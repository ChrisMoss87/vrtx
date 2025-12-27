<?php

namespace App\Services\Scheduling;

use App\Domain\User\Entities\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MeetingBookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Book a meeting.
     */
    public function bookMeeting(
        MeetingType $meetingType,
        array $data
    ): ScheduledMeeting {
        $schedulingPage = $meetingType->schedulingPage;
        $host = $schedulingPage->user;

        // Parse the start time
        $startTime = Carbon::parse($data['start_time']);
        $endTime = $startTime->copy()->addMinutes($meetingType->duration_minutes);

        // Verify slot is still available
        if (!$this->availabilityService->isSlotAvailable($meetingType, $startTime, $data['timezone'] ?? 'UTC')) {
            throw new \Exception('This time slot is no longer available. Please select another time.');
        }

        return DB::transaction(function () use ($meetingType, $host, $data, $startTime, $endTime) {
            // Find or create contact
            $contactId = $this->findOrCreateContact($host, $data);

            // Create the meeting
            $meeting = DB::table('scheduled_meetings')->insertGetId([
                'meeting_type_id' => $meetingType->id,
                'host_user_id' => $host->id,
                'contact_id' => $contactId,
                'attendee_name' => $data['name'],
                'attendee_email' => $data['email'],
                'attendee_phone' => $data['phone'] ?? null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'timezone' => $data['timezone'] ?? 'UTC',
                'location' => $this->generateLocation($meetingType),
                'notes' => $data['notes'] ?? null,
                'answers' => $data['answers'] ?? null,
                'status' => ScheduledMeeting::STATUS_SCHEDULED,
            ]);

            // Log as activity if contact was found/created
            if ($contactId) {
                $this->logMeetingActivity($meeting, $contactId);
            }

            // TODO: Create calendar event via CalendarSyncService
            // TODO: Send confirmation email

            return $meeting;
        });
    }

    /**
     * Reschedule a meeting.
     */
    public function rescheduleMeeting(
        ScheduledMeeting $meeting,
        Carbon $newStartTime,
        string $timezone = 'UTC'
    ): ScheduledMeeting {
        if (!$meeting->can_reschedule) {
            throw new \Exception('This meeting cannot be rescheduled.');
        }

        $meetingType = $meeting->meetingType;

        // Verify new slot is available
        if (!$this->availabilityService->isSlotAvailable($meetingType, $newStartTime, $timezone)) {
            throw new \Exception('This time slot is not available. Please select another time.');
        }

        $newEndTime = $newStartTime->copy()->addMinutes($meetingType->duration_minutes);

        // Update the meeting
        $meeting->update([
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
            'timezone' => $timezone,
            'status' => ScheduledMeeting::STATUS_SCHEDULED,
        ]);

        // TODO: Update calendar event
        // TODO: Send reschedule notification email

        return $meeting->fresh();
    }

    /**
     * Cancel a meeting.
     */
    public function cancelMeeting(ScheduledMeeting $meeting, ?string $reason = null): void
    {
        if (!$meeting->can_cancel) {
            throw new \Exception('This meeting cannot be cancelled.');
        }

        $meeting->cancel($reason);

        // TODO: Delete calendar event
        // TODO: Send cancellation email
    }

    /**
     * Find existing contact or create new one.
     */
    protected function findOrCreateContact(User $host, array $data): ?int
    {
        try {
            // Try to find existing contact by email
            $contact = ModuleRecord::whereHas('module', function ($q) {
                $q->where('api_name', 'contacts');
            })
                ->whereJsonContains('data->email', $data['email'])
                ->first();

            if ($contact) {
                return $contact->id;
            }

            // Get contacts module
            $contactsModule = DB::table('modules')->where('api_name', 'contacts')->first();
            if (!$contactsModule) {
                return null;
            }

            // Parse name
            $nameParts = explode(' ', $data['name'], 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';

            // Create new contact
            $contact = DB::table('module_records')->insertGetId([
                'module_id' => $contactsModule->id,
                'owner_id' => $host->id,
                'data' => [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'lead_source' => 'Meeting Scheduler',
                ],
            ]);

            return $contact->id;
        } catch (\Exception $e) {
            Log::warning('Failed to find/create contact for meeting booking', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Log meeting as an activity on the contact.
     */
    protected function logMeetingActivity(ScheduledMeeting $meeting, int $contactId): void
    {
        try {
            DB::table('activities')->insertGetId([
                'module_record_id' => $contactId,
                'user_id' => $meeting->host_user_id,
                'type' => 'meeting',
                'subject' => "Meeting: {$meeting->meetingType->name}",
                'description' => "Scheduled meeting with {$meeting->attendee_name}",
                'scheduled_at' => $meeting->start_time,
                'duration_minutes' => $meeting->duration_minutes,
                'status' => 'scheduled',
                'metadata' => [
                    'scheduled_meeting_id' => $meeting->id,
                    'meeting_type' => $meeting->meetingType->name,
                    'location' => $meeting->location,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log meeting activity', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate location string based on meeting type.
     */
    protected function generateLocation(MeetingType $meetingType): string
    {
        return match ($meetingType->location_type) {
            'zoom' => 'Zoom meeting (link will be sent via email)',
            'google_meet' => 'Google Meet (link will be sent via email)',
            'phone' => 'Phone call',
            'in_person' => $meetingType->location_details ?? 'In person',
            'custom' => $meetingType->location_details ?? '',
            default => '',
        };
    }

    /**
     * Get meeting by manage token.
     */
    public function getMeetingByToken(string $token): ?ScheduledMeeting
    {
        return DB::table('scheduled_meetings')->where('manage_token', $token)
            ->with(['meetingType.schedulingPage', 'host'])
            ->first();
    }
}

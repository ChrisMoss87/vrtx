<?php

namespace App\Services\Meeting;

use App\Domain\User\Entities\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    public function getMeetings(
        User $user,
        ?string $from = null,
        ?string $to = null,
        ?int $dealId = null,
        ?int $companyId = null
    ): Collection {
        $query = SyncedMeeting::with('participants')
            ->forUser($user->id)
            ->orderBy('start_time');

        if ($from && $to) {
            $query->inDateRange($from, $to);
        }

        if ($dealId) {
            $query->forDeal($dealId);
        }

        if ($companyId) {
            $query->forCompany($companyId);
        }

        return $query->get();
    }

    public function getUpcomingMeetings(User $user, int $limit = 10): Collection
    {
        return SyncedMeeting::with('participants')
            ->forUser($user->id)
            ->upcoming()
            ->take($limit)
            ->get();
    }

    public function getTodaysMeetings(User $user): Collection
    {
        return SyncedMeeting::with('participants')
            ->forUser($user->id)
            ->whereDate('start_time', today())
            ->where('status', '!=', SyncedMeeting::STATUS_CANCELLED)
            ->orderBy('start_time')
            ->get();
    }

    public function getMeeting(int $id): ?SyncedMeeting
    {
        return SyncedMeeting::with('participants')->find($id);
    }

    public function linkMeetingToDeal(SyncedMeeting $meeting, int $dealId): SyncedMeeting
    {
        $meeting->linkToDeal($dealId);
        return $meeting->fresh();
    }

    public function linkMeetingToCompany(SyncedMeeting $meeting, int $companyId): SyncedMeeting
    {
        $meeting->linkToCompany($companyId);
        return $meeting->fresh();
    }

    public function recordOutcome(SyncedMeeting $meeting, string $outcome, ?string $notes = null): SyncedMeeting
    {
        $meeting->recordOutcome($outcome, $notes);
        return $meeting->fresh();
    }

    public function createMeeting(User $user, array $data): SyncedMeeting
    {
        $meeting = DB::table('synced_meetings')->insertGetId([
            'user_id' => $user->id,
            'calendar_provider' => $data['calendar_provider'] ?? 'manual',
            'external_event_id' => $data['external_event_id'] ?? uniqid('manual_'),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'location' => $data['location'] ?? null,
            'is_online' => $data['is_online'] ?? false,
            'meeting_url' => $data['meeting_url'] ?? null,
            'organizer_email' => $user->email,
            'status' => SyncedMeeting::STATUS_CONFIRMED,
            'deal_id' => $data['deal_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
        ]);

        // Add participants
        if (!empty($data['participants'])) {
            foreach ($data['participants'] as $participant) {
                DB::table('meeting_participants')->insertGetId([
                    'meeting_id' => $meeting->id,
                    'email' => $participant['email'],
                    'name' => $participant['name'] ?? null,
                    'is_organizer' => $participant['email'] === $user->email,
                    'response_status' => MeetingParticipant::RESPONSE_NEEDS_ACTION,
                ]);
            }
        }

        return $meeting->fresh('participants');
    }

    public function updateMeeting(SyncedMeeting $meeting, array $data): SyncedMeeting
    {
        $meeting->update(array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'location' => $data['location'] ?? null,
            'is_online' => $data['is_online'] ?? null,
            'meeting_url' => $data['meeting_url'] ?? null,
            'deal_id' => array_key_exists('deal_id', $data) ? $data['deal_id'] : $meeting->deal_id,
            'company_id' => array_key_exists('company_id', $data) ? $data['company_id'] : $meeting->company_id,
        ], fn ($v) => $v !== null));

        return $meeting->fresh('participants');
    }

    public function deleteMeeting(SyncedMeeting $meeting): void
    {
        $meeting->delete();
    }

    public function getMeetingsForDeal(int $dealId): Collection
    {
        return SyncedMeeting::with('participants')
            ->forDeal($dealId)
            ->orderByDesc('start_time')
            ->get();
    }

    public function getMeetingsForCompany(int $companyId): Collection
    {
        return SyncedMeeting::with('participants')
            ->forCompany($companyId)
            ->orderByDesc('start_time')
            ->get();
    }
}

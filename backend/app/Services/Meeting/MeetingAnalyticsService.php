<?php

namespace App\Services\Meeting;

use App\Models\SyncedMeeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingAnalyticsCache;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MeetingAnalyticsService
{
    public function getOverview(User $user, ?string $period = 'month'): array
    {
        $dateRange = $this->getDateRange($period);

        $currentPeriod = SyncedMeeting::forUser($user->id)
            ->inDateRange($dateRange['current']['start'], $dateRange['current']['end'])
            ->where('status', '!=', SyncedMeeting::STATUS_CANCELLED)
            ->get();

        $previousPeriod = SyncedMeeting::forUser($user->id)
            ->inDateRange($dateRange['previous']['start'], $dateRange['previous']['end'])
            ->where('status', '!=', SyncedMeeting::STATUS_CANCELLED)
            ->get();

        $currentCount = $currentPeriod->count();
        $previousCount = $previousPeriod->count();

        $currentDuration = $currentPeriod->sum(fn ($m) => $m->getDurationMinutes());

        $uniqueStakeholders = MeetingParticipant::whereIn('meeting_id', $currentPeriod->pluck('id'))
            ->where('is_organizer', false)
            ->distinct('email')
            ->count('email');

        $change = $previousCount > 0
            ? round((($currentCount - $previousCount) / $previousCount) * 100, 1)
            : null;

        return [
            'total_meetings' => $currentCount,
            'total_hours' => round($currentDuration / 60, 1),
            'unique_stakeholders' => $uniqueStakeholders,
            'change_percent' => $change,
            'period' => $period,
        ];
    }

    public function getHeatmap(User $user, ?int $weeks = 4): array
    {
        $startDate = now()->subWeeks($weeks)->startOfWeek();
        $endDate = now()->endOfWeek();

        $meetings = SyncedMeeting::forUser($user->id)
            ->inDateRange($startDate->toDateString(), $endDate->toDateString())
            ->where('status', '!=', SyncedMeeting::STATUS_CANCELLED)
            ->get();

        // Build heatmap: day of week x hour
        $heatmap = [];
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        $hours = range(8, 18); // 8 AM to 6 PM

        foreach ($hours as $hour) {
            $heatmap[$hour] = [];
            foreach ($days as $day) {
                $heatmap[$hour][$day] = 0;
            }
        }

        foreach ($meetings as $meeting) {
            $dayOfWeek = $meeting->start_time->format('D');
            $hour = (int) $meeting->start_time->format('G');

            if (isset($heatmap[$hour][$dayOfWeek])) {
                $heatmap[$hour][$dayOfWeek]++;
            }
        }

        // Find peak times
        $maxCount = 0;
        $peakTimes = [];

        foreach ($heatmap as $hour => $days) {
            foreach ($days as $day => $count) {
                if ($count > $maxCount) {
                    $maxCount = $count;
                    $peakTimes = [['hour' => $hour, 'day' => $day]];
                } elseif ($count === $maxCount && $count > 0) {
                    $peakTimes[] = ['hour' => $hour, 'day' => $day];
                }
            }
        }

        return [
            'data' => $heatmap,
            'max_value' => $maxCount,
            'peak_times' => array_slice($peakTimes, 0, 3),
            'days' => $days,
            'hours' => $hours,
        ];
    }

    public function getDealAnalytics(int $dealId): array
    {
        $meetings = SyncedMeeting::with('participants')
            ->forDeal($dealId)
            ->where('status', '!=', SyncedMeeting::STATUS_CANCELLED)
            ->orderBy('start_time')
            ->get();

        if ($meetings->isEmpty()) {
            return [
                'total_meetings' => 0,
                'total_hours' => 0,
                'unique_stakeholders' => 0,
                'meetings_per_week' => null,
                'first_meeting' => null,
                'last_meeting' => null,
                'timeline' => [],
                'stakeholders' => [],
            ];
        }

        $firstMeeting = $meetings->first();
        $lastMeeting = $meetings->last();
        $weeks = max(1, $firstMeeting->start_time->diffInWeeks(now()));

        $stakeholderMeetings = [];
        foreach ($meetings as $meeting) {
            foreach ($meeting->getExternalParticipants() as $participant) {
                if (!isset($stakeholderMeetings[$participant->email])) {
                    $stakeholderMeetings[$participant->email] = [
                        'email' => $participant->email,
                        'name' => $participant->name,
                        'contact_id' => $participant->contact_id,
                        'meeting_count' => 0,
                        'last_met' => null,
                    ];
                }
                $stakeholderMeetings[$participant->email]['meeting_count']++;
                $stakeholderMeetings[$participant->email]['last_met'] = $meeting->start_time->toISOString();
            }
        }

        return [
            'total_meetings' => $meetings->count(),
            'total_hours' => round($meetings->sum(fn ($m) => $m->getDurationMinutes()) / 60, 1),
            'unique_stakeholders' => count($stakeholderMeetings),
            'meetings_per_week' => round($meetings->count() / $weeks, 1),
            'first_meeting' => $firstMeeting->start_time->toISOString(),
            'last_meeting' => $lastMeeting->start_time->toISOString(),
            'timeline' => $meetings->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'date' => $m->start_time->toISOString(),
                'duration_minutes' => $m->getDurationMinutes(),
                'participant_count' => $m->participants->count(),
            ]),
            'stakeholders' => array_values($stakeholderMeetings),
        ];
    }

    public function getStakeholderCoverage(int $companyId, ?int $dealId = null): array
    {
        // Get all contacts for the company
        // This would typically query the contacts module
        $query = SyncedMeeting::with('participants')
            ->forCompany($companyId)
            ->where('status', '!=', SyncedMeeting::STATUS_CANCELLED);

        if ($dealId) {
            $query->forDeal($dealId);
        }

        $meetings = $query->get();

        $stakeholders = [];
        foreach ($meetings as $meeting) {
            foreach ($meeting->getExternalParticipants() as $participant) {
                $key = $participant->email;
                if (!isset($stakeholders[$key])) {
                    $stakeholders[$key] = [
                        'email' => $participant->email,
                        'name' => $participant->name,
                        'contact_id' => $participant->contact_id,
                        'meeting_count' => 0,
                        'first_met' => null,
                        'last_met' => null,
                        'total_minutes' => 0,
                    ];
                }
                $stakeholders[$key]['meeting_count']++;
                $stakeholders[$key]['total_minutes'] += $meeting->getDurationMinutes();

                if (!$stakeholders[$key]['first_met'] || $meeting->start_time->lt($stakeholders[$key]['first_met'])) {
                    $stakeholders[$key]['first_met'] = $meeting->start_time->toISOString();
                }
                $stakeholders[$key]['last_met'] = $meeting->start_time->toISOString();
            }
        }

        // Sort by meeting count
        usort($stakeholders, fn ($a, $b) => $b['meeting_count'] - $a['meeting_count']);

        return [
            'total_stakeholders' => count($stakeholders),
            'total_meetings' => $meetings->count(),
            'stakeholders' => array_values($stakeholders),
        ];
    }

    public function getDealInsights(int $dealId): array
    {
        $analytics = $this->getDealAnalytics($dealId);
        $insights = [];

        // Meeting cadence insight
        if ($analytics['total_meetings'] > 0) {
            $meetingsPerWeek = $analytics['meetings_per_week'];

            if ($meetingsPerWeek < 0.5) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Low meeting frequency',
                    'description' => 'Consider scheduling more regular check-ins to maintain momentum.',
                ];
            } elseif ($meetingsPerWeek >= 1.5) {
                $insights[] = [
                    'type' => 'success',
                    'title' => 'Strong engagement',
                    'description' => 'Good meeting frequency indicates healthy deal momentum.',
                ];
            }
        }

        // Stakeholder coverage insight
        if ($analytics['unique_stakeholders'] < 3 && $analytics['total_meetings'] >= 3) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Limited stakeholder engagement',
                'description' => 'Try to involve more stakeholders to build broader support.',
            ];
        }

        // Recency insight
        if ($analytics['last_meeting']) {
            $lastMeetingDate = new \DateTime($analytics['last_meeting']);
            $daysSinceLastMeeting = (new \DateTime())->diff($lastMeetingDate)->days;

            if ($daysSinceLastMeeting > 14) {
                $insights[] = [
                    'type' => 'warning',
                    'title' => 'Engagement gap',
                    'description' => "It's been {$daysSinceLastMeeting} days since your last meeting. Consider reaching out.",
                ];
            }
        } else {
            $insights[] = [
                'type' => 'info',
                'title' => 'No meetings scheduled',
                'description' => 'Schedule an initial meeting to kick off the engagement.',
            ];
        }

        return [
            'analytics' => $analytics,
            'insights' => $insights,
        ];
    }

    private function getDateRange(string $period): array
    {
        $current = match ($period) {
            'week' => [
                'start' => now()->startOfWeek()->toDateString(),
                'end' => now()->endOfWeek()->toDateString(),
            ],
            'quarter' => [
                'start' => now()->firstOfQuarter()->toDateString(),
                'end' => now()->lastOfQuarter()->toDateString(),
            ],
            default => [ // month
                'start' => now()->startOfMonth()->toDateString(),
                'end' => now()->endOfMonth()->toDateString(),
            ],
        };

        $previous = match ($period) {
            'week' => [
                'start' => now()->subWeek()->startOfWeek()->toDateString(),
                'end' => now()->subWeek()->endOfWeek()->toDateString(),
            ],
            'quarter' => [
                'start' => now()->subQuarter()->firstOfQuarter()->toDateString(),
                'end' => now()->subQuarter()->lastOfQuarter()->toDateString(),
            ],
            default => [ // month
                'start' => now()->subMonth()->startOfMonth()->toDateString(),
                'end' => now()->subMonth()->endOfMonth()->toDateString(),
            ],
        };

        return ['current' => $current, 'previous' => $previous];
    }
}

<x-mail::message>
# Deal Health Report

Hi {{ $user->name }},

Here's your deal health summary. The following deals need your attention:

## Summary
@if($summary['rotting'] > 0)
- **{{ $summary['rotting'] }}** deals are rotting (need immediate action)
@endif
@if($summary['stale'] > 0)
- **{{ $summary['stale'] }}** deals are stale (approaching threshold)
@endif
@if($summary['warming'] > 0)
- **{{ $summary['warming'] }}** deals are warming (monitor closely)
@endif

---

## Deals Requiring Attention

@foreach($deals->take(10) as $deal)
@php
$status = $deal['rot_status']['status'];
$color = match($status) {
    'rotting' => '🔴',
    'stale' => '🟠',
    'warming' => '🟡',
    default => '🟢',
};
$recordName = $deal['record']['data']['name'] ?? $deal['record']['data']['title'] ?? 'Record #' . $deal['record']['id'];
@endphp

### {{ $color }} {{ $recordName }}
- **Pipeline:** {{ $deal['pipeline']['name'] }} → {{ $deal['stage']['name'] }}
- **Days Inactive:** {{ $deal['rot_status']['days_inactive'] }} days (threshold: {{ $deal['rot_status']['threshold_days'] }} days)
- **Status:** {{ ucfirst($status) }} ({{ $deal['rot_status']['percentage'] }}% of threshold)

@endforeach

@if($deals->count() > 10)
*...and {{ $deals->count() - 10 }} more deals*
@endif

<x-mail::button :url="config('app.frontend_url') . '/deals/rotting'">
View All Rotting Deals
</x-mail::button>

---

To update your notification preferences or adjust deal rotting thresholds, visit your settings page.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

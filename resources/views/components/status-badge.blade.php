@props(['status'])
@php
    $variant = match (true) {
        $status instanceof \App\Enums\ElectionStatus => $status->badge(),
        in_array($status?->value, ['ACTIVE', 'VERIFIED', 'ELIGIBLE', 'COMPLETED', 'PUBLISHED', 'SUCCESS', 'REVIEWED'], true) => 'success',
        in_array($status?->value, ['VOTED', 'VERIFIED_RESULT', 'CALCULATED', 'PREVIEWED', 'PROCESSING', 'LOW', 'INFO'], true) => 'info',
        in_array($status?->value, ['SUSPENDED', 'UNVERIFIED', 'INACTIVE', 'EXPIRED', 'MEDIUM', 'WITHDRAWN', 'OPEN_ALERT'], true) => 'warning',
        in_array($status?->value, ['DISABLED', 'REJECTED', 'INELIGIBLE', 'FAILED', 'DENIED', 'FAILURE', 'DISQUALIFIED', 'HIGH', 'CRITICAL', 'REVOKED'], true) => 'error',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$variant]) }}>{{ $status?->label() ?? '-' }}</span>

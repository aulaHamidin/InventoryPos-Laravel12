@php
    $actor = auth()->user();
    $impersonation = \App\Services\ImpersonationContext::session();
    $subscription = $actor instanceof \App\Models\User && $actor->tenant
        ? app(\App\Support\SubscriptionCapabilityService::class)->current($actor->tenant)
        : null;
@endphp
@if ($impersonation)
    <div class="sticky top-0 z-50 flex items-center justify-between gap-4 bg-amber-500 px-4 py-3 text-sm font-semibold text-black">
        <span>Mode support read-only aktif sampai {{ $impersonation->expires_at->timezone('Asia/Jakarta')->format('H:i') }} WIB. Semua mutation ditolak.</span>
        <form method="POST" action="{{ route('impersonation.end') }}">@csrf <button class="rounded bg-black px-3 py-1 text-white">Akhiri</button></form>
    </div>
@elseif ($subscription && ! in_array($subscription->status->value, ['trial', 'active'], true))
    <div @class(['px-4 py-3 text-sm font-semibold', 'bg-amber-100 text-amber-900' => $subscription->status->value === 'past_due', 'bg-red-100 text-red-900' => in_array($subscription->status->value, ['suspended', 'expired'], true)])>
        Status langganan: {{ $subscription->status->value }}. Buka Billing untuk melihat capability yang tersedia.
    </div>
@endif

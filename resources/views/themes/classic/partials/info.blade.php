{{-- Open-now state, today's hours, address and contact buttons. --}}
<div class="k-info">
    <div class="k-info__row">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7.5V12l3 2"></path></svg>
        <span>
            @if ($live->isOpenNow())
                <strong class="k-open">{{ __('menu.open_now') }}</strong>
                @if ($live->closesAt()) · {{ __('menu.closes_at', ['time' => $live->closesAt()]) }} @endif
            @else
                <strong class="k-closed">{{ __('menu.closed_now') }}</strong>
                @if ($live->opensAt()) · {{ __('menu.opens_at', ['time' => $live->opensAt()]) }} @endif
            @endif
            @if ($live->todayHours())
                <span class="k-muted"> · {{ __('menu.today') }} {{ $live->todayHours() }}</span>
            @endif
        </span>
    </div>
    @if ($menu->location->address)
        <div class="k-info__row">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"></path></svg>
            <span>{{ $menu->location->address }}</span>
        </div>
    @endif
    @if ($menu->location->mapUrl || $menu->location->phone || $menu->location->whatsapp)
        <div class="k-info__actions">
            @if ($menu->location->whatsapp)
                <a class="k-btn k-btn--solid" href="https://wa.me/{{ preg_replace('/\D+/', '', $menu->location->whatsapp) }}" rel="noopener">{{ __('menu.whatsapp') }}</a>
            @elseif ($menu->location->phone)
                <a class="k-btn k-btn--solid" href="tel:{{ preg_replace('/[^\d+]+/', '', $menu->location->phone) }}">{{ __('menu.call') }}</a>
            @endif
            @if ($menu->location->mapUrl)
                <a class="k-btn k-btn--ghost" href="{{ $menu->location->mapUrl }}" rel="noopener" target="_blank">{{ __('menu.directions') }}</a>
            @endif
        </div>
    @endif
</div>

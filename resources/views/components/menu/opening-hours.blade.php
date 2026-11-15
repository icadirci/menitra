{{-- Weekly hours (today highlighted) and upcoming special days. --}}
@props(['location', 'live'])
<div {{ $attributes->merge(['class' => 'menu-hours']) }}>
    <dl class="menu-hours__week">
        @foreach (range(1, 7) as $day)
            <div class="menu-hours__row" @if ($day === $live->todayIsoDay()) aria-current="date" @endif>
                <dt>{{ __("menu.days.{$day}") }}</dt>
                <dd>
                    @forelse (collect($location->weeklyHours)->where('day', $day) as $interval)
                        <span>{{ $interval->opens }}–{{ $interval->closes }}</span>
                    @empty
                        <span class="menu-hours__closed">{{ __('menu.closed') }}</span>
                    @endforelse
                </dd>
            </div>
        @endforeach
    </dl>
    @if ($location->specialHours !== [])
        <h3 class="menu-hours__special-title">{{ __('menu.special_hours') }}</h3>
        <dl class="menu-hours__week">
            @foreach (collect($location->specialHours)->groupBy('date') as $date => $rows)
                <div class="menu-hours__row">
                    <dt>{{ \Carbon\CarbonImmutable::parse($date)->locale(app()->getLocale())->isoFormat('D MMMM, dddd') }}</dt>
                    <dd>
                        @foreach ($rows as $row)
                            @if ($row->isClosed)
                                <span class="menu-hours__closed">{{ __('menu.closed') }}</span>
                            @else
                                <span>{{ $row->opens }}–{{ $row->closes }}</span>
                            @endif
                        @endforeach
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>

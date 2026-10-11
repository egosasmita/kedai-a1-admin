@props([
    'label',
    'value',
    'detail',
    'icon' => 'grid',
    'dark' => false,
])

<article {{ $attributes->class(['stat-card', 'stat-card-dark' => $dark]) }}>
    <div class="stat-topline">
        <span>{{ $label }}</span>
        <span class="stat-icon" aria-hidden="true">
            @switch($icon)
                @case('users')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="9" cy="8" r="3"/><path d="M3.5 20v-1.5a5.5 5.5 0 0 1 11 0V20M16 5.5a3 3 0 0 1 0 5.8M17 14a5 5 0 0 1 3.5 4.8V20"/></svg>
                    @break
                @case('calendar')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M7.5 3v4M16.5 3v4M4 9h16M8 13h3M8 17h2"/></svg>
                    @break
                @case('warning')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M10.3 4.4 2.7 18a2 2 0 0 0 1.8 3h15a2 2 0 0 0 1.8-3L13.7 4.4a2 2 0 0 0-3.4 0Z"/><path d="M12 9v5m0 3h.01"/></svg>
                    @break
                @case('box')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 8 9 5 9-5M3 8v9l9 5 9-5V8M12 13v9"/></svg>
                    @break
                @default
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="6" y="3" width="12" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h6M9 19h6"/></svg>
            @endswitch
        </span>
    </div>
    <div class="stat-bottomline">
        <strong>{{ $value }}</strong>
        <span>{{ $detail }}</span>
    </div>
</article>

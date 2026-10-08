<x-filament-widgets::widget>
    <div style="position: relative; min-height: 22rem; overflow: hidden; border-radius: 0.75rem; padding: 1.5rem; color: #fff; background: radial-gradient(60% 80% at 10% 10%, #f43f5e 0, transparent 60%), radial-gradient(50% 70% at 90% 20%, #f59e0b 0, transparent 60%), radial-gradient(60% 80% at 80% 90%, #0ea5e9 0, transparent 60%), radial-gradient(50% 60% at 20% 90%, #8b5cf6 0, transparent 60%), #10b981;">
        <p style="margin: 0; font-size: 0.875rem; font-weight: 600; opacity: 0.9;">Campaign reach</p>
        <p style="margin: 0.25rem 0 1.5rem; font-size: 2.25rem; font-weight: 700; font-variant-numeric: tabular-nums;">1.28M</p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 0.75rem;">
            @foreach (['Email' => '#111827', 'Social' => '#fde047', 'Search' => '#ffffff', 'Partners' => '#1e3a8a', 'Events' => '#be123c', 'Referrals' => '#064e3b'] as $channel => $color)
                <div style="display: flex; align-items: flex-end; min-height: 6rem; padding: 0.75rem; border-radius: 0.75rem; background: {{ $color }}; color: {{ in_array($color, ['#fde047', '#ffffff']) ? '#111827' : '#fff' }}; font-weight: 600;">{{ $channel }}</div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>

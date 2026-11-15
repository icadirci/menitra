<footer class="k-footer">
    @include('themes.classic.partials.ornament', ['id' => 'k-band-bottom', 'size' => 18, 'variant' => 'light'])
    <div class="k-footer__body">
        <p>{{ __('menu.prices_note') }}</p>
        <x-menu.branding-footer :brand="$menu->brand" />
    </div>
</footer>

{{-- Panel brand logo (both panels). Inline styles only: panels load no app.css. --}}
<span style="display: inline-flex; align-items: center; gap: 0.625rem; height: 100%; color: inherit">
    <x-ui.logo-mark :size="null" />
    <span style="font-weight: 700; font-size: 1.125rem; letter-spacing: -0.02em; white-space: nowrap">{{ $name }}</span>
</span>

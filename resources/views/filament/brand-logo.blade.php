{{-- Panel brand logo (both panels). Inline styles only: panels load no app.css. --}}
<span style="display: inline-flex; align-items: center; gap: 0.625rem; height: 100%; color: inherit">
    <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false" style="height: 100%; width: auto; flex: none">
        <rect width="32" height="32" rx="9" fill="#1F4D3B" />
        <rect x="7" y="7" width="7" height="7" rx="2" fill="#F6F5F1" />
        <rect x="18" y="7" width="7" height="7" rx="2" fill="#F6F5F1" />
        <rect x="7" y="18" width="7" height="7" rx="2" fill="#F6F5F1" />
        <circle cx="21.5" cy="21.5" r="3.6" fill="#E7A33E" />
    </svg>
    <span style="font-weight: 700; font-size: 1.125rem; letter-spacing: -0.02em; white-space: nowrap">{{ $name }}</span>
</span>

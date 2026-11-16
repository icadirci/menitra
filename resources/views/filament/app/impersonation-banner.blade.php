{{-- /app band while a super admin impersonates (launch plan Faz 8). Inline styles only: panels load no app.css. --}}
@php($impersonation = app(\App\Services\Admin\Impersonation::class))
@if ($impersonation->isActive() && auth()->check())
    <div role="status" style="position: sticky; top: 0; z-index: 50; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.5rem 1rem; padding: 0.5rem 1rem; background: #7C2D12; color: #FFF7ED; font-size: 0.875rem; line-height: 1.25rem">
        <span>
            {{ __('admin.impersonation.banner', ['name' => auth()->user()->name, 'email' => auth()->user()->email]) }}
            <span style="opacity: 0.85">· {{ __('admin.impersonation.banner_expires', ['time' => $impersonation->expiresAt()?->timezone(config('app.timezone'))->format('H:i')]) }}</span>
        </span>
        <form method="POST" action="{{ route('impersonation.leave') }}" style="margin: 0">
            @csrf
            <button type="submit" style="padding: 0.25rem 0.75rem; border-radius: 0.375rem; border: 1px solid #FFF7ED; background: transparent; color: inherit; font-weight: 600; cursor: pointer">
                {{ __('admin.impersonation.leave') }}
            </button>
        </form>
    </div>
@endif

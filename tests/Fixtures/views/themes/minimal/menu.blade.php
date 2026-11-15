{{-- Test fixture: stands in for a built premium theme (ThemeManagementTest adds tests/Fixtures/views to the view finder). --}}
<div class="menu-root" data-theme="{{ $theme->key() }}" style="{{ $theme->cssVariables() }}">{{ $menu->menu->name }}</div>

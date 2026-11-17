{{--
    The brand logo as a "home" link, shared by every theme: the branch's start
    page (its only menu when it has one, as the location URL renders that menu
    too). On the menu page itself menuPage.goHome() resets it in place (closes
    the category, clears the search, back to top) instead of reloading.
    Inputs: $menu (MenuSnapshot).
--}}
@props(['menu'])

<a {{ $attributes->merge(['class' => 'menu-home']) }}
   href="{{ ($menu->menu !== null && count($menu->menus) === 1 ? $menu->menus[0]->url : $menu->location->url) }}?lang={{ $menu->locale }}"
   aria-label="{{ __('menu.home_link', ['name' => $menu->brand->name]) }}"
   @click="goHome($event)">{{ $slot }}</a>

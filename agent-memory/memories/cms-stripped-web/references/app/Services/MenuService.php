<?php

namespace App\Services;

final class MenuService
{
    protected $menus;

    protected $user;

    protected $userPermissions;

    public function __construct()
    {
        $this->menus = collect([]);
        $this->user = \Illuminate\Support\Facades\Auth::user();
        $this->userPermissions = [];
        if ($this->user instanceof \App\Models\User && method_exists($this->user, 'getPermissions')) {
            $this->userPermissions = $this->user->getPermissions()->pluck('name')->toArray();
        }
    }

    protected function hasPermission(array|string $permissions): bool
    {
        if (is_string($permissions)) {
            return in_array($permissions, $this->userPermissions, true);
        }

        return ! empty(array_intersect($permissions, $this->userPermissions));
    }

    public function handlerMenu(): array|\Illuminate\Support\Collection
    {
        if (\Illuminate\Support\Facades\Auth::check()) {

            $this->menus[] = [
                'name' => 'dashboard',
                'title' => 'Dashboard',
                'icon' => 'DashboardIcon',
                'route' => route('dashboard'),
            ];

            $this->menus[] = [
                'name' => 'media.index',
                'title' => 'Media',
                'icon' => 'GalleryIcon',
                'route' => route('media.index'),
            ];

            if (config('app.env') === 'local' || $this->hasPermission(['admin.access.rewards', 'admin.access.rewards.read'])) {
                $this->menus[] = [
                    'name' => 'rewards.*',
                    'title' => 'Rewards',
                    'icon' => 'GiftIcon',
                    'route' => route('rewards.index'),
                ];
            }

            if (config('app.env') === 'local' || $this->hasPermission(['admin.access.sliders', 'admin.access.sliders.read'])) {
                $this->menus[] = [
                    'name' => 'sliders.*',
                    'title' => 'Sliders',
                    'icon' => 'SlidersIcon',
                    'route' => route('sliders.index'),
                ];
            }

            if (config('app.env') === 'local' || $this->hasPermission(['admin.access.promos', 'admin.access.promos.read'])) {
                $this->menus[] = [
                    'name' => 'promos.*',
                    'title' => 'Promos',
                    'icon' => 'PriceTagIcon',
                    'route' => route('promos.index'),
                ];
            }

            if (config('app.env') === 'local' || $this->hasPermission(['admin.access.promo-categories', 'admin.access.promo-categories.read'])) {
                $this->menus[] = [
                    'name' => 'promo-categories.*',
                    'title' => 'Promo Categories',
                    'icon' => 'FolderIcon',
                    'route' => route('promo-categories.index'),
                ];
            }

            if (config('app.env') === 'local' || $this->hasPermission(['admin.access.members', 'admin.access.members.read'])) {
                $this->menus[] = [
                    'name' => 'members.*',
                    'title' => 'Members',
                    'icon' => 'MemberIcon',
                    'route' => route('members.index'),
                ];
            }

            if (config('app.env') === 'local' || $this->hasPermission(['admin.access.manage.pages', 'admin.access.manage.pages.read'])) {
                $this->menus[] = [
                    'name' => 'management.pages.*',
                    'title' => 'Pages',
                    'icon' => 'PageIcon',
                    'route' => route('management.pages.index'),
                ];
            }

            $this->userManagementMenu();

            $this->settingMenu();
        }

        return $this->menus;
    }

    protected function userManagementMenu(): void
    {
        $menu = [
            'name' => 'management.*',
            'title' => 'Management',
            'icon' => 'UserManagementIcon',
            'route' => '#',
            'children' => [],
        ];

        $hasAnyPermission = false;

        if ($this->hasPermission([
            'admin.access.manage.roles',
            'admin.access.manage.roles.read',
        ])) {
            $menu['children'][] = [
                'title' => 'Role Management',
                'name' => 'management.roles.*',
                'route' => route('management.roles.index'),
            ];
            $hasAnyPermission = true;
        }

        if ($this->hasPermission([
            'admin.access.manage.user',
            'admin.access.manage.user.read',
        ])) {
            $menu['children'][] = [
                'title' => 'User Management',
                'name' => 'management.user.*',
                'route' => route('management.user.index'),
            ];
            $hasAnyPermission = true;
        }

        // Only add the menu if user has permission to at least one child
        if ($hasAnyPermission) {
            $this->menus[] = $menu;
        }
    }

    protected function settingMenu(): void
    {
        $menu = [
            'name' => 'setting.*',
            'title' => 'Settings',
            'icon' => 'SettingIcon',
            'route' => '#',
            'children' => [],
        ];

        $hasAnyPermission = false;

        $children = [
            ['title' => 'General', 'name' => 'setting.general', 'permissions' => ['admin.access.setting.general.read'], 'route' => route('setting.general')],
            ['title' => 'Activity Log', 'name' => 'setting.activity.*', 'permissions' => ['admin.access.setting.activity_log.read'], 'route' => route('setting.activity.index')],
        ];

        foreach ($children as $child) {
            if (config('app.env') === 'local') {
                $menu['children'][] = $child;
                $hasAnyPermission = true;

                continue;
            }

            if (! empty($child['permissions']) && $this->hasPermission($child['permissions'])) {
                $menu['children'][] = $child;
                $hasAnyPermission = true;
            }
        }

        // Only add the menu if user has permission to at least one child
        if ($hasAnyPermission) {
            $this->menus[] = $menu;
        }
    }
}

<?php

use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;

Breadcrumbs::for('setting.general', function (BreadcrumbTrail $trail): void {
    $trail->push('Setting');
    $trail->push('General', route('setting.general'));
});

Breadcrumbs::for('management.roles.index', function (BreadcrumbTrail $trail): void {
    $trail->push('Management');
    $trail->push('Role Management');
});

Breadcrumbs::for('management.roles.create', function (BreadcrumbTrail $trail): void {
    $trail->push('Management ');
    $trail->push('Role Management', route('management.roles.index'));
    $trail->push('Create Role', route('management.roles.create'));
});

Breadcrumbs::for('management.roles.edit', function (BreadcrumbTrail $trail, \App\Models\Setting\Role $role): void {
    $trail->push('Management ');
    $trail->push('Role Management', route('management.roles.index'));
    $trail->push('Edit Role', route('management.roles.edit', $role));
});

Breadcrumbs::for('management.user.index', function (BreadcrumbTrail $trail): void {
    $trail->push('Management');
    $trail->push('User Management');
});

Breadcrumbs::for('management.user.create', function (BreadcrumbTrail $trail): void {
    $trail->push('Management');
    $trail->push('User Management', route('management.user.index'));
    $trail->push('Create User', route('management.user.create'));
});

Breadcrumbs::for('management.user.edit', function (BreadcrumbTrail $trail, \App\Models\User $user): void {
    $trail->push('Management');
    $trail->push('User Management', route('management.user.index'));
    $trail->push('Edit User', route('management.user.edit', $user));
});

Breadcrumbs::for('rewards.index', function (BreadcrumbTrail $trail): void {
    $trail->push('Rewards');
});

Breadcrumbs::for('rewards.create', function (BreadcrumbTrail $trail): void {
    $trail->push('Rewards', route('rewards.index'));
    $trail->push('Create Reward', route('rewards.create'));
});

Breadcrumbs::for('rewards.edit', function (BreadcrumbTrail $trail, \App\Models\Reward $reward): void {
    $trail->push('Rewards', route('rewards.index'));
    $trail->push('Edit Reward', route('rewards.edit', $reward));
});

Breadcrumbs::for('rewards.show', function (BreadcrumbTrail $trail, \App\Models\Reward $reward): void {
    $trail->push('Rewards', route('rewards.index'));
    $trail->push('Reward Detail', route('rewards.show', $reward));
});

Breadcrumbs::for('sliders.index', function (BreadcrumbTrail $trail): void {
    $trail->push('Sliders');
});

Breadcrumbs::for('sliders.create', function (BreadcrumbTrail $trail): void {
    $trail->push('Sliders', route('sliders.index'));
    $trail->push('Create Slider', route('sliders.create'));
});

Breadcrumbs::for('sliders.edit', function (BreadcrumbTrail $trail, \App\Models\Slider $slider): void {
    $trail->push('Sliders', route('sliders.index'));
    $trail->push('Edit Slider', route('sliders.edit', $slider));
});

Breadcrumbs::for('sliders.show', function (BreadcrumbTrail $trail, \App\Models\Slider $slider): void {
    $trail->push('Sliders', route('sliders.index'));
    $trail->push('Slider Detail', route('sliders.show', $slider));
});

Breadcrumbs::for('promos.index', function (BreadcrumbTrail $trail): void {
    $trail->push('Promos');
});

Breadcrumbs::for('promos.create', function (BreadcrumbTrail $trail): void {
    $trail->push('Promos', route('promos.index'));
    $trail->push('Create Promo', route('promos.create'));
});

Breadcrumbs::for('promos.edit', function (BreadcrumbTrail $trail, \App\Models\Promo $promo): void {
    $trail->push('Promos', route('promos.index'));
    $trail->push('Edit Promo', route('promos.edit', $promo));
});

Breadcrumbs::for('promos.show', function (BreadcrumbTrail $trail, \App\Models\Promo $promo): void {
    $trail->push('Promos', route('promos.index'));
    $trail->push('Promo Detail', route('promos.show', $promo));
});

Breadcrumbs::for('campaigns.index', function (BreadcrumbTrail $trail): void {
    $trail->push('Campaigns');
});

Breadcrumbs::for('campaigns.create', function (BreadcrumbTrail $trail): void {
    $trail->push('Campaigns', route('campaigns.index'));
    $trail->push('Create Campaign', route('campaigns.create'));
});

Breadcrumbs::for('campaigns.edit', function (BreadcrumbTrail $trail, \App\Models\Campaign $campaign): void {
    $trail->push('Campaigns', route('campaigns.index'));
    $trail->push('Edit Campaign', route('campaigns.edit', $campaign));
});

Breadcrumbs::for('campaigns.show', function (BreadcrumbTrail $trail, \App\Models\Campaign $campaign): void {
    $trail->push('Campaigns', route('campaigns.index'));
    $trail->push('Campaign Detail', route('campaigns.show', $campaign));
});

Breadcrumbs::for('setting.activity.index', function (BreadcrumbTrail $trail): void {
    $trail->push('Activity Log');
});

Breadcrumbs::for('setting.activity.show', function (BreadcrumbTrail $trail): void {
    $trail->push('Activity Log', route('setting.activity.index'));
    $trail->push('Activity Detail');
});

<?php

namespace Database\Seeders;

use App\Models\Setting\Permission;
use App\Services\PermissionService;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $serivce = new PermissionService;

        Permission::whereNotNull('name')->delete();

        $arrayParent = [

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.manage.user',
            ], [
                'description' => json_encode([
                    'description' => 'Manage all user management - users',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'export',
                        'delete',
                        'all',
                    ],
                ]),
            ]),
            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.manage.roles',
            ], [
                'description' => json_encode([
                    'description' => 'Manage all user management - roles',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'delete',
                        'all',
                    ],
                ]),
            ]),

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.setting.general',
            ], [
                'description' => json_encode([
                    'description' => 'Manage all Setting - General',
                    'access' => [
                        'read',
                        'update',
                        'all',
                    ],
                ]),
            ]),

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.setting.activity_log',
            ], [
                'description' => json_encode([
                    'description' => 'Manage all Setting - Activity log',
                    'access' => [
                        'read',
                        'export',
                        'all',
                    ],
                ]),
            ]),
            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.rewards',
            ], [
                'description' => json_encode([
                    'description' => 'Manage Rewards',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'delete',
                        'all',
                    ],
                ]),
            ]),
            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.sliders',
            ], [
                'description' => json_encode([
                    'description' => 'Manage Sliders',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'delete',
                        'all',
                    ],
                ]),
            ]),
            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.promos',
            ], [
                'description' => json_encode([
                    'description' => 'Manage Promos',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'delete',
                        'all',
                    ],
                ]),
            ]),

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.promo-categories',
            ], [
                'description' => json_encode([
                    'description' => 'Manage Promo Categories',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'delete',
                        'all',
                    ],
                ]),
            ]),

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.campaigns',
            ], [
                'description' => json_encode([
                    'description' => 'Manage Campaigns',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'delete',
                        'all',
                    ],
                ]),
            ]),

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.members',
            ], [
                'description' => json_encode([
                    'description' => 'Manage Members',
                    'access' => [
                        'read',
                        'block',
                        'unblock',
                        'all',
                    ],
                ]),
            ]),

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.manage.pages',
            ], [
                'description' => json_encode([
                    'description' => 'Manage Static Pages (Terms & Conditions, Privacy Policy, etc.)',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'delete',
                        'preview',
                        'toggle-status',
                        'reorder',
                        'all',
                    ],
                ]),
            ]),

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.media',
            ], [
                'description' => json_encode([
                    'description' => 'Manage Media Library',
                    'access' => [
                        'read',
                        'update',
                        'delete',
                        'all',
                    ],
                ]),
            ]),

            Permission::updateOrCreate([
                'type' => 'admin',
                'name' => 'admin.access.upload',
            ], [
                'description' => json_encode([
                    'description' => 'Manage File Uploads',
                    'access' => [
                        'create',
                        'read',
                        'update',
                        'delete',
                        'all',
                    ],
                ]),
            ]),
        ];
        foreach ($arrayParent as $item) {
            $serivce->handleChildPermissions($item);
        }
        $serivce->superAdminHandler();
    }
}

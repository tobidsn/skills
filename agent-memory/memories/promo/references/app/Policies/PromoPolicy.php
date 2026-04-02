<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Promo;
use App\Models\User;

final class PromoPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('admin.access.promos.read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Promo $promo): bool
    {
        return $user->hasPermissionTo('admin.access.promos.read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('admin.access.promos.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Promo $promo): bool
    {
        return $user->hasPermissionTo('admin.access.promos.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Promo $promo): bool
    {
        return $user->hasPermissionTo('admin.access.promos.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Promo $promo): bool
    {
        return $user->hasPermissionTo('admin.access.promos.update');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Promo $promo): bool
    {
        return $user->hasPermissionTo('admin.access.promos.delete');
    }
}

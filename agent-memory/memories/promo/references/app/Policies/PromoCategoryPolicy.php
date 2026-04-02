<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PromoCategory;
use App\Models\User;
use App\Trait\TrackUserVisit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

final class PromoCategoryPolicy
{
    use HandlesAuthorization, TrackUserVisit;

    protected string $pageName = 'PromoCategory';

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        $this->logVisit($user, 'viewAny', $this->pageName);

        return Gate::allows('admin.access.promo-categories') || Gate::allows('admin.access.promo-categories.read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PromoCategory $promoCategory): bool
    {
        $this->logVisit($user, 'view', $this->pageName);

        return Gate::allows('admin.access.promo-categories.read');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (request()->isMethod('get')) {
            $this->logVisit($user, 'create', $this->pageName);
        } else {
            $this->logVisit($user, 'store', $this->pageName);
        }

        return Gate::allows('admin.access.promo-categories.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        if (request()->isMethod('get')) {
            $this->logVisit($user, 'edit', $this->pageName);
        } else {
            $this->logVisit($user, 'update', $this->pageName);
        }

        return Gate::allows('admin.access.promo-categories.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PromoCategory $promoCategory): bool
    {
        $this->logVisit($user, 'delete', $this->pageName);

        return Gate::allows('admin.access.promo-categories.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PromoCategory $promoCategory): bool
    {
        $this->logVisit($user, 'restore', $this->pageName);

        return Gate::allows('admin.access.promo-categories');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PromoCategory $promoCategory): bool
    {
        $this->logVisit($user, 'forceDelete', $this->pageName);

        return Gate::allows('admin.access.promo-categories');
    }
}

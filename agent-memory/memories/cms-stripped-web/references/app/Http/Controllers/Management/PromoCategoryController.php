<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StorePromoCategoryRequest;
use App\Http\Requests\Management\UpdatePromoCategoryRequest;
use App\Http\Resources\CMS\Collection\PromoCategoryCollection;
use App\Models\PromoCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class PromoCategoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PromoCategory::class);

        $request->validate([
            'direction' => ['in:asc,desc'],
            'field' => ['in:name,is_active,sort_order,created_at'],
            'load' => ['nullable', 'numeric'],
        ]);

        $query = PromoCategory::with(['createdBy']);

        $query->when($request->has('q'), function ($query) use ($request) {
            $q = $request->q;

            return $query->where('name', 'LIKE', "%{$q}%")
                ->orWhere('description', 'LIKE', "%{$q}%");
        });

        $query->when($request->has(['field', 'direction']), function ($query) use ($request) {
            return $query->orderBy($request->field, $request->direction);
        });

        $promoCategories = new PromoCategoryCollection($query->paginate($request->load ?? 10));

        return Inertia::render('Management/PromoCategory/Index', compact('promoCategories'));
    }

    public function create()
    {
        $this->authorize('create', PromoCategory::class);

        return Inertia::render('Management/PromoCategory/Create');
    }

    public function store(StorePromoCategoryRequest $request)
    {
        $this->authorize('create', PromoCategory::class);

        $payload = $request->validated();
        $payload['created_by'] = Auth::id();

        PromoCategory::create($payload);

        return to_route('promo-categories.index')->with('message', [
            'success' => true,
            'message' => 'Promo category has been created successfully',
        ]);
    }

    public function edit(PromoCategory $promoCategory)
    {
        $this->authorize('update', $promoCategory);

        return Inertia::render('Management/PromoCategory/Edit', compact('promoCategory'));
    }

    public function update(UpdatePromoCategoryRequest $request, PromoCategory $promoCategory)
    {
        $this->authorize('update', $promoCategory);

        $payload = $request->validated();
        $payload['updated_by'] = Auth::id();

        $promoCategory->update($payload);

        return to_route('promo-categories.index')->with('message', [
            'success' => true,
            'message' => 'Promo category has been updated successfully',
        ]);
    }

    public function delete(PromoCategory $promoCategory)
    {
        $this->authorize('delete', $promoCategory);

        $promoCategory->delete();

        return to_route('promo-categories.index')->with('message', [
            'success' => true,
            'message' => 'Promo category has been deleted successfully',
        ]);
    }

    public function show(PromoCategory $promoCategory)
    {
        $this->authorize('view', $promoCategory);

        $promoCategory->load(['createdBy', 'updatedBy', 'promos']);

        return Inertia::render('Management/PromoCategory/Show', compact('promoCategory'));
    }
}

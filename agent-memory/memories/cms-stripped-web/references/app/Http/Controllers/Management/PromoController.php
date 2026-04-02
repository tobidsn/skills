<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StorePromoRequest;
use App\Http\Requests\Management\UpdatePromoRequest;
use App\Http\Resources\CMS\Collection\PromoCollection;
use App\Models\Promo;
use App\Models\PromoCategory;
use App\Models\Reward;
use App\Services\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PromoController extends Controller
{
    public function __construct(
        private readonly VoucherService $voucherService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Promo::class);

        $request->validate([
            'direction' => ['in:asc,desc'],
            'field' => ['in:title,is_active,start_date,end_date,created_at'],
            'load' => ['nullable', 'numeric'],
        ]);

        $query = Promo::with(['category', 'createdBy']);

        $query->when($request->has('q'), function ($query) use ($request) {
            $q = $request->q;

            return $query->where('title', 'LIKE', "%{$q}%")
                ->orWhere('description', 'LIKE', "%{$q}%")
                ->orWhereHas('category', function ($categoryQuery) use ($q) {
                    $categoryQuery->where('name', 'LIKE', "%{$q}%");
                });
        });

        $query->when($request->has(['field', 'direction']), function ($query) use ($request) {
            return $query->orderBy($request->field, $request->direction);
        });

        $promos = new PromoCollection($query->paginate($request->load ?? 10));

        return Inertia::render('Management/Promo/Index', compact('promos'));
    }

    public function create()
    {
        $this->authorize('create', Promo::class);

        $categories = PromoCategory::select('id', 'name')->get();
        $rewards = Reward::select('id', 'title')->get();

        return Inertia::render('Management/Promo/Create', compact('categories', 'rewards'));
    }

    public function store(StorePromoRequest $request)
    {
        $this->authorize('create', Promo::class);

        $payload = $request->validated();
        $payload['created_by'] = Auth::id();

        Promo::create($payload);

        return to_route('promos.index')->with('message', [
            'success' => true,
            'message' => 'Promo has been created successfully',
        ]);
    }

    public function edit(Promo $promo)
    {
        $this->authorize('update', $promo);

        $categories = PromoCategory::select('id', 'name')->get();
        $rewards = Reward::select('id', 'title')->get();

        return Inertia::render('Management/Promo/Edit', compact('promo', 'categories', 'rewards'));
    }

    public function update(UpdatePromoRequest $request, Promo $promo)
    {
        $this->authorize('update', $promo);

        $payload = $request->validated();
        $payload['updated_by'] = Auth::id();

        $promo->update($payload);

        return to_route('promos.index')->with('message', [
            'success' => true,
            'message' => 'Promo has been updated successfully',
        ]);
    }

    public function delete(Promo $promo)
    {
        $this->authorize('delete', $promo);
        $promo->delete();

        return to_route('promos.index')->with('message', [
            'success' => true,
            'message' => 'Promo has been deleted successfully',
        ]);
    }

    public function show(Promo $promo)
    {
        $this->authorize('view', $promo);

        $promo->load(['category', 'createdBy', 'updatedBy']);

        return Inertia::render('Management/Promo/Show', compact('promo'));
    }

    public function vouchers(Promo $promo): InertiaResponse
    {
        $this->authorize('view', $promo);

        $vouchers = $promo->vouchers()
            ->with(['member'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $stats = $promo->voucher_stats;

        return Inertia::render('Management/Promo/Vouchers', [
            'promo' => $promo,
            'vouchersData' => $vouchers,
            'totalVouchers' => $stats['total'],
            'usedVouchers' => $stats['used'],
            'unusedVouchers' => $stats['unused'],
            'expiredVouchers' => $stats['expired'],
        ]);
    }

    public function importVouchers(Request $request, Promo $promo): RedirectResponse
    {
        $this->authorize('update', $promo);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $this->voucherService->importFromCsv($request->file('file'), $promo);

        return back()->with('message', [
            'success' => true,
            'message' => 'Vouchers imported successfully',
        ]);
    }

    public function downloadVoucherTemplate(Promo $promo): StreamedResponse
    {
        $this->authorize('view', $promo);

        $filename = "voucher_template_{$promo->id}.csv";

        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['promo_id', 'code']);
            fputcsv($file, ['EXAMPLE_PROMO_ID', 'EXAMPLE_CODE_123']);
            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}

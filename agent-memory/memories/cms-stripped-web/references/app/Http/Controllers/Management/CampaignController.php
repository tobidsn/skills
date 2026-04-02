<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreCampaignRequest;
use App\Http\Requests\Management\UpdateCampaignRequest;
use App\Http\Resources\CMS\Collection\CampaignCollection;
use App\Models\Campaign;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Campaign::class);

        $request->validate([
            'direction' => ['in:asc,desc'],
            'field' => ['in:title,required_quantity,voucher_quota,created_at'],
            'load' => ['nullable', 'numeric'],
        ]);

        $query = Campaign::query();

        $query->when($request->has('q'), function ($query) use ($request) {
            $q = $request->q;

            return $query->where('title', 'LIKE', "%{$q}%")
                ->orWhere('description', 'LIKE', "%{$q}%");
        });

        $query->when($request->has(['field', 'direction']), function ($query) use ($request) {
            return $query->orderBy($request->field, $request->direction);
        });

        $campaigns = new CampaignCollection($query->paginate($request->load ?? 10));

        return Inertia::render('Management/Campaign/Index', compact('campaigns'));
    }

    public function create()
    {
        $this->authorize('create', Campaign::class);

        $rewards = Reward::select('id', 'title')->get();

        return Inertia::render('Management/Campaign/Create', compact('rewards'));
    }

    public function store(StoreCampaignRequest $request)
    {
        $this->authorize('create', Campaign::class);

        $payload = $request->validated();
        $payload['created_by'] = Auth::id();

        $campaign = Campaign::create($payload);

        return to_route('campaigns.index')->with('message', [
            'success' => true,
            'message' => 'Campaign has been created successfully',
        ]);
    }

    public function edit(Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        // Load campaign with reward relationship to ensure all data is available
        $campaign->load('reward');

        $rewards = Reward::select('id', 'title')->get();

        return Inertia::render('Management/Campaign/Edit', compact('campaign', 'rewards'));
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $payload = $request->validated();
        $payload['updated_by'] = Auth::id();

        $campaign->update($payload);

        return to_route('campaigns.index')->with('message', [
            'success' => true,
            'message' => 'Campaign has been updated successfully',
        ]);
    }

    public function delete(Campaign $campaign)
    {
        $this->authorize('update', $campaign);
        $campaign->delete();

        return to_route('campaigns.index')->with('message', [
            'success' => true,
            'message' => 'Campaign has been deleted successfully',
        ]);
    }

    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        return Inertia::render('Management/Campaign/Show', compact('campaign'));
    }
}

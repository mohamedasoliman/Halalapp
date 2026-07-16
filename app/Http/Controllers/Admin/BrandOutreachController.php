<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandOutreachBatch;
use App\Models\PrioritisationRequest;
use App\Services\BrandOutreachService;
use Illuminate\Http\Request;
use LogicException;

class BrandOutreachController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');
        $batches = BrandOutreachBatch::with('brand')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'contacts_needed' => Brand::where(function ($query) {
                $query->where('contact_research_status', '!=', 'verified')
                    ->orWhere('contact_type', '!=', 'email');
            })->count(),
            'ready_requests' => PrioritisationRequest::active()->where('status', 'ready_for_outreach')->count(),
            'drafts' => BrandOutreachBatch::where('status', 'draft')->count(),
            'queued' => BrandOutreachBatch::where('status', 'queued')->count(),
            'sent' => BrandOutreachBatch::where('status', 'sent')->count(),
            'failed' => BrandOutreachBatch::where('status', 'failed')->count(),
        ];

        return view('admin.outreach.index', [
            'batches' => $batches,
            'stats' => $stats,
            'outreachEnabled' => config('outreach.enabled'),
        ]);
    }

    public function prepare(BrandOutreachService $service)
    {
        $initial = $service->prepareInitialOutreach();
        $followUps = $service->createFollowUpDrafts();

        return redirect()->route('outreach.index')->with(
            'success',
            "Prepared {$initial['draftsCreated']} initial and {$followUps} follow-up draft(s). "
            ."Created {$initial['createdBrands']} brand research record(s); {$initial['missingContacts']} brand(s) still need a verified contact.",
        );
    }

    public function queue(Request $request, BrandOutreachService $service)
    {
        $validated = $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'integer|exists:brand_outreach_batches,id',
        ]);

        $batches = BrandOutreachBatch::with('brand')
            ->whereIn('id', $validated['batch_ids'])
            ->where('status', 'draft')
            ->get();

        try {
            $queued = $service->queueDrafts($batches);
        } catch (LogicException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->route('outreach.index')->with('success', count($queued).' approved batch(es) queued for throttled delivery.');
    }

    public function cancel(BrandOutreachBatch $batch)
    {
        if (in_array($batch->status, ['draft', 'queued'], true)) {
            $batch->update(['status' => 'cancelled']);
        }

        return redirect()->back()->with('success', "Batch {$batch->reference} cancelled.");
    }

    public function retry(BrandOutreachBatch $batch)
    {
        if ($batch->status === 'failed') {
            $batch->update([
                'status' => 'draft',
                'scheduled_at' => null,
                'failed_at' => null,
                'error' => null,
            ]);
        }

        return redirect()->back()->with('success', "Batch {$batch->reference} returned to draft for review.");
    }
}

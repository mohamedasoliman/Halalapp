<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandOutreachBatch;
use App\Models\PrioritisationRequest;
use App\Services\BrandOutreachService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
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
            'approved' => BrandOutreachBatch::where('status', 'approved')->count(),
            'due_approved' => BrandOutreachBatch::where('status', 'approved')
                ->where('not_before_at', '<=', now())
                ->count(),
            'review_required' => BrandOutreachBatch::where('status', 'review_required')->count(),
            'queued' => BrandOutreachBatch::where('status', 'queued')->count(),
            'sending' => BrandOutreachBatch::where('status', 'sending')->count(),
            'uncertain' => BrandOutreachBatch::where('status', 'uncertain')->count(),
            'sent' => BrandOutreachBatch::where('status', 'sent')->count(),
            'failed' => BrandOutreachBatch::where('status', 'failed')->count(),
        ];

        return view('admin.outreach.index', [
            'batches' => $batches,
            'stats' => $stats,
            'outreachEnabled' => config('outreach.enabled'),
            'outreachTimezone' => config('outreach.timezone', 'Pacific/Auckland'),
            'defaultNotBefore' => now(config('outreach.timezone', 'Pacific/Auckland'))->addDay()->startOfDay()->format('Y-m-d\TH:i'),
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

    public function approve(Request $request, BrandOutreachService $service)
    {
        $validated = $request->validate([
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'integer|exists:brand_outreach_batches,id',
            'not_before' => 'required|string|max:100',
            'approval_reference' => 'required|string|max:500',
        ]);

        try {
            $notBefore = Carbon::parse($validated['not_before'], config('outreach.timezone', 'Pacific/Auckland'));
        } catch (\Throwable) {
            return redirect()->back()->withInput()->with('error', 'The scheduled release date/time is invalid.');
        }

        try {
            $batches = BrandOutreachBatch::with('brand')
                ->whereIn('id', $validated['batch_ids'])
                ->whereIn('status', ['draft', 'approved'])
                ->get();
            if ($batches->count() !== count(array_unique($validated['batch_ids']))) {
                throw new LogicException('One or more selected batches are no longer available for scheduled approval.');
            }
            $approved = $service->approveScheduledBatches(
                $batches,
                $notBefore,
                $validated['approval_reference'],
            );
        } catch (InvalidArgumentException|LogicException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('outreach.index')->with(
            'success',
            count($approved).' batch(es) durably approved for release no earlier than '
                .$notBefore->timezone(config('outreach.timezone', 'Pacific/Auckland'))->format('Y-m-d H:i T')
                .'. No email was queued or sent now.',
        );
    }

    public function cancel(BrandOutreachBatch $batch)
    {
        if (in_array($batch->status, ['draft', 'approved', 'queued'], true)) {
            $batch->update(['status' => 'cancelled']);
        }

        return redirect()->back()->with('success', "Batch {$batch->reference} cancelled.");
    }

    public function retry(BrandOutreachBatch $batch)
    {
        if (in_array($batch->status, ['failed', 'review_required'], true)) {
            $batch->update([
                'status' => 'draft',
                'approved_at' => null,
                'not_before_at' => null,
                'approval_reference' => null,
                'review_required_at' => null,
                'scheduled_at' => null,
                'failed_at' => null,
                'error' => null,
            ]);
        }

        return redirect()->back()->with('success', "Batch {$batch->reference} returned to draft for review.");
    }
}

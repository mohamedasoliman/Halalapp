<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserNotificationEmail;
use App\Models\Brand;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class PrioritisationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $statusFilter = $request->get('status', 'all');

        $query = PrioritisationRequest::query()->orderByDesc('created_at');
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $requests = $query->paginate(50);

        $counts = [
            'all' => PrioritisationRequest::count(),
            'pending' => PrioritisationRequest::where('status', 'pending')->count(),
            'ready_for_outreach' => PrioritisationRequest::where('status', 'ready_for_outreach')->count(),
            'contacted' => PrioritisationRequest::where('status', 'contacted')->count(),
            'ready_for_review' => PrioritisationRequest::where('status', 'ready_for_review')->count(),
            'resolved' => PrioritisationRequest::where('status', 'resolved')->count(),
        ];

        return view('admin.prioritisation.index', compact('requests', 'counts', 'statusFilter'));
    }

    public function show($id)
    {
        $request = PrioritisationRequest::with('watchers')->findOrFail($id);
        $brand = $request->brand();
        $product = Product::where('Barcode', $request->barcode)->first();

        return view('admin.prioritisation.show', compact('request', 'brand', 'product'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,ready_for_outreach,contacted,ready_for_review,resolved',
            'brand_name' => 'nullable|string|max:255',
        ]);

        $prioRequest = PrioritisationRequest::findOrFail($id);
        $prioRequest->update([
            'status' => $request->status,
            'brand_name' => $request->brand_name ?? $prioRequest->brand_name,
        ]);

        return redirect()->back()->with('success', 'Status updated.');
    }

    public function resolve(Request $request, $id)
    {
        $request->validate([
            'halal_status' => 'required|in:0,1',
            'notes' => 'nullable|string|max:5000',
        ]);

        $prioRequest = PrioritisationRequest::findOrFail($id);
        $barcode = $prioRequest->barcode;
        $status = $request->halal_status;
        $notes = $request->notes ?? '';
        $statusLabel = $status === '0' ? 'Halal' : 'Not Halal';

        // 1. Update product
        $product = Product::where('Barcode', $barcode)->first();
        if ($product) {
            $product->update([
                'halal_status' => $status,
                'notes' => $notes,
            ]);
        }

        // 2. Invalidate cache
        Cache::increment('products_cache_version');

        // 3. Resolve ALL requests for this barcode
        $allRequests = PrioritisationRequest::where('barcode', $barcode)
            ->where('status', '!=', 'resolved')
            ->get();

        $watcherEmails = collect();
        foreach ($allRequests as $req) {
            $req->update([
                'status' => 'resolved',
                'resolved_status' => (int) $status,
                'notes' => "Marked {$statusLabel}. {$notes}",
            ]);
            foreach ($req->watchers as $watcher) {
                $watcherEmails->push($watcher->user_email);
            }
        }

        // 4. Notify watchers
        $productName = $product?->product_name ?? $prioRequest->product_name ?? 'Unknown Product';
        foreach ($watcherEmails->unique()->filter() as $email) {
            try {
                Mail::to($email)->send(
                    new UserNotificationEmail('resolved', $productName, $barcode, $status)
                );
            } catch (\Exception $e) {
                // Log but don't fail
            }
        }

        return redirect()->route('prioritisation.index')
            ->with('success', "Resolved: {$productName} marked as {$statusLabel}. {$watcherEmails->unique()->count()} user(s) notified.");
    }
}

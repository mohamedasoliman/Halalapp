<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrioritisationRequest;
use App\Models\ProductModel\Product;
use App\Services\ProductResolutionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

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
            'dead_end' => PrioritisationRequest::where('status', 'dead_end')->count(),
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
            'status' => 'required|in:pending,ready_for_outreach,contacted,ready_for_review,resolved,dead_end',
            'brand_name' => 'nullable|string|max:255',
        ]);

        $prioRequest = PrioritisationRequest::findOrFail($id);
        $prioRequest->update([
            'status' => $request->status,
            'brand_name' => $request->brand_name ?? $prioRequest->brand_name,
        ]);

        return redirect()->back()->with('success', 'Status updated.');
    }

    public function resolve(Request $request, $id, ProductResolutionService $resolutions)
    {
        $request->validate([
            'halal_status' => 'required|in:0,1',
            'notes' => 'nullable|string|max:5000',
            'proof_path' => 'nullable|string|max:5000',
            'brand_communication_id' => 'nullable|integer|exists:brand_communications,id',
        ]);

        $prioRequest = PrioritisationRequest::findOrFail($id);
        try {
            $result = $resolutions->resolve(
                (string) $prioRequest->barcode,
                (string) $request->halal_status,
                (string) ($request->notes ?? ''),
                $request->proof_path,
                $request->brand_communication_id ? (int) $request->brand_communication_id : null,
            );
        } catch (ModelNotFoundException) {
            return redirect()->back()->withErrors([
                'halal_status' => 'Exact-barcode product was not found. Nothing was resolved or emailed.',
            ]);
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withErrors([
                'halal_status' => $exception->getMessage(),
            ]);
        }

        $delivery = $result['delivery'];

        return redirect()->route('prioritisation.index')
            ->with('success', sprintf(
                'Resolved %s. Notifications sent: %d; failed: %d; uncertain: %d; sending: %d. Event: %s',
                $result['product_name'],
                $delivery['sent'],
                $delivery['failed'],
                $delivery['uncertain'],
                $delivery['sending'],
                $result['event_reference'],
            ));
    }

    public function researchUnknown()
    {
        $requests = PrioritisationRequest::where(function ($q) {
            $q->whereNull('product_name')->orWhere('product_name', '');
        })->whereNotIn('status', ['resolved', 'dead_end'])->get();

        if ($requests->isEmpty()) {
            return redirect()->back()->with('success', 'No unknown products to research.');
        }

        $found = 0;
        $failed = 0;

        foreach ($requests as $request) {
            try {
                $response = Http::timeout(8)
                    ->get("https://world.openfoodfacts.org/api/v2/product/{$request->barcode}.json", [
                        'fields' => 'product_name,brands',
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (($data['status'] ?? 0) == 1) {
                        $product = $data['product'] ?? [];
                        $updates = [];

                        if (! empty($product['product_name'])) {
                            $updates['product_name'] = $product['product_name'];
                        }
                        if (! empty($product['brands']) && empty($request->brand_name)) {
                            $updates['brand_name'] = $product['brands'];
                        }

                        if (! empty($updates)) {
                            $request->update($updates);

                            // Also update the product in DB if it exists
                            if (! empty($updates['product_name'])) {
                                $dbProduct = Product::where('Barcode', $request->barcode)->first();
                                if ($dbProduct && empty($dbProduct->product_name)) {
                                    $dbProduct->update(['product_name' => $updates['product_name']]);
                                }
                            }

                            $found++;
                        } else {
                            $failed++;
                        }
                    } else {
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::debug("OFF lookup failed for {$request->barcode}: {$e->getMessage()}");
            }
        }

        return redirect()->back()
            ->with('success', "Research complete. Found: {$found}, Not found: {$failed} (out of {$requests->count()} unknown products).");
    }
}

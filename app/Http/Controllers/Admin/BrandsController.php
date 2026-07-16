<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\PrioritisationRequest;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $brands = Brand::withCount([
            'communications',
            'prioritisationRequests as active_requests_count' => fn ($query) => $query->active(),
        ])
            ->when($request->input('research') === 'pending', fn ($query) => $query->where(function ($contacts) {
                $contacts->where('contact_research_status', '!=', 'verified')
                    ->orWhere('contact_type', '!=', 'email');
            }))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->input('search').'%';
                $query->where(fn ($nested) => $nested->where('name', 'like', $search)->orWhere('email', 'like', $search));
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.brands.index', compact('brands'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'email' => 'nullable|string|max:255',
            'contact_type' => 'required|in:email,form',
            'contact_source' => 'nullable|string|max:500',
            'response' => 'nullable|in:halal,not_halal,partial',
            'response_scope' => 'nullable|in:blanket,partial',
            'notes' => 'nullable|string|max:5000',
        ]);

        Brand::create(array_merge(
            $request->only(['name', 'email', 'contact_type', 'contact_source', 'response', 'response_scope', 'notes']),
            $this->contactResearchFields($request),
        ));

        return redirect()->back()->with('success', 'Brand added.');
    }

    public function edit($id)
    {
        $brand = Brand::findOrFail($id);
        $requestCount = PrioritisationRequest::where('brand_name', $brand->name)->count();

        return view('admin.brands.edit', compact('brand', 'requestCount'));
    }

    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,'.$id,
            'email' => 'nullable|string|max:255',
            'contact_type' => 'required|in:email,form',
            'contact_source' => 'nullable|string|max:500',
            'response' => 'nullable|in:halal,not_halal,partial',
            'response_scope' => 'nullable|in:blanket,partial',
            'notes' => 'nullable|string|max:5000',
        ]);

        $oldName = $brand->name;
        $brand->update(array_merge(
            $request->only(['name', 'email', 'contact_type', 'contact_source', 'response', 'response_scope', 'notes']),
            $this->contactResearchFields($request),
        ));

        // Update brand_name in requests if name changed
        if ($oldName !== $brand->name) {
            PrioritisationRequest::where('brand_name', $oldName)->update(['brand_name' => $brand->name]);
        }

        return redirect()->route('brands.index')->with('success', 'Brand updated.');
    }

    public function destroy($id)
    {
        Brand::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Brand deleted.');
    }

    private function contactResearchFields(Request $request): array
    {
        $isEmail = $request->input('contact_type') === 'email';
        $hasUsableContact = $isEmail
            ? filter_var($request->input('email'), FILTER_VALIDATE_EMAIL) !== false
            : filter_var($request->input('email'), FILTER_VALIDATE_URL) !== false;

        return [
            'contact_research_status' => $hasUsableContact ? ($isEmail ? 'verified' : 'manual') : 'pending',
            'contact_verified_at' => $hasUsableContact ? now() : null,
        ];
    }
}

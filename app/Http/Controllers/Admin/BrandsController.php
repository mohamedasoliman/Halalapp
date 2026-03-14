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

    public function index()
    {
        $brands = Brand::withCount('communications')
            ->orderBy('name')
            ->paginate(50);

        return view('admin.brands.index', compact('brands'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'email' => 'nullable|string|max:255',
            'contact_type' => 'required|in:email,form',
            'response' => 'nullable|in:halal,not_halal,partial',
            'response_scope' => 'nullable|in:blanket,partial',
            'notes' => 'nullable|string|max:5000',
        ]);

        Brand::create($request->only(['name', 'email', 'contact_type', 'response', 'response_scope', 'notes']));

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
            'name' => 'required|string|max:255|unique:brands,name,' . $id,
            'email' => 'nullable|string|max:255',
            'contact_type' => 'required|in:email,form',
            'response' => 'nullable|in:halal,not_halal,partial',
            'response_scope' => 'nullable|in:blanket,partial',
            'notes' => 'nullable|string|max:5000',
        ]);

        $oldName = $brand->name;
        $brand->update($request->only(['name', 'email', 'contact_type', 'response', 'response_scope', 'notes']));

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
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class RestaurantTierController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    private function jsonPath(): string
    {
        return public_path('data/HalalRestaurantsList.json');
    }

    private function loadRestaurants(): array
    {
        $path = $this->jsonPath();
        if (!File::exists($path)) {
            return [];
        }
        return json_decode(File::get($path), true) ?? [];
    }

    private function saveRestaurants(array $restaurants): void
    {
        File::put($this->jsonPath(), json_encode($restaurants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Also sync to public_html
        $publicHtmlPath = '/home5/halalapp/public_html/data/HalalRestaurantsList.json';
        if (is_dir('/home5/halalapp/public_html/data')) {
            @copy($this->jsonPath(), $publicHtmlPath);
        }
    }

    public function index(Request $request)
    {
        $restaurants = $this->loadRestaurants();
        $tierFilter = $request->get('tier', 'all');
        $search = $request->get('search', '');

        // Add index for identification
        foreach ($restaurants as $i => &$r) {
            $r['_index'] = $i;
        }
        unset($r);

        // Apply search filter
        if (!empty($search)) {
            $searchLower = strtolower($search);
            $restaurants = array_filter($restaurants, function ($r) use ($searchLower) {
                return str_contains(strtolower($r['NAME'] ?? ''), $searchLower)
                    || str_contains(strtolower($r['CATEGORY'] ?? ''), $searchLower)
                    || str_contains(strtolower($r['ADDRESS'] ?? ''), $searchLower);
            });
        }

        if ($tierFilter !== 'all') {
            $restaurants = array_filter($restaurants, function ($r) use ($tierFilter) {
                $tier = $r['membership_tier'] ?? 'free';
                if ($tierFilter === 'free') {
                    return empty($tier) || $tier === 'free' || $tier === 'none';
                }
                return $tier === $tierFilter;
            });
        }

        $counts = ['all' => 0, 'free' => 0, 'verified' => 0, 'featured' => 0, 'premium' => 0];
        foreach ($this->loadRestaurants() as $r) {
            $counts['all']++;
            $tier = $r['membership_tier'] ?? 'free';
            if (empty($tier) || $tier === 'none' || $tier === 'free') {
                $counts['free']++;
            } elseif (isset($counts[$tier])) {
                $counts[$tier]++;
            }
        }

        return view('admin.restaurant_tiers.index', compact('restaurants', 'counts', 'tierFilter', 'search'));
    }

    public function update(Request $request, int $index)
    {
        $request->validate([
            'membership_tier' => 'required|in:free,verified,featured,premium',
            'is_verified' => 'required|in:0,1',
            'menu_url' => 'nullable|string|max:500',
            'images.*' => 'nullable|image|max:5120',
            'logo' => 'nullable|image|max:5120',
            'name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'certified' => 'nullable|string|max:255',
            'monday' => 'nullable|string|max:100',
            'tuesday' => 'nullable|string|max:100',
            'wednesday' => 'nullable|string|max:100',
            'thursday' => 'nullable|string|max:100',
            'friday' => 'nullable|string|max:100',
            'saturday' => 'nullable|string|max:100',
            'sunday' => 'nullable|string|max:100',
        ]);

        $restaurants = $this->loadRestaurants();

        if (!isset($restaurants[$index])) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        // Update basic fields
        if ($request->filled('name')) {
            $restaurants[$index]['NAME'] = $request->name;
        }
        if ($request->has('category')) {
            $restaurants[$index]['CATEGORY'] = $request->category ?? '';
        }
        if ($request->has('address')) {
            $restaurants[$index]['ADDRESS'] = $request->address ?? '';
        }
        if ($request->has('phone')) {
            $restaurants[$index]['PHONENUMBER'] = $request->phone ?? '';
        }
        if ($request->has('website')) {
            $restaurants[$index]['WEBSITEURL'] = $request->website ?? '';
        }
        if ($request->has('latitude')) {
            $restaurants[$index]['Latitude'] = $request->latitude !== null ? (float) $request->latitude : '';
        }
        if ($request->has('longitude')) {
            $restaurants[$index]['Longitude'] = $request->longitude !== null ? (float) $request->longitude : '';
        }
        if ($request->has('certified')) {
            $restaurants[$index]['Certified'] = $request->certified ?? '';
        }

        // Update opening hours
        $days = ['monday' => 'MONDAY', 'tuesday' => 'TUESDAY', 'wednesday' => 'WEDNESDAY', 'thursday' => 'THURSDAY', 'friday' => 'FRIDAY', 'saturday' => 'SATURDAY', 'sunday' => 'SUNDAY'];
        foreach ($days as $field => $key) {
            if ($request->has($field)) {
                $restaurants[$index][$key] = $request->$field ?? '';
            }
        }

        $tier = $request->membership_tier;
        $restaurants[$index]['membership_tier'] = $tier === 'free' ? '' : $tier;
        $restaurants[$index]['is_verified'] = (int) $request->is_verified;

        if ($request->menu_url) {
            $restaurants[$index]['menu_url'] = $request->menu_url;
        } else {
            unset($restaurants[$index]['menu_url']);
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoUrl = $this->uploadImage($request->file('logo'), $restaurants[$index]['NAME'] ?? 'restaurant', 'logo');
            if ($logoUrl) {
                $restaurants[$index]['LOGOURL'] = $logoUrl;
            }
        }

        // Handle image uploads (up to 6)
        if ($request->hasFile('images')) {
            $imgNum = 1;
            foreach ($request->file('images') as $image) {
                if ($imgNum > 6) break;
                $imgUrl = $this->uploadImage($image, $restaurants[$index]['NAME'] ?? 'restaurant', "img{$imgNum}");
                if ($imgUrl) {
                    $restaurants[$index]["Image_{$imgNum}"] = $imgUrl;
                }
                $imgNum++;
            }
        }

        $this->saveRestaurants($restaurants);

        $name = $restaurants[$index]['NAME'] ?? 'Restaurant';
        return redirect()->back()->with('success', "{$name} updated.");
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'certified' => 'nullable|string|max:255',
            'membership_tier' => 'required|in:free,verified,featured,premium',
            'is_verified' => 'required|in:0,1',
            'menu_url' => 'nullable|string|max:500',
            'monday' => 'nullable|string|max:100',
            'tuesday' => 'nullable|string|max:100',
            'wednesday' => 'nullable|string|max:100',
            'thursday' => 'nullable|string|max:100',
            'friday' => 'nullable|string|max:100',
            'saturday' => 'nullable|string|max:100',
            'sunday' => 'nullable|string|max:100',
            'logo' => 'nullable|image|max:5120',
        ]);

        $restaurants = $this->loadRestaurants();

        $tier = $request->membership_tier;
        $restaurant = [
            'CATEGORY' => $request->category ?? '',
            'NAME' => $request->name,
            'ADDRESS' => $request->address ?? '',
            'Latitude' => $request->latitude !== null ? (float) $request->latitude : '',
            'Longitude' => $request->longitude !== null ? (float) $request->longitude : '',
            'PHONENUMBER' => $request->phone ?? '',
            'MONDAY' => $request->monday ?? '',
            'TUESDAY' => $request->tuesday ?? '',
            'WEDNESDAY' => $request->wednesday ?? '',
            'THURSDAY' => $request->thursday ?? '',
            'FRIDAY' => $request->friday ?? '',
            'SATURDAY' => $request->saturday ?? '',
            'SUNDAY' => $request->sunday ?? '',
            'WEBSITEURL' => $request->website ?? '',
            'LOGOURL' => '',
            'Certified' => $request->certified ?? '',
            'membership_tier' => $tier === 'free' ? '' : $tier,
            'is_verified' => (int) $request->is_verified,
        ];

        if ($request->menu_url) {
            $restaurant['menu_url'] = $request->menu_url;
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoUrl = $this->uploadImage($request->file('logo'), $request->name, 'logo');
            if ($logoUrl) {
                $restaurant['LOGOURL'] = $logoUrl;
            }
        }

        $restaurants[] = $restaurant;
        $this->saveRestaurants($restaurants);

        return redirect()->back()->with('success', "{$request->name} added successfully.");
    }

    public function destroy(int $index)
    {
        $restaurants = $this->loadRestaurants();

        if (!isset($restaurants[$index])) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        $name = $restaurants[$index]['NAME'] ?? 'Restaurant';
        array_splice($restaurants, $index, 1);
        $this->saveRestaurants($restaurants);

        return redirect()->back()->with('success', "{$name} deleted successfully.");
    }

    private function uploadImage($file, string $restaurantName, string $suffix): ?string
    {
        try {
            $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($restaurantName)));
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = "{$slug}_{$suffix}.{$ext}";

            // Store in public upload directory
            $uploadDir = public_path('upload/resturant');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $file->move($uploadDir, $filename);

            // Also copy to public_html on server
            $serverDir = '/home5/halalapp/public_html/upload/resturant';
            if (is_dir('/home5/halalapp/public_html/upload')) {
                if (!is_dir($serverDir)) {
                    @mkdir($serverDir, 0775, true);
                }
                @copy("{$uploadDir}/{$filename}", "{$serverDir}/{$filename}");
            }

            return "https://halalapp.info/upload/resturant/{$filename}";
        } catch (\Exception $e) {
            return null;
        }
    }
}

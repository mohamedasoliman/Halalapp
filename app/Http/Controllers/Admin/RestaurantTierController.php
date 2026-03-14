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

        // Add index for identification
        foreach ($restaurants as $i => &$r) {
            $r['_index'] = $i;
        }
        unset($r);

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

        return view('admin.restaurant_tiers.index', compact('restaurants', 'counts', 'tierFilter'));
    }

    public function update(Request $request, int $index)
    {
        $request->validate([
            'membership_tier' => 'required|in:free,verified,featured,premium',
            'is_verified' => 'required|in:0,1',
            'menu_url' => 'nullable|string|max:500',
        ]);

        $restaurants = $this->loadRestaurants();

        if (!isset($restaurants[$index])) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        $tier = $request->membership_tier;
        $restaurants[$index]['membership_tier'] = $tier === 'free' ? '' : $tier;
        $restaurants[$index]['is_verified'] = (int) $request->is_verified;

        if ($request->menu_url) {
            $restaurants[$index]['menu_url'] = $request->menu_url;
        } else {
            unset($restaurants[$index]['menu_url']);
        }

        $this->saveRestaurants($restaurants);

        $name = $restaurants[$index]['NAME'] ?? 'Restaurant';
        return redirect()->back()->with('success', "{$name} updated to {$tier} tier.");
    }
}

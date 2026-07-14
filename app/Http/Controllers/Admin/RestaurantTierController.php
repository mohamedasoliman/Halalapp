<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MembershipDeal;
use App\Support\MembershipTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

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
        if (! File::exists($path)) {
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
        $allRestaurants = $this->loadRestaurants();
        $requestedTier = strtolower(trim((string) $request->get('tier', 'all')));
        $tierFilter = in_array($requestedTier, MembershipTier::VALUES, true)
            ? $requestedTier
            : 'all';
        $search = trim((string) $request->get('search', ''));
        $counts = array_fill_keys(['all', ...MembershipTier::VALUES], 0);
        $restaurants = [];

        foreach ($allRestaurants as $index => $restaurant) {
            $tier = $this->restaurantTier($restaurant);
            $counts['all']++;
            $counts[$tier]++;

            if ($tierFilter !== 'all' && $tier !== $tierFilter) {
                continue;
            }

            if ($search !== '' && ! $this->matchesSearch($restaurant, $search)) {
                continue;
            }

            $restaurant['_index'] = $index;
            $restaurant['_tier'] = $tier;
            $restaurants[] = $restaurant;
        }

        $tierOptions = $this->tierOptions();

        return view('admin.restaurant_tiers.index', compact(
            'restaurants',
            'counts',
            'tierFilter',
            'tierOptions',
            'search'
        ));
    }

    public function update(Request $request, int $index)
    {
        $request->validate([
            'tier' => ['required', Rule::in(MembershipTier::VALUES)],
            'menu_url' => 'nullable|url|max:500',
            'enquiry_email' => 'nullable|email|max:255',
            'deal_title' => 'nullable|string|max:120',
            'deal_description' => 'nullable|string|max:500',
            'deal_code' => 'nullable|string|max:50',
            'deal_expiry' => 'nullable|date',
            'deals' => 'nullable|array|max:5',
            'deals.*' => 'array',
            'deals.*.title' => 'nullable|string|max:120',
            'deals.*.description' => 'nullable|string|max:500',
            'deals.*.code' => 'nullable|string|max:50',
            'deals.*.expiry' => 'nullable|date',
            'images' => 'nullable|array|max:5',
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
            'business_status' => ['required', Rule::in([
                'OPERATIONAL',
                'CLOSED_TEMPORARILY',
                'UNKNOWN',
                'REVIEW_REQUIRED',
            ])],
            'status_note' => 'nullable|string|max:500',
            'last_reviewed_at' => 'nullable|date',
            'monday' => 'nullable|string|max:100',
            'tuesday' => 'nullable|string|max:100',
            'wednesday' => 'nullable|string|max:100',
            'thursday' => 'nullable|string|max:100',
            'friday' => 'nullable|string|max:100',
            'saturday' => 'nullable|string|max:100',
            'sunday' => 'nullable|string|max:100',
        ]);

        $restaurants = $this->loadRestaurants();

        if (! isset($restaurants[$index])) {
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
        $restaurants[$index]['BUSINESS_STATUS'] = $request->business_status;
        $restaurants[$index]['STATUS_NOTE'] = trim((string) $request->status_note);
        $restaurants[$index]['LAST_REVIEWED_AT'] = $request->last_reviewed_at ?? '';

        // Update opening hours
        $days = ['monday' => 'MONDAY', 'tuesday' => 'TUESDAY', 'wednesday' => 'WEDNESDAY', 'thursday' => 'THURSDAY', 'friday' => 'FRIDAY', 'saturday' => 'SATURDAY', 'sunday' => 'SUNDAY'];
        foreach ($days as $field => $key) {
            if ($request->has($field)) {
                $restaurants[$index][$key] = $request->$field ?? '';
            }
        }

        $tier = MembershipTier::normalise($request->tier);
        $this->applyMembershipTier($restaurants[$index], $tier);

        $this->applyMenuField($restaurants[$index], $request, $tier);
        $this->applyDealFields($restaurants[$index], $request, $tier);
        $this->applyEnquiryField($restaurants[$index], $request, $tier);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoUrl = $this->uploadImage($request->file('logo'), $restaurants[$index]['NAME'] ?? 'restaurant', 'logo');
            if ($logoUrl) {
                $restaurants[$index]['LOGOURL'] = $logoUrl;
            }
        }

        $this->applyUploadedImages($restaurants[$index], $request, $tier);
        $this->enforceGalleryLimit($restaurants[$index], $tier);

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
            'business_status' => ['required', Rule::in([
                'OPERATIONAL',
                'CLOSED_TEMPORARILY',
                'UNKNOWN',
                'REVIEW_REQUIRED',
            ])],
            'status_note' => 'nullable|string|max:500',
            'last_reviewed_at' => 'nullable|date',
            'tier' => ['required', Rule::in(MembershipTier::VALUES)],
            'menu_url' => 'nullable|url|max:500',
            'enquiry_email' => 'nullable|email|max:255',
            'deal_title' => 'nullable|string|max:120',
            'deal_description' => 'nullable|string|max:500',
            'deal_code' => 'nullable|string|max:50',
            'deal_expiry' => 'nullable|date',
            'deals' => 'nullable|array|max:5',
            'deals.*' => 'array',
            'deals.*.title' => 'nullable|string|max:120',
            'deals.*.description' => 'nullable|string|max:500',
            'deals.*.code' => 'nullable|string|max:50',
            'deals.*.expiry' => 'nullable|date',
            'images' => 'nullable|array|max:5',
            'images.*' => 'nullable|image|max:5120',
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

        $tier = MembershipTier::normalise($request->tier);
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
            'BUSINESS_STATUS' => $request->business_status,
            'STATUS_NOTE' => trim((string) $request->status_note),
            'LAST_REVIEWED_AT' => $request->last_reviewed_at ?? '',
            'Tier' => MembershipTier::label($tier),
            'membership_tier' => MembershipTier::legacyRestaurantValue($tier),
        ];

        $this->applyMenuField($restaurant, $request, $tier);
        $this->applyDealFields($restaurant, $request, $tier);
        $this->applyEnquiryField($restaurant, $request, $tier);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoUrl = $this->uploadImage($request->file('logo'), $request->name, 'logo');
            if ($logoUrl) {
                $restaurant['LOGOURL'] = $logoUrl;
            }
        }

        $this->applyUploadedImages($restaurant, $request, $tier);
        $this->enforceGalleryLimit($restaurant, $tier);

        $restaurants[] = $restaurant;
        $this->saveRestaurants($restaurants);

        return redirect()->back()->with('success', "{$request->name} added successfully.");
    }

    public function destroy(int $index)
    {
        $restaurants = $this->loadRestaurants();

        if (! isset($restaurants[$index])) {
            return redirect()->back()->with('error', 'Restaurant not found.');
        }

        $name = $restaurants[$index]['NAME'] ?? 'Restaurant';
        array_splice($restaurants, $index, 1);
        $this->saveRestaurants($restaurants);

        return redirect()->back()->with('success', "{$name} deleted successfully.");
    }

    private function restaurantTier(array $restaurant): string
    {
        $canonicalTier = trim((string) ($restaurant['Tier'] ?? ''));

        return MembershipTier::normalise(
            $canonicalTier !== ''
                ? $canonicalTier
                : ($restaurant['membership_tier'] ?? null)
        );
    }

    private function applyMembershipTier(array &$restaurant, string $tier): void
    {
        $restaurant['Tier'] = MembershipTier::label($tier);
        $restaurant['membership_tier'] = MembershipTier::legacyRestaurantValue($tier);
        unset($restaurant['is_verified']);
    }

    private function matchesSearch(array $restaurant, string $search): bool
    {
        $haystack = implode(' ', [
            $restaurant['NAME'] ?? '',
            $restaurant['CATEGORY'] ?? '',
            $restaurant['ADDRESS'] ?? '',
        ]);

        return str_contains(mb_strtolower($haystack), mb_strtolower($search));
    }

    private function tierOptions(): array
    {
        $options = [];

        foreach (MembershipTier::VALUES as $tier) {
            $options[$tier] = [
                'label' => MembershipTier::label($tier),
                'weekly_price' => MembershipTier::weeklyPrice($tier),
                'gallery_limit' => MembershipTier::galleryLimit($tier),
                'deal_limit' => MembershipTier::dealLimit($tier),
                'can_publish_menu' => MembershipTier::canPublishMenu($tier),
                'can_publish_deal' => MembershipTier::canPublishDeal($tier),
                'can_receive_enquiries' => MembershipTier::canReceiveEnquiries($tier),
            ];
        }

        return $options;
    }

    private function applyUploadedImages(array &$restaurant, Request $request, string $tier): void
    {
        $limit = MembershipTier::galleryLimit($tier);
        if ($limit === 0 || ! $request->hasFile('images')) {
            return;
        }

        foreach (array_slice($request->file('images'), 0, $limit) as $position => $image) {
            $imageNumber = $position + 1;
            $imageUrl = $this->uploadImage(
                $image,
                $restaurant['NAME'] ?? 'restaurant',
                "img{$imageNumber}"
            );

            if ($imageUrl) {
                $restaurant["Image_{$imageNumber}"] = $imageUrl;
            }
        }
    }

    private function applyMenuField(array &$restaurant, Request $request, string $tier): void
    {
        if (MembershipTier::canPublishMenu($tier) && $request->filled('menu_url')) {
            $restaurant['menu_url'] = trim((string) $request->menu_url);
            unset($restaurant['MenuUrl']);

            return;
        }

        unset($restaurant['menu_url'], $restaurant['MenuUrl']);
    }

    private function applyDealFields(array &$restaurant, Request $request, string $tier): void
    {
        MembershipDeal::applyToRecord(
            $restaurant,
            MembershipDeal::fromRequest($request->all(), $tier),
            $tier
        );
    }

    private function applyEnquiryField(array &$restaurant, Request $request, string $tier): void
    {
        if (MembershipTier::canReceiveEnquiries($tier) && $request->filled('enquiry_email')) {
            $restaurant['EnquiryEmail'] = trim((string) $request->enquiry_email);
        } else {
            unset($restaurant['EnquiryEmail'], $restaurant['enquiry_email']);
        }
    }

    private function enforceGalleryLimit(array &$restaurant, string $tier): void
    {
        $limit = MembershipTier::galleryLimit($tier);

        foreach (array_keys($restaurant) as $key) {
            if (preg_match('/^Image_(\d+)$/', $key, $matches) !== 1) {
                continue;
            }

            if ((int) $matches[1] > $limit) {
                unset($restaurant[$key]);
            }
        }
    }

    private function uploadImage($file, string $restaurantName, string $suffix): ?string
    {
        try {
            $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($restaurantName)));
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = "{$slug}_{$suffix}.{$ext}";

            // Store in public upload directory
            $uploadDir = public_path('upload/resturant');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $file->move($uploadDir, $filename);

            // Also copy to public_html on server
            $serverDir = '/home5/halalapp/public_html/upload/resturant';
            if (is_dir('/home5/halalapp/public_html/upload')) {
                if (! is_dir($serverDir)) {
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

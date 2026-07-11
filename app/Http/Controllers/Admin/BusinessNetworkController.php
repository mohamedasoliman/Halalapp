<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class BusinessNetworkController extends Controller
{
    private const TIERS = ['community', 'starter', 'growth', 'premium'];

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $allBusinesses = $this->loadBusinesses();
        $tierFilter = $request->string('tier', 'all')->toString();
        $search = trim($request->string('search')->toString());
        $counts = array_fill_keys(['all', ...self::TIERS], 0);

        foreach ($allBusinesses as $business) {
            $counts['all']++;
            $counts[$this->normaliseTier($business['Tier'] ?? null)]++;
        }

        $businesses = [];
        foreach ($allBusinesses as $index => $business) {
            $business['_index'] = $index;
            $business['_tier'] = $this->normaliseTier($business['Tier'] ?? null);

            if ($tierFilter !== 'all' && $business['_tier'] !== $tierFilter) {
                continue;
            }

            if ($search !== '' && ! $this->matchesSearch($business, $search)) {
                continue;
            }

            $businesses[] = $business;
        }

        usort($businesses, function (array $a, array $b): int {
            $order = ['premium' => 0, 'growth' => 1, 'starter' => 2, 'community' => 3];
            $tierComparison = $order[$a['_tier']] <=> $order[$b['_tier']];

            return $tierComparison !== 0
                ? $tierComparison
                : strcasecmp($a['Name'] ?? '', $b['Name'] ?? '');
        });

        return view('admin.business_network.index', compact(
            'businesses',
            'counts',
            'tierFilter',
            'search'
        ));
    }

    public function create()
    {
        return view('admin.business_network.form', [
            'business' => [],
            'index' => null,
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateBusiness($request, true);
        $businesses = $this->loadBusinesses();
        $businesses[] = $this->buildBusiness($validated, $request);
        $this->saveBusinesses($businesses);

        return redirect()
            ->route('business-network.index')
            ->with('success', "{$validated['name']} added to the Muslim Business Network.");
    }

    public function edit(int $index)
    {
        $businesses = $this->loadBusinesses();
        abort_unless(isset($businesses[$index]), 404);

        return view('admin.business_network.form', [
            'business' => $businesses[$index],
            'index' => $index,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, int $index)
    {
        $businesses = $this->loadBusinesses();
        abort_unless(isset($businesses[$index]), 404);

        $validated = $this->validateBusiness($request);
        $businesses[$index] = $this->buildBusiness(
            $validated,
            $request,
            $businesses[$index]
        );
        $this->saveBusinesses($businesses);

        return redirect()
            ->route('business-network.index')
            ->with('success', "{$validated['name']} updated successfully.");
    }

    public function destroy(int $index)
    {
        $businesses = $this->loadBusinesses();
        abort_unless(isset($businesses[$index]), 404);

        $name = $businesses[$index]['Name'] ?? 'Business';
        array_splice($businesses, $index, 1);
        $this->saveBusinesses($businesses);

        return redirect()
            ->route('business-network.index')
            ->with('success', "{$name} removed from the directory.");
    }

    private function validateBusiness(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'sub_category' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:500'],
            'instagram' => ['nullable', 'url', 'max:500'],
            'facebook' => ['nullable', 'url', 'max:500'],
            'bio' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:3000'],
            'business_type' => ['required', Rule::in([
                'muslim_owned',
                'halal_certified',
                'muslim_friendly',
                'community_organisation',
            ])],
            'tier' => ['required', Rule::in(self::TIERS)],
            'verified' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'feature_in_carousel' => ['required', 'boolean'],
            'permission_granted' => [$creating ? 'accepted' : 'nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['image', 'max:5120'],
            'clear_gallery' => ['nullable', 'boolean'],
            'menu_url' => ['nullable', 'url', 'max:500'],
            'deal_title' => ['nullable', 'string', 'max:120'],
            'deal_description' => ['nullable', 'string', 'max:500'],
            'deal_code' => ['nullable', 'string', 'max:50'],
            'deal_expiry' => ['nullable', 'date'],
            'monday' => ['nullable', 'string', 'max:100'],
            'tuesday' => ['nullable', 'string', 'max:100'],
            'wednesday' => ['nullable', 'string', 'max:100'],
            'thursday' => ['nullable', 'string', 'max:100'],
            'friday' => ['nullable', 'string', 'max:100'],
            'saturday' => ['nullable', 'string', 'max:100'],
            'sunday' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function buildBusiness(array $data, Request $request, array $existing = []): array
    {
        $business = $existing;
        $business['Name'] = $data['name'];
        $business['Category'] = $data['category'];
        $business['SubCategory'] = $data['sub_category'];
        $business['Address'] = $data['address'] ?? '';
        $business['Phone'] = $data['phone'] ?? '';
        $business['Email'] = $data['email'] ?? '';
        $business['website'] = $data['website'] ?? '';
        $business['Instagram'] = $data['instagram'] ?? '';
        $business['Facebook'] = $data['facebook'] ?? '';
        $business['Bio'] = $data['bio'] ?? '';
        $business['Desc'] = $data['description'] ?? '';
        $business['BusinessType'] = $data['business_type'];
        $business['Tier'] = ucfirst($data['tier']);
        $business['Verified'] = (bool) $data['verified'];
        $business['IsActive'] = (bool) $data['is_active'];
        $business['FeatureInCarousel'] = (bool) $data['feature_in_carousel'];
        $business['PermissionGranted'] = $request->boolean('permission_granted')
            || (bool) ($existing['PermissionGranted'] ?? false);
        $business['MenuUrl'] = $data['menu_url'] ?? '';
        $business['DealTitle'] = $data['deal_title'] ?? '';
        $business['DealDescription'] = $data['deal_description'] ?? '';
        $business['DealCode'] = $data['deal_code'] ?? '';
        $business['DealExpiry'] = $data['deal_expiry'] ?? '';

        $business['hours'] = [
            'Monday' => $data['monday'] ?? '',
            'Tuesday' => $data['tuesday'] ?? '',
            'Wednesday' => $data['wednesday'] ?? '',
            'Thursday' => $data['thursday'] ?? '',
            'Friday' => $data['friday'] ?? '',
            'Saturday' => $data['saturday'] ?? '',
            'Sunday' => $data['sunday'] ?? '',
        ];

        if ($request->hasFile('logo')) {
            $business['Logo'] = $this->uploadImage(
                $request->file('logo'),
                $data['name'],
                'logo'
            );
        } elseif (! array_key_exists('Logo', $business)) {
            $business['Logo'] = '';
        }

        $galleryLimit = match ($data['tier']) {
            'growth' => 3,
            'premium' => 5,
            default => 0,
        };

        $images = $request->boolean('clear_gallery')
            ? []
            : array_values(array_filter($existing['images'] ?? []));

        foreach ($request->file('images', []) as $position => $image) {
            if (count($images) >= $galleryLimit) {
                break;
            }

            $images[] = $this->uploadImage(
                $image,
                $data['name'],
                'photo_'.($position + 1)
            );
        }

        if ($galleryLimit > 0) {
            $business['images'] = array_slice(array_values(array_filter($images)), 0, $galleryLimit);
        } else {
            unset($business['images']);
        }

        return $business;
    }

    private function matchesSearch(array $business, string $search): bool
    {
        $haystack = implode(' ', [
            $business['Name'] ?? '',
            $business['Category'] ?? '',
            $business['SubCategory'] ?? '',
            $business['Address'] ?? '',
        ]);

        return str_contains(mb_strtolower($haystack), mb_strtolower($search));
    }

    private function normaliseTier(mixed $tier): string
    {
        return match (strtolower(trim((string) $tier))) {
            'premium', 'gold' => 'premium',
            'growth', 'silver', 'featured' => 'growth',
            'starter', 'verified' => 'starter',
            default => 'community',
        };
    }

    private function categories(): array
    {
        $categories = array_map(
            fn (array $business): string => trim($business['Category'] ?? ''),
            $this->loadBusinesses()
        );
        $categories = array_values(array_unique(array_filter($categories)));
        natcasesort($categories);

        return array_values($categories);
    }

    private function loadBusinesses(): array
    {
        if (! File::exists($this->jsonPath())) {
            return [];
        }

        return json_decode(File::get($this->jsonPath()), true) ?? [];
    }

    private function saveBusinesses(array $businesses): void
    {
        File::put(
            $this->jsonPath(),
            json_encode(
                array_values($businesses),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            true
        );

        $publicPath = '/home5/halalapp/public_html/data/BusinessList.json';
        if (is_dir(dirname($publicPath))) {
            @copy($this->jsonPath(), $publicPath);
        }
    }

    private function uploadImage($file, string $businessName, string $suffix): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($businessName)), '_');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = $slug.'_'.time().'_'.$suffix.'.'.$extension;
        $uploadDirectory = public_path('upload/businesses');

        File::ensureDirectoryExists($uploadDirectory, 0775);
        $file->move($uploadDirectory, $filename);

        $publicDirectory = '/home5/halalapp/public_html/upload/businesses';
        if (is_dir('/home5/halalapp/public_html/upload')) {
            File::ensureDirectoryExists($publicDirectory, 0775);
            @copy($uploadDirectory.'/'.$filename, $publicDirectory.'/'.$filename);
        }

        return 'https://halalapp.info/upload/businesses/'.$filename;
    }

    private function jsonPath(): string
    {
        return public_path('data/BusinessList.json');
    }
}

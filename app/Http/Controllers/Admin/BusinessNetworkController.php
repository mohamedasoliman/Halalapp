<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MembershipDeal;
use App\Support\MembershipTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BusinessNetworkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $allBusinesses = $this->loadBusinesses();
        $tierFilter = $request->string('tier', 'all')->toString();
        $search = trim($request->string('search')->toString());
        $counts = array_fill_keys(['all', ...MembershipTier::VALUES], 0);

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
            $tierComparison = MembershipTier::sortOrder($a['_tier'])
                <=> MembershipTier::sortOrder($b['_tier']);

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
            'additional_addresses' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:100'],
            'alternate_phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:500'],
            'google_maps_url' => ['nullable', 'url', 'max:500'],
            'instagram' => ['nullable', 'url', 'max:500'],
            'facebook' => ['nullable', 'url', 'max:500'],
            'bio' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:3000'],
            'tier' => ['required', Rule::in(MembershipTier::VALUES)],
            'is_active' => ['required', 'boolean'],
            'is_service_area_business' => ['required', 'boolean'],
            'business_status' => ['required', Rule::in([
                'operational',
                'temporarily_closed',
                'unknown',
                'review_required',
            ])],
            'status_note' => ['nullable', 'string', 'max:500'],
            'last_reviewed_at' => ['nullable', 'date'],
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
            'deals' => ['nullable', 'array', 'max:5'],
            'deals.*' => ['array'],
            'deals.*.title' => ['nullable', 'string', 'max:120'],
            'deals.*.description' => ['nullable', 'string', 'max:500'],
            'deals.*.code' => ['nullable', 'string', 'max:50'],
            'deals.*.expiry' => ['nullable', 'date'],
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
        $business['AdditionalAddresses'] = array_values(array_unique(array_filter(array_map(
            'trim',
            preg_split('/\R/', $data['additional_addresses'] ?? '') ?: []
        ))));
        $business['Phone'] = $data['phone'] ?? '';
        $business['AlternatePhone'] = $data['alternate_phone'] ?? '';
        $business['Email'] = $data['email'] ?? '';
        $business['website'] = $data['website'] ?? '';
        $business['GoogleMapsUrl'] = $data['google_maps_url'] ?? '';
        $business['Instagram'] = $data['instagram'] ?? '';
        $business['Facebook'] = $data['facebook'] ?? '';
        $business['Bio'] = $data['bio'] ?? '';
        $business['Desc'] = $data['description'] ?? '';
        $tier = MembershipTier::normalise($data['tier']);
        $business['Tier'] = MembershipTier::label($tier);
        unset(
            $business['BusinessType'],
            $business['Verified'],
            $business['IsVerified']
        );
        $business['IsActive'] = (bool) $data['is_active'];
        $business['IsServiceAreaBusiness'] = (bool) $data['is_service_area_business'];
        $business['BusinessStatus'] = $data['business_status'];
        $business['StatusNote'] = $data['status_note'] ?? '';
        $business['LastReviewedAt'] = $data['last_reviewed_at'] ?? '';
        $business['FeatureInCarousel'] = MembershipTier::canAppearInCarousel($tier)
            && $data['business_status'] === 'operational'
            && (bool) $data['feature_in_carousel'];
        $business['PermissionGranted'] = $request->boolean('permission_granted')
            || (bool) ($existing['PermissionGranted'] ?? false);
        if (MembershipTier::canPublishMenu($tier)) {
            $business['MenuUrl'] = $data['menu_url'] ?? '';
            unset($business['menu_url']);
        } else {
            unset($business['MenuUrl'], $business['menu_url']);
        }

        MembershipDeal::applyToRecord(
            $business,
            MembershipDeal::fromRequest($data, $tier),
            $tier
        );

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

        $galleryLimit = MembershipTier::galleryLimit($data['tier']);

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
        return MembershipTier::normalise($tier);
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
        $uploadDirectory = public_path('upload/businesses');
        File::ensureDirectoryExists($uploadDirectory, 0775);

        $baseFilename = $slug.'_'.time().'_'.$suffix;
        $filename = $baseFilename.'.webp';
        $targetPath = $uploadDirectory.'/'.$filename;

        try {
            $this->writeOptimisedWebp(
                $file->getRealPath(),
                $targetPath,
                $suffix === 'logo' ? 800 : 1600,
                $suffix === 'logo' ? 90 : 84
            );
        } catch (\Throwable $error) {
            Log::warning('Business image WebP optimisation failed; storing original.', [
                'business' => $businessName,
                'suffix' => $suffix,
                'error' => $error->getMessage(),
            ]);

            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $extension = in_array($extension, ['gif', 'jpeg', 'jpg', 'png', 'webp'], true)
                ? $extension
                : 'jpg';
            $filename = $baseFilename.'.'.$extension;
            $targetPath = $uploadDirectory.'/'.$filename;
            $file->move($uploadDirectory, $filename);
        }

        $publicDirectory = '/home5/halalapp/public_html/upload/businesses';
        if (is_dir('/home5/halalapp/public_html/upload')) {
            File::ensureDirectoryExists($publicDirectory, 0775);
            @copy($targetPath, $publicDirectory.'/'.$filename);
        }

        return 'https://halalapp.info/upload/businesses/'.$filename;
    }

    private function writeOptimisedWebp(
        string $sourcePath,
        string $targetPath,
        int $maxDimension,
        int $quality
    ): void {
        if (! extension_loaded('imagick') || ! in_array('WEBP', \Imagick::queryFormats('WEBP'), true)) {
            throw new \RuntimeException('ImageMagick WebP support is unavailable.');
        }

        $image = new \Imagick($sourcePath);

        try {
            $image->setIteratorIndex(0);
            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }

            if ($image->getImageWidth() > $maxDimension
                || $image->getImageHeight() > $maxDimension) {
                $image->thumbnailImage($maxDimension, $maxDimension, true, true);
            }

            $image->setImagePage(0, 0, 0, 0);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($quality);
            $image->setOption('webp:method', '6');
            $image->stripImage();

            if (! $image->writeImage($targetPath)) {
                throw new \RuntimeException('ImageMagick could not write the WebP file.');
            }
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function jsonPath(): string
    {
        return public_path('data/BusinessList.json');
    }
}

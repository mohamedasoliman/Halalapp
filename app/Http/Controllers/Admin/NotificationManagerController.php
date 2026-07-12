<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class NotificationManagerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    private function jsonPath(): string
    {
        return public_path('data/customData.json');
    }

    private function loadJson(): array
    {
        $path = $this->jsonPath();
        if (! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?? [];
    }

    private function saveJson(array $data): void
    {
        File::put($this->jsonPath(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        // Also sync to public_html
        $publicHtmlPath = '/home5/halalapp/public_html/data/customData.json';
        if (is_dir('/home5/halalapp/public_html/data')) {
            @copy($this->jsonPath(), $publicHtmlPath);
        }
    }

    public function index()
    {
        $data = $this->loadJson();

        $notification = [
            'notificationText' => $data['notificationText'] ?? '',
            'notificationButton' => $data['notificationButton'] ?? '',
            'notificationButtonText' => $data['notificationButtonText'] ?? '',
            'notificationVersion' => $data['notificationVersion'] ?? 0,
            'notificationImage' => $data['notificationImage'] ?? '',
            'message' => $data['message'] ?? '',
        ];

        // Determine link type and target from notificationButton
        $linkType = 'screen';
        $linkTarget = $notification['notificationButton'];

        if (preg_match('#^/barcode/product/(.+)$#', $notification['notificationButton'], $m)) {
            $linkType = 'product';
            $linkTarget = $m[1];
        } elseif (preg_match('#^/restaurants/(.+)$#', $notification['notificationButton'], $m)) {
            $linkType = 'restaurant';
            $linkTarget = $m[1];
        } elseif ($notification['notificationButton'] === '/masjid') {
            $linkType = 'masjid';
            $linkTarget = '';
        } elseif (preg_match('#^https?://#', $notification['notificationButton'])) {
            $linkType = 'url';
            $linkTarget = $notification['notificationButton'];
        }

        $notification['linkType'] = $linkType;
        $notification['linkTarget'] = $linkTarget;

        // Check if notification is active (has text)
        $notification['active'] = ! empty($notification['notificationText']);

        // Load restaurant names for dropdown
        $restaurantNames = [];
        $restaurantJsonPath = public_path('data/HalalRestaurantsList.json');
        if (File::exists($restaurantJsonPath)) {
            $restaurants = json_decode(File::get($restaurantJsonPath), true) ?? [];
            $restaurantNames = array_filter(array_map(fn ($r) => $r['NAME'] ?? null, $restaurants));
            sort($restaurantNames);
        }

        $businessNames = [];
        $businessJsonPath = public_path('data/BusinessList.json');
        if (File::exists($businessJsonPath)) {
            $businesses = json_decode(File::get($businessJsonPath), true) ?? [];
            $businessNames = array_values(array_filter(array_map(
                fn ($business) => $business['Name'] ?? null,
                $businesses
            )));
            sort($businessNames);
        }

        // Load ads and users count
        $ads = $data['ads'] ?? [];
        $usersCount = $data['users'] ?? '';
        $scanAds = $data['scanAds'] ?? [];
        $scanAdsActive = $data['scanAdsActive'] ?? false;
        $stickyAd = array_merge([
            'active' => false,
            'campaignId' => '',
            'sponsorName' => '',
            'message' => '',
            'logoUrl' => '',
            'buttonText' => 'View',
            'destinationType' => 'business',
            'destinationTarget' => '',
            'startDate' => '',
            'endDate' => '',
            'version' => 0,
        ], $data['stickyAd'] ?? []);

        return view('admin.notification_manager.index', compact(
            'notification',
            'restaurantNames',
            'businessNames',
            'ads',
            'usersCount',
            'scanAds',
            'scanAdsActive',
            'stickyAd'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'notification_text' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:50',
            'link_type' => 'required|in:product,restaurant,masjid,screen,url',
            'link_target' => 'nullable|string|max:500',
            'notification_image' => 'nullable|image|max:5120',
        ]);

        $data = $this->loadJson();

        $active = $request->has('active');

        if ($active) {
            $data['notificationText'] = $request->notification_text ?? '';
            $data['notificationButtonText'] = $request->button_text ?? '';

            // Build the GoRouter path based on link type
            $linkTarget = trim($request->link_target ?? '');
            $data['notificationButton'] = match ($request->link_type) {
                'product' => '/barcode/product/'.$linkTarget,
                'restaurant' => '/restaurants/'.rawurlencode($linkTarget),
                'masjid' => '/masjid',
                'screen' => $linkTarget,
                'url' => $linkTarget,
            };
        } else {
            $data['notificationText'] = '';
            $data['notificationButton'] = '';
            $data['notificationButtonText'] = '';
        }

        // Handle image upload
        if ($request->hasFile('notification_image')) {
            $imageUrl = $this->uploadImage($request->file('notification_image'));
            if ($imageUrl) {
                $data['notificationImage'] = $imageUrl;
            }
        }

        // Remove image if requested
        if ($request->has('remove_image')) {
            unset($data['notificationImage']);
        }

        // Increment notification version
        $data['notificationVersion'] = ($data['notificationVersion'] ?? 0) + 1;

        $this->saveJson($data);

        return redirect()->back()->with('success', 'Notification updated successfully. Version: '.$data['notificationVersion']);
    }

    public function updateAds(Request $request)
    {
        $request->validate([
            'ad_image_urls' => 'nullable|array',
            'ad_image_urls.*' => 'nullable|string|max:500',
            'ad_link_urls' => 'nullable|array',
            'ad_link_urls.*' => 'nullable|string|max:500',
            'new_ad_image' => 'nullable|image|max:5120',
            'new_ad_link' => 'nullable|string|max:500',
        ]);

        $data = $this->loadJson();
        $ads = [];

        // Update existing ads
        $imageUrls = $request->ad_image_urls ?? [];
        $linkUrls = $request->ad_link_urls ?? [];

        for ($i = 0; $i < count($imageUrls); $i++) {
            if (! empty($imageUrls[$i])) {
                $ads[] = [
                    'adImageUrl' => $imageUrls[$i],
                    'adLinkUrl' => $linkUrls[$i] ?? '',
                ];
            }
        }

        // Handle new ad with image upload
        if ($request->hasFile('new_ad_image')) {
            $imageUrl = $this->uploadAdImage($request->file('new_ad_image'));
            if ($imageUrl) {
                $ads[] = [
                    'adImageUrl' => $imageUrl,
                    'adLinkUrl' => $request->new_ad_link ?? '',
                ];
            }
        }

        $data['ads'] = $ads;
        $this->saveJson($data);

        return redirect()->back()->with('success', 'Ads updated successfully.');
    }

    public function deleteAd(Request $request, int $index)
    {
        $data = $this->loadJson();
        $ads = $data['ads'] ?? [];

        if (! isset($ads[$index])) {
            return redirect()->back()->with('error', 'Ad not found.');
        }

        array_splice($ads, $index, 1);
        $data['ads'] = $ads;
        $this->saveJson($data);

        return redirect()->back()->with('success', 'Ad deleted successfully.');
    }

    public function updateUsers(Request $request)
    {
        $request->validate([
            'users_count' => 'required|string|max:50',
        ]);

        $data = $this->loadJson();
        $data['users'] = $request->users_count;
        $this->saveJson($data);

        return redirect()->back()->with('success', 'Users count updated successfully.');
    }

    public function updateStickyAd(Request $request)
    {
        $validated = $request->validate([
            'active' => ['nullable', 'boolean'],
            'sponsor_name' => ['required_if:active,1', 'nullable', 'string', 'max:60'],
            'message' => ['required_if:active,1', 'nullable', 'string', 'max:90'],
            'button_text' => ['nullable', 'string', 'max:16'],
            'destination_type' => ['required', 'in:business,restaurant,screen,url'],
            'destination_target' => ['required_if:active,1', 'nullable', 'string', 'max:500'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        $active = $request->boolean('active');
        $destinationType = $validated['destination_type'];
        $destinationTarget = trim($validated['destination_target'] ?? '');
        if ($active && $destinationType === 'url' && ! filter_var($destinationTarget, FILTER_VALIDATE_URL)) {
            return redirect()->back()
                ->withErrors(['destination_target' => 'Enter a valid external URL.'])
                ->withInput();
        }

        $data = $this->loadJson();
        $existing = $data['stickyAd'] ?? [];
        $logoUrl = $existing['logoUrl'] ?? '';

        if ($request->boolean('remove_logo')) {
            $logoUrl = '';
        }
        if ($request->hasFile('logo')) {
            $uploadedLogo = $this->uploadAdImage($request->file('logo'));
            if ($uploadedLogo !== null) {
                $logoUrl = $uploadedLogo;
            }
        }

        $version = ((int) ($existing['version'] ?? 0)) + 1;
        $data['stickyAd'] = [
            'active' => $active,
            'campaignId' => 'sticky-'.$version,
            'sponsorName' => trim($validated['sponsor_name'] ?? ''),
            'message' => trim($validated['message'] ?? ''),
            'logoUrl' => $logoUrl,
            'buttonText' => trim($validated['button_text'] ?? '') ?: 'View',
            'destinationType' => $destinationType,
            'destinationTarget' => $destinationTarget,
            'startDate' => $validated['start_date'] ?? '',
            'endDate' => $validated['end_date'] ?? '',
            'version' => $version,
        ];

        $this->saveJson($data);

        return redirect()->back()->with(
            'success',
            $active ? 'Sticky sponsored banner activated.' : 'Sticky sponsored banner saved as inactive.'
        );
    }

    public function updateScanAds(Request $request)
    {
        $request->validate([
            'ad_image_urls' => 'nullable|array',
            'ad_image_urls.*' => 'nullable|string|max:500',
            'ad_link_urls' => 'nullable|array',
            'ad_link_urls.*' => 'nullable|string|max:500',
            'new_ad_image' => 'nullable|image|max:5120',
            'new_ad_link' => 'nullable|string|max:500',
        ]);

        $data = $this->loadJson();

        $data['scanAdsActive'] = $request->has('scan_ads_active');

        $ads = [];
        $imageUrls = $request->ad_image_urls ?? [];
        $linkUrls = $request->ad_link_urls ?? [];

        for ($i = 0; $i < count($imageUrls); $i++) {
            if (! empty($imageUrls[$i])) {
                $ads[] = [
                    'adImageUrl' => $imageUrls[$i],
                    'adLinkUrl' => $linkUrls[$i] ?? '',
                ];
            }
        }

        if ($request->hasFile('new_ad_image')) {
            $imageUrl = $this->uploadAdImage($request->file('new_ad_image'));
            if ($imageUrl) {
                $ads[] = [
                    'adImageUrl' => $imageUrl,
                    'adLinkUrl' => $request->new_ad_link ?? '',
                ];
            }
        }

        $data['scanAds'] = $ads;
        $this->saveJson($data);

        return redirect()->back()->with('success', 'Scan ads updated successfully.');
    }

    public function deleteScanAd(int $index)
    {
        $data = $this->loadJson();
        $ads = $data['scanAds'] ?? [];

        if (! isset($ads[$index])) {
            return redirect()->back()->with('error', 'Ad not found.');
        }

        array_splice($ads, $index, 1);
        $data['scanAds'] = $ads;
        $this->saveJson($data);

        return redirect()->back()->with('success', 'Scan ad deleted.');
    }

    private function uploadAdImage($file): ?string
    {
        try {
            $ext = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'ad_'.time().'_'.mt_rand(100, 999).'.'.$ext;

            $uploadDir = public_path('data/images');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $file->move($uploadDir, $filename);

            // Also copy to public_html on server
            $serverDir = '/home5/halalapp/public_html/data/images';
            if (is_dir('/home5/halalapp/public_html/data')) {
                if (! is_dir($serverDir)) {
                    @mkdir($serverDir, 0775, true);
                }
                @copy("{$uploadDir}/{$filename}", "{$serverDir}/{$filename}");
            }

            return "https://halalapp.info/data/images/{$filename}";
        } catch (\Exception $e) {
            return null;
        }
    }

    private function uploadImage($file): ?string
    {
        try {
            $ext = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'notification_'.time().'.'.$ext;

            $uploadDir = public_path('data/images/notifications');
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $file->move($uploadDir, $filename);

            // Also copy to public_html on server
            $serverDir = '/home5/halalapp/public_html/data/images/notifications';
            if (is_dir('/home5/halalapp/public_html/data/images')) {
                if (! is_dir($serverDir)) {
                    @mkdir($serverDir, 0775, true);
                }
                @copy("{$uploadDir}/{$filename}", "{$serverDir}/{$filename}");
            }

            return "https://halalapp.info/data/images/notifications/{$filename}";
        } catch (\Exception $e) {
            return null;
        }
    }
}

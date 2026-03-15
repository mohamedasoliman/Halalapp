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
        if (!File::exists($path)) {
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
        $notification['active'] = !empty($notification['notificationText']);

        return view('admin.notification_manager.index', compact('notification'));
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
                'product' => '/barcode/product/' . $linkTarget,
                'restaurant' => '/restaurants/' . urlencode($linkTarget),
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

        return redirect()->back()->with('success', 'Notification updated successfully. Version: ' . $data['notificationVersion']);
    }

    private function uploadImage($file): ?string
    {
        try {
            $ext = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'notification_' . time() . '.' . $ext;

            $uploadDir = public_path('data/images/notifications');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $file->move($uploadDir, $filename);

            // Also copy to public_html on server
            $serverDir = '/home5/halalapp/public_html/data/images/notifications';
            if (is_dir('/home5/halalapp/public_html/data/images')) {
                if (!is_dir($serverDir)) {
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Narrow, reusable admin media boundary. New purposes must be explicitly
 * allowed here; clients can never choose an arbitrary model or collection.
 */
final class MediaController extends Controller
{
    public function store(Request $request, SettingsService $settings): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'in:site_logo'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $setting = Setting::query()->where('key', 'site_logo_url')->firstOrFail();
        $media = $setting->addMediaFromRequest('file')->toMediaCollection('site_logo');
        $url = $media->getFullUrl();
        $settings->update('site_logo_url', $url, $request->user());

        return response()->json(['data' => $this->mediaItem($media, $data['purpose'], $url)], 201);
    }

    /** @return array{id:int,purpose:string,url:string,name:string,mime_type:?string,size:int} */
    private function mediaItem(Media $media, string $purpose, string $url): array
    {
        return [
            'id' => $media->id,
            'purpose' => $purpose,
            'url' => $url,
            'name' => $media->name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
        ];
    }
}

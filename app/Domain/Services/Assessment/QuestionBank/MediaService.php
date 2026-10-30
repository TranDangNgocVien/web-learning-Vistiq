<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use RuntimeException;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Services\System\SettingsService;

class MediaService
{
    public function __construct(private SettingsService $settings) {}

    public function configure(): string
    {
        $value = $this->settings->get('storage');
        $disk = $value['disk'];
        if ($disk === 's3') {
            config(['filesystems.disks.s3' => array_merge(config('filesystems.disks.s3'), array_diff_key($value, ['disk' => true]))]);
            Storage::forgetDisk('s3');
        }

        return $disk;
    }

    public function store(UploadedFile $file, ?int $actorId, ?int $questionId = null): MediaAsset
    {
        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            return $this->storeImage($file, $actorId, $questionId);
        }

        // Local setups can use their configured filesystem without requiring Cloudflare R2.
        $disk = app()->environment('local') ? $this->configure() : 'r2';
        $folder = $this->questionFolder($questionId);
        $path = $file->store($folder, $disk);
        abort_unless(is_string($path), 503, 'Không thể lưu media lên ổ lưu trữ.');

        return MediaAsset::create([
            'disk' => $disk,
            'path' => $path,
            'title' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'created_by' => $actorId,
            'metadata' => ['storage_folder' => $folder],
        ]);
    }

    public function url(MediaAsset $asset): string
    {
        if ($asset->disk === 'cloudinary') {
            return (string) ($asset->metadata['secure_url'] ?? '');
        }

        $disk = $this->diskFor($asset);
        $cdn = $this->settings->get('media')['cdn_url'];
        if ($cdn && $asset->disk === 'public') {
            return rtrim($cdn, '/').'/'.ltrim($asset->path, '/');
        }

        return in_array($asset->disk, ['s3', 'r2'], true) ? $disk->temporaryUrl($asset->path, now()->addMinutes(15)) : $disk->url($asset->path);
    }

    public function available(int $id): bool
    {
        return $this->checkAvailability($id) === true;
    }

    /**
     * Return null when storage could not be checked (for example, a TLS or
     * network failure). Callers that make publishing decisions should treat
     * that state as unavailable; read-only review pages can explain it without
     * failing the whole page.
     */
    public function checkAvailability(int $id): ?bool
    {
        $asset = MediaAsset::find($id);
        if (! $asset) {
            return false;
        }

        if ($asset->disk === 'cloudinary') {
            return filled($asset->metadata['secure_url'] ?? null);
        }

        try {
            return $this->diskFor($asset)->exists($asset->path);
        } catch (UnableToCheckFileExistence $exception) {
            Log::warning('Unable to check media asset availability.', [
                'media_id' => $asset->id,
                'disk' => $asset->disk,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    public function delete(MediaAsset $asset): void
    {
        if ($asset->disk !== 'cloudinary') {
            $this->diskFor($asset)->delete($asset->path);
        }

        $asset->delete();
    }

    public function diskFor(MediaAsset $asset): Filesystem
    {
        $this->configure();
        if ($asset->disk === 'r2') {
            return Storage::disk('r2');
        }
        if ($asset->disk === 's3' && ! empty($asset->metadata['storage_location'])) {
            return Storage::build(array_replace(config('filesystems.disks.s3'), $asset->metadata['storage_location']));
        }

        return Storage::disk($asset->disk);
    }

    private function storeImage(UploadedFile $file, ?int $actorId, ?int $questionId): MediaAsset
    {
        $cloudinary = config('services.cloudinary');
        abort_if(blank($cloudinary['cloud_name'] ?? null) || blank($cloudinary['api_key'] ?? null) || blank($cloudinary['api_secret'] ?? null), 503, 'Thiếu cấu hình Cloudinary.');

        $timestamp = now()->timestamp;
        $folder = $this->questionFolder($questionId);
        $publicId = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::uuid();
        $parameters = ['folder' => $folder, 'public_id' => $publicId, 'timestamp' => $timestamp];
        ksort($parameters);
        $signature = sha1(urldecode(http_build_query($parameters)).$cloudinary['api_secret']);
        $fileHandle = fopen($file->getRealPath(), 'rb');

        if ($fileHandle === false) {
            throw new RuntimeException('Không thể đọc ảnh tải lên.');
        }

        try {
            $response = Http::connectTimeout(10)->timeout(60)
                ->attach('file', $fileHandle, $file->getClientOriginalName())
                ->post('https://api.cloudinary.com/v1_1/'.$cloudinary['cloud_name'].'/image/upload', [
                    ...$parameters,
                    'api_key' => $cloudinary['api_key'],
                    'signature' => $signature,
                ]);
        } finally {
            fclose($fileHandle);
        }

        if (! $response->successful() || ! filled($response->json('secure_url'))) {
            report(new RuntimeException('Cloudinary upload failed: '.$response->status()));
            abort(503, 'Không thể lưu ảnh lên Cloudinary.');
        }

        return MediaAsset::create([
            'disk' => 'cloudinary',
            'path' => $response->json('public_id'),
            'title' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'created_by' => $actorId,
            'metadata' => [
                'secure_url' => $response->json('secure_url'),
                'storage_folder' => $folder,
                'cloudinary_asset_id' => $response->json('asset_id'),
            ],
        ]);
    }

    private function questionFolder(?int $questionId): string
    {
        return $questionId === null
            ? 'media/'.now()->format('Y/m')
            : 'questions/Q-'.str_pad((string) $questionId, 5, '0', STR_PAD_LEFT);
    }
}

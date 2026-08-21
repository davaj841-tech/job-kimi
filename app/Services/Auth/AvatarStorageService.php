<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AvatarStorageService
{
    /** @var list<string> */
    protected array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    public function storeFromDataUri(User $user, string $dataUri): string
    {
        if (! preg_match('#^data:image/(png|jpe?g|webp);base64,([A-Za-z0-9+/=\s]+)$#i', $dataUri, $m)) {
            throw ValidationException::withMessages([
                'photo' => ['فرمت تصویر نامعتبر است. فقط JPEG، PNG یا WebP مجاز است.'],
            ]);
        }

        $raw = base64_decode($m[2], true);
        if ($raw === false) {
            throw ValidationException::withMessages([
                'photo' => ['تصویر قابل خواندن نیست.'],
            ]);
        }

        return $this->storeBinary($user, $raw);
    }

    public function storeFromUpload(User $user, UploadedFile $file): string
    {
        if ($file->getSize() > 2_000_000) {
            throw ValidationException::withMessages([
                'photo' => ['حجم تصویر حداکثر ۲ مگابایت است.'],
            ]);
        }

        $raw = file_get_contents($file->getRealPath() ?: $file->getPathname());
        if ($raw === false) {
            throw ValidationException::withMessages([
                'photo' => ['آپلود تصویر ناموفق بود.'],
            ]);
        }

        return $this->storeBinary($user, $raw);
    }

    public function delete(User $user): void
    {
        if ($user->avatar && ! str_starts_with((string) $user->avatar, 'data:image')) {
            Storage::disk('public')->delete($user->avatar);
        }
    }

    protected function storeBinary(User $user, string $raw): string
    {
        if (strlen($raw) > 2_000_000) {
            throw ValidationException::withMessages([
                'photo' => ['حجم تصویر حداکثر ۲ مگابایت است.'],
            ]);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);
        if (! in_array($mime, $this->allowedMimes, true)) {
            throw ValidationException::withMessages([
                'photo' => ['نوع فایل تصویر معتبر نیست.'],
            ]);
        }

        if (@getimagesizefromstring($raw) === false) {
            throw ValidationException::withMessages([
                'photo' => ['فایل انتخاب‌شده یک تصویر معتبر نیست.'],
            ]);
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            throw ValidationException::withMessages([
                'photo' => ['پردازش تصویر ممکن نشد.'],
            ]);
        }

        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }

        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);
        $jpeg = (string) ob_get_clean();

        if ($jpeg === '') {
            throw ValidationException::withMessages([
                'photo' => ['ذخیره تصویر ممکن نشد.'],
            ]);
        }

        $this->delete($user);
        $rel = 'avatars/'.$user->id.'.jpg';
        Storage::disk('public')->put($rel, $jpeg);

        return $rel;
    }
}

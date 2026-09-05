<?php

namespace App\Services\Storefront;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores a transfer receipt as a real file (handoff 12 · 13).
 *
 * Two things the frontend explicitly does not do, and therefore must happen
 * here: check the file's actual MIME type, and cap its size. The upload form
 * only sets `accept="image/*"`, which is a hint to a file picker and no kind of
 * check at all — the request need not have come from that form.
 */
class ReceiptStorage
{
    /** 5 MB, the ceiling handoff 13 names. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** What a photo of a transfer confirmation actually is. */
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

    /**
     * @return string|null the stored path, or null when no file was sent
     *
     * @throws ValidationException on a file that is too big or not an image
     */
    public function store(?UploadedFile $file, string $folder = 'receipts', string $field = 'receiptImage'): ?string
    {
        if ($file === null) {
            return null;
        }

        if (! $file->isValid()) {
            $this->fail($field, 'تعذّر رفع الملف، حاول مرة أخرى');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            $this->fail($field, 'حجم الصورة كبير جداً — الحد الأقصى 5 ميجابايت');
        }

        // getMimeType() sniffs the file's contents; getClientMimeType() would
        // just repeat whatever the client claimed in the request.
        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            $this->fail($field, 'يجب أن يكون الملف صورة (JPG أو PNG أو WEBP)');
        }

        // The name is generated, never taken from the upload: a client-supplied
        // filename is a path-traversal and content-type problem waiting to
        // happen, and the extension is re-derived from the sniffed type.
        $extension = strtolower($file->extension() ?: 'jpg');

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = 'jpg';
        }

        return $file->storeAs(
            $folder,
            Str::ulid() . '.' . $extension,
            ['disk' => 'public'],
        ) ?: null;
    }

    /** Replace an existing receipt, removing the file it supersedes. */
    public function replace(?string $existing, ?UploadedFile $file, string $folder = 'receipts', string $field = 'receiptImage'): ?string
    {
        $stored = $this->store($file, $folder, $field);

        if ($stored !== null && $existing !== null) {
            Storage::disk('public')->delete($existing);
        }

        return $stored ?? $existing;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

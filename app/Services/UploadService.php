<?php

declare(strict_types=1);

namespace App\Services;

final class UploadService
{
    private const ALLOWED = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    private const IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public function store(array $file, string $directory = '', ?int $maxDimension = null): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed.');
        }

        $maxBytes = ((int) config('security.max_upload_mb', 2)) * 1024 * 1024;
        if ((int) $file['size'] > $maxBytes) {
            throw new \RuntimeException('The uploaded file is too large.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Unsupported file type.');
        }

        $safeDirectory = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $directory), '/');
        $targetDirectory = PUBLIC_PATH . '/uploads' . ($safeDirectory ? '/' . $safeDirectory : '');
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $name = bin2hex(random_bytes(18)) . '.' . self::ALLOWED[$mime];
        $target = $targetDirectory . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new \RuntimeException('Could not store uploaded file.');
        }

        if ($maxDimension !== null && in_array($mime, self::IMAGE_MIMES, true)) {
            $this->resizeToFit($target, $mime, $maxDimension);
        }

        return ($safeDirectory ? $safeDirectory . '/' : '') . $name;
    }

    /**
     * Scales the image down to fit within a square bounding box, preserving aspect ratio, so an
     * oversized upload (e.g. a 4000px logo) never gets stored and re-served at its original size.
     * Images already within bounds, or any GD failure, are left untouched - this is a best-effort
     * optimisation, not a requirement for the upload to succeed.
     */
    private function resizeToFit(string $path, string $mime, int $maxDimension): void
    {
        if (!extension_loaded('gd')) {
            return;
        }

        $info = @getimagesize($path);
        if (!$info) {
            return;
        }

        [$width, $height] = $info;
        if ($width <= $maxDimension && $height <= $maxDimension) {
            return;
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (!$source) {
            return;
        }

        $scale = min($maxDimension / $width, $maxDimension / $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($mime !== 'image/jpeg') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);

        match ($mime) {
            'image/jpeg' => imagejpeg($resized, $path, 88),
            'image/png' => imagepng($resized, $path),
            'image/webp' => function_exists('imagewebp') ? imagewebp($resized, $path, 88) : null,
            default => null,
        };
        imagedestroy($resized);
    }
}

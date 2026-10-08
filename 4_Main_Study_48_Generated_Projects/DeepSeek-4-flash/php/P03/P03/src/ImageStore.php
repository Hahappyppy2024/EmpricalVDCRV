<?php

declare(strict_types=1);

namespace Shop;

/**
 * Deterministic local object-store adapter: uploaded product images are
 * saved to a local upload directory and referenced from the products table.
 */
final class ImageStore
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    public function __construct(private string $uploadDir)
    {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
    }

    /**
     * @param array<string,mixed> $file A $_FILES entry.
     */
    public function save(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['image' => 'Image upload failed.']);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new ValidationException(['image' => 'Only JPG, PNG, WEBP, GIF or SVG images are allowed.']);
        }
        if ($mime === 'image/svg+xml' && $this->hasActiveContent((string) file_get_contents((string) $file['tmp_name']))) {
            throw new ValidationException(['image' => 'SVG images with active or executable content are not allowed.']);
        }
        $name = 'product-' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $this->uploadDir . DIRECTORY_SEPARATOR . $name)) {
            throw new DomainException('Could not store the uploaded image.', 500);
        }
        return $name;
    }

    /**
     * Reject SVG files that contain active or scriptable content.
     */
    private function hasActiveContent(string $content): bool
    {
        $patterns = [
            '/<\s*script\b/i',
            '/<\s*foreignObject\b/i',
            '/<\s*iframe\b/i',
            '/<\s*embed\b/i',
            '/<\s*object\b/i',
            '/<\s*\?php/i',
            '/on[a-z]+\s*=/i',
            '/\bjavascript\s*:/i',
            '/\bvbscript\s*:/i',
            '/<\s*style\b[^>]*>\s*@import/i',
            '/expression\s*\(/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }
        return false;
    }
}

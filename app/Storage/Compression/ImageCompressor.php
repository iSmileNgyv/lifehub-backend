<?php

namespace App\Storage\Compression;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Şəkli korlamadan maksimum kiçildir: max 1600px-ə scale-down + WebP (keyfiyyət 75).
 * GD-də WebP yoxdursa JPEG-ə düşür.
 */
class ImageCompressor implements FileCompressor
{
    private const MAX_DIMENSION = 1600;

    public function compress(string $contents, string $mime, string $extension): CompressedResult
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->decodeBinary($contents);
        $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

        try {
            $encoded = (string) $image->encodeUsingMediaType('image/webp', quality: 75);

            return new CompressedResult($encoded, 'image/webp', 'webp');
        } catch (Throwable) {
            $encoded = (string) $image->encodeUsingMediaType('image/jpeg', quality: 80);

            return new CompressedResult($encoded, 'image/jpeg', 'jpg');
        }
    }
}

<?php

namespace App\Storage\Compression;

/**
 * Factory: mime-ə görə uyğun sıxıcı.
 *  image/*          → ImageCompressor (resize + webp)
 *  application/pdf  → PdfCompressor (ghostscript)
 *  digər            → NullCompressor (olduğu kimi)
 */
class CompressorFactory
{
    public static function for(string $mime): FileCompressor
    {
        if (str_starts_with($mime, 'image/')) {
            return new ImageCompressor();
        }

        if ($mime === 'application/pdf') {
            return new PdfCompressor();
        }

        return new NullCompressor();
    }
}

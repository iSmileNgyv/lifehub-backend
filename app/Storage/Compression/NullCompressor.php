<?php

namespace App\Storage\Compression;

/** Sıxılmadan saxlayır (xlsx və s. — itkisiz mənalı sıxma yoxdur). */
class NullCompressor implements FileCompressor
{
    public function compress(string $contents, string $mime, string $extension): CompressedResult
    {
        return new CompressedResult($contents, $mime, $extension);
    }
}

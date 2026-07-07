<?php

namespace App\Storage\Compression;

/** Sıxılmış faylın nəticəsi. */
class CompressedResult
{
    public function __construct(
        public string $contents,
        public string $mime,
        public string $extension,
    ) {}
}

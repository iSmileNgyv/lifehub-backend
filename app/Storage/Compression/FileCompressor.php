<?php

namespace App\Storage\Compression;

interface FileCompressor
{
    public function compress(string $contents, string $mime, string $extension): CompressedResult;
}

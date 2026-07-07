<?php

namespace App\Storage\Drivers;

use App\Storage\StorageDriver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class LocalStorageDriver implements StorageDriver
{
    private Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk('public');
    }

    public function name(): string
    {
        return 'local';
    }

    public function put(string $path, string $contents): void
    {
        $this->disk->put($path, $contents);
    }

    public function url(string $path): string
    {
        return $this->disk->url($path);
    }

    public function delete(string $path): void
    {
        $this->disk->delete($path);
    }
}

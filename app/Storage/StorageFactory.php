<?php

namespace App\Storage;

use App\Storage\Drivers\LocalStorageDriver;
use InvalidArgumentException;

/**
 * Factory: .env STORAGE_DRIVER-ə görə uyğun storage driver qaytarır.
 * Köhnə fayllar üçün öz driver-ini (bazadan) ötürmək olar.
 */
class StorageFactory
{
    public static function make(?string $driver = null): StorageDriver
    {
        $driver ??= config('storage.driver', 'local');

        return match ($driver) {
            'local' => new LocalStorageDriver(),
            // 's3' => new Drivers\S3StorageDriver(),     // sonra
            // 'azure' => new Drivers\AzureStorageDriver(), // sonra
            default => throw new InvalidArgumentException("Dəstəklənməyən storage driver: {$driver}"),
        };
    }
}

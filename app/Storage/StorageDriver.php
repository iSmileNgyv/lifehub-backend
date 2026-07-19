<?php

namespace App\Storage;

/**
 * Storage driver müqaviləsi. local indi var; s3/azure sonra eyni interfeysi həyata keçirir.
 */
interface StorageDriver
{
    /** Driver adı — bazada saxlanır ki, sistem harada axtaracağını bilsin. */
    public function name(): string;

    public function put(string $path, string $contents): void;

    /** Faylın məzmununu qaytarır (kopyalama üçün). */
    public function get(string $path): string;

    public function url(string $path): string;

    public function delete(string $path): void;
}

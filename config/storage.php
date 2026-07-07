<?php

return [
    /*
     * Hansı storage driver işləsin: local | s3 | azure.
     * Hələlik yalnız `local` implementasiya olunub.
     */
    'driver' => env('STORAGE_DRIVER', 'local'),
];

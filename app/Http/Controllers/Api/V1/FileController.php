<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use App\Storage\Compression\CompressorFactory;
use App\Storage\StorageFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /** POST /api/v1/files — yüklə → sıx → saxla → reyestrə yaz */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // limit yox (biznes), yalnız serverin təhlükəsizlik tavanı
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $file = $request->file('file');
        $contents = (string) file_get_contents($file->getRealPath());
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $ext = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));

        // mime-ə görə sıxma (factory)
        $result = CompressorFactory::for($mime)->compress($contents, $mime, $ext);

        // driver-ə görə saxlama (factory)
        $driver = StorageFactory::make();
        $uid = (string) Str::ulid();
        $path = "uploads/{$uid}.{$result->extension}";
        $driver->put($path, $result->contents);

        $stored = StoredFile::create([
            'uid' => $uid,
            'driver' => $driver->name(),
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $result->mime,
            'size' => strlen($result->contents),
        ]);

        return response()->json([
            'uid' => $stored->uid,
            'url' => $driver->url($path),
            'mime' => $stored->mime,
            'size' => $stored->size,
        ], 201);
    }

    /** GET /api/v1/files/{file} — faylı driver-in url-inə yönləndirir (public, <img> üçün) */
    public function show(StoredFile $file): RedirectResponse
    {
        $driver = StorageFactory::make($file->driver);

        return redirect($driver->url($file->path));
    }
}

<?php

namespace App\Storage\Compression;

/**
 * PDF-i Ghostscript ilə sıxır (içindəki şəkilləri downsample edir).
 * `gs` yoxdursa fayl olduğu kimi saxlanır (korlanmır).
 */
class PdfCompressor implements FileCompressor
{
    public function compress(string $contents, string $mime, string $extension): CompressedResult
    {
        if (! $this->ghostscriptAvailable()) {
            return new CompressedResult($contents, 'application/pdf', 'pdf');
        }

        $in = tempnam(sys_get_temp_dir(), 'pdf_in_');
        $out = tempnam(sys_get_temp_dir(), 'pdf_out_');
        file_put_contents($in, $contents);

        $cmd = sprintf(
            'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s 2>/dev/null',
            escapeshellarg($out),
            escapeshellarg($in),
        );
        @exec($cmd, $output, $code);

        $compressed = ($code === 0 && is_file($out) && filesize($out) > 0)
            ? (string) file_get_contents($out)
            : $contents;

        // sıxılmış daha böyükdürsə orijinalı saxla
        if (strlen($compressed) >= strlen($contents)) {
            $compressed = $contents;
        }

        @unlink($in);
        @unlink($out);

        return new CompressedResult($compressed, 'application/pdf', 'pdf');
    }

    private function ghostscriptAvailable(): bool
    {
        @exec('which gs', $o, $code);

        return $code === 0;
    }
}

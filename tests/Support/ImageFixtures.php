<?php

declare(strict_types=1);

namespace Tests\Support;

/** Membuat berkas gambar/uji kecil di direktori sementara untuk tes unggahan. */
final class ImageFixtures
{
    public static function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/pos-retail-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        return $dir;
    }

    public static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    /** @param 'png'|'jpeg'|'webp'|'gif' $type */
    public static function image(string $dir, string $type = 'png', int $w = 8, int $h = 8, int $red = 200): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, $red, 40, 90));
        $path = $dir . '/' . bin2hex(random_bytes(4)) . '.bin';
        match ($type) {
            'png' => imagepng($im, $path),
            'jpeg' => imagejpeg($im, $path),
            'webp' => imagewebp($im, $path),
            'gif' => imagegif($im, $path),
        };
        return $path;
    }

    public static function file(string $dir, string $content): string
    {
        $path = $dir . '/' . bin2hex(random_bytes(4)) . '.bin';
        file_put_contents($path, $content);
        return $path;
    }

    /** @return array<string, mixed> bentuk entri $_FILES */
    public static function upload(string $path, string $name = 'logo.png', string $type = 'image/png', int $error = UPLOAD_ERR_OK): array
    {
        return ['name' => $name, 'type' => $type, 'tmp_name' => $path, 'error' => $error, 'size' => is_file($path) ? (int) filesize($path) : 0];
    }
}

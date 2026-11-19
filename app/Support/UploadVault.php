<?php

namespace App\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class UploadVault
{
    public static function protect(): void
    {
        try {
            $root = self::root();
            if (! is_dir($root) && ! @mkdir($root, 0755, true) && ! is_dir($root)) {
                return;
            }

            self::ensurePublicLink();

            $marker = storage_path('app/.uploads-protected');
            if (is_file($marker)) {
                return;
            }

            foreach (['local', 'public'] as $disk) {
                self::restoreDisk($disk);
                self::mirrorDisk($disk);
            }

            $markerDir = dirname($marker);
            if (! is_dir($markerDir)) {
                @mkdir($markerDir, 0755, true);
            }
            @file_put_contents($marker, (string) time());
        } catch (Throwable) {
            // Request tetap jalan bila folder cadangan tidak bisa ditulis.
        }
    }

    public static function mirror(string $disk, string $relativePath): void
    {
        try {
            $relativePath = self::relative($relativePath);
            if ($relativePath === '') {
                return;
            }
            $source = self::storageFile($disk, $relativePath);
            if ($source === null || ! is_file($source)) {
                return;
            }

            self::copyFile($source, self::vaultFile($disk, $relativePath));
        } catch (Throwable) {
        }
    }

    public static function forget(string $disk, string $relativePath): void
    {
        try {
            $relativePath = self::relative($relativePath);
            if ($relativePath === '') {
                return;
            }
            $backup = self::vaultFile($disk, $relativePath);
            if (is_file($backup)) {
                @unlink($backup);
            }
        } catch (Throwable) {
        }
    }

    public static function ensure(string $disk, string $relativePath): void
    {
        try {
            $relativePath = self::relative($relativePath);
            if ($relativePath === '') {
                return;
            }
            $source = self::storageFile($disk, $relativePath);
            $backup = self::vaultFile($disk, $relativePath);
            if ($source === null || is_file($source) || ! is_file($backup)) {
                return;
            }

            self::copyFile($backup, $source);
        } catch (Throwable) {
        }
    }

    public static function root(): string
    {
        $fallback = dirname(base_path()).DIRECTORY_SEPARATOR.'irsaf-uploads';
        $configured = config('filesystems.uploads_vault');
        if (! is_string($configured) || trim($configured) === '') {
            return $fallback;
        }

        $configured = rtrim(trim($configured), '/\\');
        $base = str_replace('\\', '/', rtrim(base_path(), '/\\'));
        $candidate = str_replace('\\', '/', $configured);
        if ($candidate === $base || str_starts_with($candidate.'/', $base.'/')) {
            return $fallback;
        }

        return $configured;
    }

    private static function restoreDisk(string $disk): void
    {
        $vaultRoot = self::diskRoot($disk);
        $storageRoot = self::storageRoot($disk);
        if ($storageRoot === null || ! is_dir($vaultRoot)) {
            return;
        }

        foreach (self::filesIn($vaultRoot) as $backup) {
            $relative = self::relativeFrom($vaultRoot, $backup);
            if ($relative === '') {
                continue;
            }
            $target = $storageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! is_file($target)) {
                self::copyFile($backup, $target);
            }
        }
    }

    private static function mirrorDisk(string $disk): void
    {
        $storageRoot = self::storageRoot($disk);
        if ($storageRoot === null || ! is_dir($storageRoot)) {
            return;
        }

        foreach (self::filesIn($storageRoot) as $source) {
            $relative = self::relativeFrom($storageRoot, $source);
            if ($relative === '') {
                continue;
            }
            $backup = self::diskRoot($disk).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! is_file($backup) || filemtime($source) > filemtime($backup)) {
                self::copyFile($source, $backup);
            }
        }
    }

    private static function ensurePublicLink(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');
        if (! is_dir($target)) {
            @mkdir($target, 0755, true);
        }

        if (is_link($link)) {
            if (file_exists($link)) {
                return;
            }
            @unlink($link);
        } elseif (file_exists($link)) {
            return;
        }

        @symlink($target, $link);
    }

    private static function copyFile(string $source, string $destination): void
    {
        $directory = dirname($destination);
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return;
        }

        @copy($source, $destination);
    }

    private static function storageFile(string $disk, string $relativePath): ?string
    {
        $root = self::storageRoot($disk);

        return $root === null ? null : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private static function vaultFile(string $disk, string $relativePath): string
    {
        return self::diskRoot($disk).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private static function storageRoot(string $disk): ?string
    {
        $root = config('filesystems.disks.'.$disk.'.root');

        return is_string($root) && $root !== '' ? rtrim($root, '/\\') : null;
    }

    private static function diskRoot(string $disk): string
    {
        $folder = $disk === 'local' ? 'private' : $disk;

        return self::root().DIRECTORY_SEPARATOR.$folder;
    }

    private static function relative(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..')) {
            return '';
        }

        return $path;
    }

    private static function relativeFrom(string $root, string $path): string
    {
        $rootReal = realpath($root);
        $pathReal = realpath($path);
        if ($rootReal !== false) {
            $root = $rootReal;
        }
        if ($pathReal !== false) {
            $path = $pathReal;
        }

        $root = rtrim(str_replace('\\', '/', $root), '/').'/';
        $path = str_replace('\\', '/', $path);
        $rootCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($root) : $root;
        $pathCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
        if (! str_starts_with($pathCompare, $rootCompare)) {
            return '';
        }

        return self::relative(substr($path, strlen($root)));
    }

    /**
     * @return list<string>
     */
    private static function filesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getFilename() === '.gitignore') {
                continue;
            }
            $files[] = $file->getPathname();
        }

        return $files;
    }
}

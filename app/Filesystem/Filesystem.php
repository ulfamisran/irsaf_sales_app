<?php

namespace App\Filesystem;

use Illuminate\Filesystem\Filesystem as BaseFilesystem;

/**
 * Custom Filesystem untuk hosting yang menonaktifkan exec().
 * Override link() agar tidak error ketika symlink() dan exec() tidak tersedia.
 */
class Filesystem extends BaseFilesystem
{
    /**
     * Create a symlink to the target file or directory.
     * Menangani hosting yang mematikan exec() dan symlink().
     */
    public function link($target, $link): bool|null
    {
        if (! windows_os()) {
            if (function_exists('symlink')) {
                return symlink($target, $link);
            }
            if (function_exists('exec')) {
                return \exec('ln -s ' . escapeshellarg($target) . ' ' . escapeshellarg($link)) !== false;
            }
            // exec() disabled di shared hosting - return false, buat symlink manual via cPanel
            return false;
        }

        $mode = $this->isDirectory($target) ? 'J' : 'H';

        if (function_exists('exec')) {
            \exec("mklink /{$mode} " . escapeshellarg($link) . ' ' . escapeshellarg($target));
        }

        return null;
    }
}

<?php

/**
 * Helper penyimpanan file materi.
 * Pakai: helper('material_file');
 */

if (! function_exists('material_dir')) {
    /**
     * Folder penyimpanan file materi (di luar public/), diakhiri separator.
     */
    function material_dir(): string
    {
        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'materials' . DIRECTORY_SEPARATOR;
    }
}

if (! function_exists('material_file_path')) {
    /**
     * Path lengkap file materi, atau NULL jika nama kosong/tidak aman/file tidak ada.
     * basename() mencegah path traversal.
     */
    function material_file_path(?string $name): ?string
    {
        $name = basename((string) $name);

        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }

        $path = material_dir() . $name;

        return is_file($path) ? $path : null;
    }
}

if (! function_exists('material_delete_file')) {
    /**
     * Hapus file materi dari disk. Mengembalikan true jika berhasil terhapus.
     */
    function material_delete_file(?string $name): bool
    {
        $path = material_file_path($name);

        if ($path === null) {
            return false;
        }

        if (! @unlink($path)) {
            log_message('warning', 'Gagal menghapus file materi: ' . basename($path));

            return false;
        }

        return true;
    }
}

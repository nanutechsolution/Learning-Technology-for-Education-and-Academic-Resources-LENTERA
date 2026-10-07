<?php

/**
 * Helper logo sekolah (folder privat writable/uploads/branding).
 * Pakai: helper('branding');
 */

if (! function_exists('branding_dir')) {
    /**
     * Folder logo (di luar public/), diakhiri separator.
     */
    function branding_dir(): string
    {
        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'branding' . DIRECTORY_SEPARATOR;
    }
}

if (! function_exists('branding_logo_max_size')) {
    /**
     * Batas ukuran logo (1,5 MB). Harus tetap di bawah upload_max_filesize (Herd: 2M).
     */
    function branding_logo_max_size(): int
    {
        return 1572864;
    }
}

if (! function_exists('branding_logo_types')) {
    /**
     * Whitelist logo: ekstensi => MIME asli dan konstanta IMAGETYPE. SVG sengaja tidak ada.
     */
    function branding_logo_types(): array
    {
        return [
            'png'  => ['mime' => 'image/png',  'type' => IMAGETYPE_PNG],
            'jpg'  => ['mime' => 'image/jpeg', 'type' => IMAGETYPE_JPEG],
            'webp' => ['mime' => 'image/webp', 'type' => IMAGETYPE_WEBP],
        ];
    }
}

if (! function_exists('branding_logo_name_valid')) {
    /**
     * Nama file logo yang tercatat harus 32 hex + ekstensi whitelist.
     */
    function branding_logo_name_valid(?string $name): bool
    {
        return $name !== null && preg_match('/^[a-f0-9]{32}\.(png|jpg|webp)$/', $name) === 1;
    }
}

if (! function_exists('branding_logo_path')) {
    /**
     * Path lengkap file logo, atau NULL jika nama tidak valid / file tidak ada.
     */
    function branding_logo_path(?string $name): ?string
    {
        if (! branding_logo_name_valid($name)) {
            return null;
        }

        $path = branding_dir() . $name;

        return is_file($path) ? $path : null;
    }
}

if (! function_exists('branding_logo_mime')) {
    /**
     * Content-Type dari whitelist berdasarkan ekstensi nama file, atau NULL.
     */
    function branding_logo_mime(?string $name): ?string
    {
        if (! branding_logo_name_valid($name)) {
            return null;
        }

        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return branding_logo_types()[$ext]['mime'] ?? null;
    }
}

if (! function_exists('branding_delete_logo')) {
    /**
     * Hapus file logo. Mengembalikan true jika terhapus.
     */
    function branding_delete_logo(?string $name): bool
    {
        $path = branding_logo_path($name);

        if ($path === null) {
            return false;
        }

        if (! @unlink($path)) {
            log_message('warning', 'Gagal menghapus logo: ' . basename($path));

            return false;
        }

        return true;
    }
}

if (! function_exists('branding_upload_error_message')) {
    function branding_upload_error_message(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran logo melebihi batas maksimal.',
            UPLOAD_ERR_PARTIAL                        => 'Logo hanya terunggah sebagian. Coba unggah ulang.',
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION                      => 'Server gagal menyimpan file sementara. Hubungi admin.',
            default                                   => 'Logo gagal diunggah (kode ' . $code . ').',
        };
    }
}

if (! function_exists('branding_inspect_logo')) {
    /**
     * Periksa logo: error upload, ekstensi, ukuran, MIME asli (finfo), dan getimagesize().
     *
     * @return array{0: array|null, 1: list<string>} [info|null, daftar error]
     */
    function branding_inspect_logo(\CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        if (! $file->isValid()) {
            return [null, [branding_upload_error_message($file->getError())]];
        }

        if (! extension_loaded('fileinfo')) {
            return [null, ['Ekstensi PHP "fileinfo" belum aktif di server, sehingga logo tidak dapat diperiksa. Hubungi admin.']];
        }

        $types = branding_logo_types();
        $ext   = strtolower($file->getClientExtension());

        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        if (! isset($types[$ext])) {
            return [null, ['Format logo tidak diizinkan. Gunakan PNG, JPG, atau WEBP.']];
        }

        $size = (int) $file->getSize();

        if ($size > branding_logo_max_size()) {
            return [null, ['Ukuran logo melebihi batas maksimal 1,5 MB.']];
        }

        if ($size < 1) {
            return [null, ['File logo kosong.']];
        }

        $tmp  = $file->getTempName();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);

        if ($mime !== $types[$ext]['mime']) {
            return [null, ['Isi file tidak sesuai dengan ekstensi .' . $ext . ' (terdeteksi: ' . ($mime ?: 'tidak dikenal') . ').']];
        }

        $img = @getimagesize($tmp);

        if ($img === false || (int) ($img[2] ?? 0) !== $types[$ext]['type']) {
            return [null, ['File bukan gambar yang valid.']];
        }

        $w = (int) ($img[0] ?? 0);
        $h = (int) ($img[1] ?? 0);

        if ($w < 16 || $h < 16 || $w > 4000 || $h > 4000) {
            return [null, ['Dimensi logo harus antara 16x16 dan 4000x4000 piksel.']];
        }

        return [['ext' => $ext, 'mime' => $mime, 'size' => $size, 'width' => $w, 'height' => $h], []];
    }
}

if (! function_exists('branding_store_logo')) {
    /**
     * Pindahkan logo ke folder branding dengan nama acak buatan server.
     *
     * @return string|null nama file tersimpan, atau NULL jika gagal
     */
    function branding_store_logo(\CodeIgniter\HTTP\Files\UploadedFile $file, array $info): ?string
    {
        $dir = branding_dir();

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            log_message('error', 'Tidak dapat membuat folder ' . $dir);

            return null;
        }

        $name = bin2hex(random_bytes(16)) . '.' . $info['ext'];

        try {
            $file->move($dir, $name);
        } catch (\Throwable $e) {
            log_message('error', 'Gagal memindahkan logo: ' . $e->getMessage());

            return null;
        }

        return is_file($dir . $name) ? $name : null;
    }
}

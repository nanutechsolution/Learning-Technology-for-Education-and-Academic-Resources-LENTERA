<?php

/**
 * Helper file private untuk lampiran tugas dan file jawaban siswa.
 * Pakai: helper('assignment_file');
 */

if (! function_exists('assignment_dir')) {
    /**
     * Folder lampiran tugas (di luar public/), diakhiri separator.
     */
    function assignment_dir(): string
    {
        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'assignments' . DIRECTORY_SEPARATOR;
    }
}

if (! function_exists('submission_dir')) {
    /**
     * Folder file jawaban siswa (di luar public/), diakhiri separator.
     */
    function submission_dir(): string
    {
        return WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'submissions' . DIRECTORY_SEPARATOR;
    }
}

if (! function_exists('lentera_private_path')) {
    /**
     * Path lengkap file di folder $dir, atau NULL jika nama kosong/tidak aman/file tidak ada.
     * basename() mencegah path traversal.
     */
    function lentera_private_path(string $dir, ?string $name): ?string
    {
        $name = basename((string) $name);

        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }

        $path = $dir . $name;

        return is_file($path) ? $path : null;
    }
}

if (! function_exists('lentera_delete_private')) {
    /**
     * Hapus file dari folder $dir. Mengembalikan true jika berhasil terhapus.
     */
    function lentera_delete_private(string $dir, ?string $name): bool
    {
        $path = lentera_private_path($dir, $name);

        if ($path === null) {
            return false;
        }

        if (! @unlink($path)) {
            log_message('warning', 'Gagal menghapus file: ' . basename($path));

            return false;
        }

        return true;
    }
}

if (! function_exists('assignment_file_path')) {
    function assignment_file_path(?string $name): ?string
    {
        return lentera_private_path(assignment_dir(), $name);
    }
}

if (! function_exists('submission_file_path')) {
    function submission_file_path(?string $name): ?string
    {
        return lentera_private_path(submission_dir(), $name);
    }
}

if (! function_exists('assignment_delete_file')) {
    function assignment_delete_file(?string $name): bool
    {
        return lentera_delete_private(assignment_dir(), $name);
    }
}

if (! function_exists('submission_delete_file')) {
    function submission_delete_file(?string $name): bool
    {
        return lentera_delete_private(submission_dir(), $name);
    }
}

if (! function_exists('lentera_upload_max_size')) {
    function lentera_upload_max_size(): int
    {
        return 10485760; // 10 MB
    }
}

if (! function_exists('lentera_upload_rules')) {
    /**
     * Whitelist: ekstensi => daftar tipe isi file (hasil deteksi finfo) yang diterima.
     * File OOXML (docx/xlsx/pptx) sering terdeteksi sebagai zip; file Office lama
     * sebagai kontainer OLE. File PHP/teks/executable tidak masuk daftar mana pun.
     */
    function lentera_upload_rules(): array
    {
        $ole = ['application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'];
        $zip = ['application/zip', 'application/x-zip-compressed', 'application/x-zip'];

        return [
            'pdf'  => ['application/pdf'],
            'doc'  => array_merge(['application/msword'], $ole),
            'xls'  => array_merge(['application/vnd.ms-excel'], $ole),
            'ppt'  => array_merge(['application/vnd.ms-powerpoint'], $ole),
            'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $zip),
            'xlsx' => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $zip),
            'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $zip),
            'zip'  => $zip,
        ];
    }
}

if (! function_exists('lentera_upload_error_message')) {
    function lentera_upload_error_message(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas maksimal 10 MB.',
            UPLOAD_ERR_PARTIAL                        => 'File hanya terunggah sebagian. Coba unggah ulang.',
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION                      => 'Server gagal menyimpan file sementara. Hubungi admin.',
            default                                   => 'File gagal diunggah (kode ' . $code . ').',
        };
    }
}

if (! function_exists('lentera_incoming_file')) {
    /**
     * File yang dikirim lewat form pada field $field, atau NULL jika tidak ada file dipilih.
     */
    function lentera_incoming_file($request, string $field): ?\CodeIgniter\HTTP\Files\UploadedFile
    {
        $file = $request->getFile($field);

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }
}

if (! function_exists('lentera_clean_name')) {
    /**
     * Nama asli yang aman disimpan/ditampilkan: tanpa path dan karakter kontrol,
     * ekstensi memakai ekstensi yang sudah divalidasi.
     */
    function lentera_clean_name(string $name, string $ext): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME);
        $base = preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]+/u', '_', $base);
        $base = trim((string) $base);

        if ($base === '') {
            $base = 'berkas';
        }

        return mb_substr($base, 0, 150) . '.' . $ext;
    }
}

if (! function_exists('lentera_inspect_upload')) {
    /**
     * Periksa file: error upload, ekstensi, ukuran, dan tipe hasil deteksi isi file.
     *
     * @return array{0: array|null, 1: list<string>} [info|null, daftar error]
     */
    function lentera_inspect_upload(\CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        if (! $file->isValid()) {
            return [null, [lentera_upload_error_message($file->getError())]];
        }

        if (! extension_loaded('fileinfo')) {
            return [null, ['Ekstensi PHP "fileinfo" belum aktif di server, sehingga file tidak dapat diperiksa. Hubungi admin.']];
        }

        $rules = lentera_upload_rules();
        $ext   = strtolower($file->getClientExtension());

        if (! isset($rules[$ext])) {
            return [null, ['Tipe file tidak diizinkan. Gunakan PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, atau ZIP.']];
        }

        $size = (int) $file->getSize();

        if ($size > lentera_upload_max_size()) {
            return [null, ['Ukuran file melebihi batas maksimal 10 MB.']];
        }

        if ($size < 1) {
            return [null, ['File kosong tidak dapat diunggah.']];
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, $rules[$ext], true)) {
            return [null, ['Isi file tidak sesuai dengan ekstensi .' . $ext . ' (terdeteksi: ' . $mime . ').']];
        }

        return [[
            'ext'  => $ext,
            'mime' => $mime,
            'size' => $size,
            'name' => lentera_clean_name($file->getClientName(), $ext),
        ], []];
    }
}

if (! function_exists('lentera_store_upload')) {
    /**
     * Pindahkan file ke folder $dir dengan nama acak.
     *
     * @return string|null nama acak file yang tersimpan, atau NULL jika gagal
     */
    function lentera_store_upload(\CodeIgniter\HTTP\Files\UploadedFile $file, string $dir, array $info): ?string
    {
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            log_message('error', 'Tidak dapat membuat folder ' . $dir);

            return null;
        }

        $newName = bin2hex(random_bytes(16)) . '.' . $info['ext'];

        try {
            $file->move($dir, $newName);
        } catch (\Throwable $e) {
            log_message('error', 'Gagal memindahkan file: ' . $e->getMessage());

            return null;
        }

        return is_file($dir . $newName) ? $newName : null;
    }
}
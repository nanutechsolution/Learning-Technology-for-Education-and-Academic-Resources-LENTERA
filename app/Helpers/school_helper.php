<?php

/**
 * Helper identitas sekolah untuk branding aplikasi.
 * Pakai: helper('school');
 * Data dibaca satu kali per request (cache statis). Jika tabel kosong atau belum
 * dimigrasi, semua fungsi mengembalikan teks default lama tanpa error.
 */

if (! function_exists('school_settings')) {
    /**
     * Baris pengaturan sekolah, atau array kosong. Satu query per request.
     */
    function school_settings(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = [];

        try {
            $row = (new \App\Models\SchoolSettingModel())->current();

            if (is_array($row)) {
                $cache = $row;
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Pengaturan sekolah tidak dapat dibaca: ' . $e->getMessage());
        }

        return $cache;
    }
}

if (! function_exists('school_name')) {
    /**
     * Nama sekolah dari pengaturan, atau string kosong jika belum ada.
     */
    function school_name(): string
    {
        return trim((string) (school_settings()['school_name'] ?? ''));
    }
}

if (! function_exists('school_display_name')) {
    /**
     * Nama sekolah untuk tampilan (navbar, login); default lama jika kosong.
     */
    function school_display_name(): string
    {
        $name = school_name();

        return $name !== '' ? $name : 'SMP Negeri 1 Wewewa Timur';
    }
}

if (! function_exists('school_brand_sub')) {
    /**
     * Teks kecil di bawah "LENTERA" pada sidebar.
     */
    function school_brand_sub(): string
    {
        $name = school_name();

        return $name !== '' ? $name : 'SMPN 1 Wewewa Timur';
    }
}

if (! function_exists('school_footer_place')) {
    /**
     * Teks footer: "<nama sekolah>, Kabupaten <kabupaten>".
     */
    function school_footer_place(): string
    {
        $name = school_name();

        if ($name === '') {
            return 'SMP Negeri 1 Wewewa Timur, Kabupaten Sumba Barat Daya';
        }

        $regency = trim((string) (school_settings()['regency'] ?? ''));

        return $regency !== '' ? $name . ', Kabupaten ' . $regency : $name;
    }
}

if (! function_exists('school_page_title')) {
    /**
     * Judul tab (belum di-escape): "<halaman> | LENTERA | <nama sekolah>".
     */
    function school_page_title(string $page): string
    {
        $name = school_name();

        return $page . ' | LENTERA' . ($name !== '' ? ' | ' . $name : '');
    }
}

if (! function_exists('school_logo_url')) {
    /**
     * URL publik logo, atau NULL jika belum ada logo / file tidak ada di disk.
     */
    function school_logo_url(): ?string
    {
        helper('branding');

        $row  = school_settings();
        $name = $row['logo_path'] ?? null;

        if (branding_logo_path($name) === null) {
            return null;
        }

        return base_url('school-logo') . '?v=' . rawurlencode((string) ($row['updated_at'] ?? ''));
    }
}

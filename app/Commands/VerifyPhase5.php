<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class VerifyPhase5 extends BaseCommand
{
    protected $group       = 'Lentera';
    protected $name        = 'lentera:verify5';
    protected $description = 'Memeriksa konsistensi Pengaturan Sekolah (satu baris, field wajib, file logo, folder branding). Hanya membaca.';

    private int $problems = 0;

    public function run(array $params)
    {
        helper('branding');

        $db = \Config\Database::connect();

        CLI::write('LENTERA - verifikasi Pengaturan Sekolah', 'yellow');
        CLI::newLine();

        if (! $db->tableExists('school_settings')) {
            CLI::write('  [GAGAL] Tabel school_settings belum ada. Jalankan: php spark migrate', 'red');

            return EXIT_ERROR;
        }

        // 1. Baris tunggal dan field wajib
        $this->section('Data pengaturan');

        $total = $db->table('school_settings')->countAllResults();
        $this->check($total === 1, 'Tepat satu baris di school_settings', "school_settings berisi {$total} baris (harus tepat 1).");

        $row = $db->table('school_settings')->orderBy('id', 'ASC')->get(1)->getRowArray();

        if ($row === null) {
            CLI::newLine();
            CLI::write($this->problems . ' masalah ditemukan.', 'red');

            return EXIT_ERROR;
        }

        $name = trim((string) ($row['school_name'] ?? ''));
        $this->check($name !== '', 'Nama sekolah terisi', 'Nama sekolah kosong.');
        $this->check(mb_strlen($name) <= 150, 'Nama sekolah tidak melebihi 150 karakter', 'Nama sekolah lebih dari 150 karakter.');

        $npsn = $row['npsn'] ?? null;
        $this->check($npsn === null || preg_match('/^[0-9]{8}$/', (string) $npsn) === 1, 'NPSN kosong atau 8 digit angka', 'NPSN tidak berformat 8 digit angka.');

        $email = $row['email'] ?? null;
        $this->check($email === null || filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'Email kosong atau valid', 'Email tersimpan tidak valid.');

        $site = $row['website'] ?? null;
        $this->check(
            $site === null || preg_match('#^https?://#i', (string) $site) === 1,
            'Website kosong atau diawali http(s)://',
            'Website tersimpan tidak diawali http:// atau https://.'
        );

        $phone = $row['phone'] ?? null;
        $this->check($phone === null || preg_match('/^[0-9+()\-\s.]+$/', (string) $phone) === 1, 'Telepon kosong atau hanya karakter telepon', 'Telepon tersimpan berisi karakter tidak wajar.');

        $emptyStrings = 0;
        foreach (['npsn', 'address', 'village', 'district', 'regency', 'province', 'email', 'phone', 'website', 'logo_path', 'principal_name', 'principal_nip'] as $col) {
            if (($row[$col] ?? null) === '') {
                $emptyStrings++;
            }
        }
        $this->check($emptyStrings === 0, 'Tidak ada kolom opsional berisi string kosong (harus NULL)', "{$emptyStrings} kolom opsional berisi string kosong, bukan NULL.");

        // 2. Folder branding
        $this->section('Folder branding');

        $dir        = branding_dir();
        $dirExists  = is_dir($dir);
        $writeReal  = realpath(WRITEPATH);
        $publicReal = realpath(FCPATH);

        $this->check(
            $writeReal !== false && $publicReal !== false && ! str_starts_with($writeReal, $publicReal),
            'WRITEPATH berada di luar public/',
            'WRITEPATH berada di dalam public/; file logo bisa diakses langsung!'
        );

        if ($dirExists) {
            $real = realpath($dir);
            $this->check(
                $real !== false && $publicReal !== false && ! str_starts_with($real, $publicReal),
                'Folder branding berada di luar public/',
                'Folder branding berada di dalam public/ sehingga file bisa diakses langsung!'
            );
            $this->check(is_writable($dir), 'Folder branding dapat ditulis', 'Folder branding tidak dapat ditulis.');
        } else {
            CLI::write('  [info] Folder branding belum ada (dibuat otomatis saat logo pertama diunggah).', 'light_gray');
        }

        // 3. File logo vs database
        $this->section('Logo');

        $logo = $row['logo_path'] ?? null;

        if ($logo === null) {
            CLI::write('  [info] Belum ada logo tercatat; aplikasi memakai ikon bawaan.', 'light_gray');
        } else {
            $valid = branding_logo_name_valid((string) $logo);
            $this->check($valid, 'Nama logo di DB berformat 32 hex + png/jpg/webp', 'Nama logo di DB tidak aman/tidak sesuai format: ' . substr((string) $logo, 0, 60));

            if ($valid) {
                $path = branding_logo_path((string) $logo);
                $this->check($path !== null, 'File logo ada di disk', 'File logo tercatat di DB tetapi tidak ada di disk: ' . $logo);

                if ($path !== null) {
                    $size = (int) filesize($path);
                    $this->check($size > 0 && $size <= branding_logo_max_size(), 'Ukuran file logo wajar (' . $size . ' byte)', 'Ukuran file logo di luar batas (' . $size . ' byte).');

                    $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $types = branding_logo_types();
                    $mime  = extension_loaded('fileinfo') ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : null;
                    $img   = @getimagesize($path);

                    $this->check(
                        $mime !== null && isset($types[$ext]) && $mime === $types[$ext]['mime'],
                        'MIME asli file logo cocok dengan ekstensi (' . ($mime ?: '?') . ')',
                        'MIME asli file logo (' . ($mime ?: 'tidak terdeteksi') . ') tidak cocok dengan ekstensi .' . $ext . '.'
                    );
                    $this->check(
                        $img !== false && isset($types[$ext]) && (int) $img[2] === $types[$ext]['type'],
                        'getimagesize() mengenali file logo sebagai gambar yang valid',
                        'getimagesize() gagal atau tipe gambar tidak cocok dengan ekstensi.'
                    );
                }
            }
        }

        // File yatim di folder branding
        if ($dirExists) {
            $onDisk = [];

            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..' || $f === '.gitkeep' || $f === 'index.html' || is_dir($dir . $f)) {
                    continue;
                }
                $onDisk[] = $f;
            }

            $orphans = array_values(array_diff($onDisk, $logo === null ? [] : [(string) $logo]));
            $this->check(
                $orphans === [],
                'Tidak ada file yatim di folder branding (' . count($onDisk) . ' file di disk)',
                count($orphans) . ' file yatim di folder branding (ada di disk, tidak dirujuk DB): ' . implode(', ', array_slice($orphans, 0, 5)) . (count($orphans) > 5 ? ', ...' : '')
            );

            $unsafe = array_values(array_filter($onDisk, static fn ($f) => ! branding_logo_name_valid($f)));
            $this->check($unsafe === [], 'Semua file di folder branding bernama acak yang aman', 'File dengan nama tidak aman di folder branding: ' . implode(', ', array_slice($unsafe, 0, 5)));
        }

        // 4. Lingkungan PHP
        $this->section('Lingkungan PHP');

        $this->check(extension_loaded('fileinfo'), 'Ekstensi fileinfo aktif', 'Ekstensi fileinfo TIDAK aktif; upload logo akan ditolak.');

        $upload = $this->toBytes((string) ini_get('upload_max_filesize'));
        $post   = $this->toBytes((string) ini_get('post_max_size'));
        $this->check(
            $upload >= branding_logo_max_size(),
            'upload_max_filesize (' . ini_get('upload_max_filesize') . ') cukup untuk batas logo',
            'upload_max_filesize (' . ini_get('upload_max_filesize') . ') lebih kecil dari batas logo ' . branding_logo_max_size() . ' byte.'
        );
        $this->check(
            $post >= $upload,
            'post_max_size (' . ini_get('post_max_size') . ') >= upload_max_filesize',
            'post_max_size (' . ini_get('post_max_size') . ') lebih kecil dari upload_max_filesize.'
        );

        CLI::newLine();

        if ($this->problems === 0) {
            CLI::write('Semua pemeriksaan lolos.', 'green');

            return EXIT_SUCCESS;
        }

        CLI::write($this->problems . ' masalah ditemukan.', 'red');

        return EXIT_ERROR;
    }

    private function section(string $title): void
    {
        CLI::newLine();
        CLI::write($title, 'cyan');
    }

    private function check(bool $ok, string $passMsg, string $failMsg): void
    {
        if ($ok) {
            CLI::write('  [OK]    ' . $passMsg, 'green');

            return;
        }

        $this->problems++;
        CLI::write('  [GAGAL] ' . $failMsg, 'red');
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $num  = (int) $value;

        return match ($unit) {
            'g'     => $num * 1073741824,
            'm'     => $num * 1048576,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}
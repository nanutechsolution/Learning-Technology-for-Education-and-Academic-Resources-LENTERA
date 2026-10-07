<?php

namespace App\Commands;

use App\Models\SchoolSettingModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SmokeSchoolSettings extends BaseCommand
{
    protected $group       = 'Lentera';
    protected $name        = 'lentera:smoke5';
    protected $description = 'Uji cepat SchoolSettingModel (STEP S1). Semua data uji dibatalkan (rollback) di akhir.';

    private int $passed = 0;
    private int $failed = 0;

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('school_settings')) {
            CLI::error('Tabel school_settings belum ada. Jalankan: php spark migrate');

            return EXIT_ERROR;
        }

        CLI::write('LENTERA - uji SchoolSettingModel (data uji di-rollback)', 'yellow');
        CLI::newLine();

        $db->transBegin();

        try {
            $this->runChecks();
        } catch (\Throwable $e) {
            $this->failed++;
            CLI::write('  [GAGAL] Exception: ' . $e->getMessage(), 'red');
        } finally {
            $db->transRollback();
        }

        CLI::newLine();
        CLI::write("Hasil: {$this->passed} lolos, {$this->failed} gagal. Semua data uji sudah dibatalkan.", $this->failed === 0 ? 'green' : 'red');

        return $this->failed === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function runChecks(): void
    {
        $db    = \Config\Database::connect();
        $m     = new SchoolSettingModel();
        $count = static fn(): int => $db->table('school_settings')->countAllResults();

        // --- Validasi (tanpa menyimpan)
        $invalid = [
            'Nama sekolah kosong'            => ['school_name' => ''],
            'Nama sekolah 151 karakter'      => ['school_name' => str_repeat('x', 151)],
            'NPSN bukan 8 digit'             => ['npsn' => '123'],
            'Email tidak valid'              => ['email' => 'bukan-email'],
            'Telepon berisi huruf'           => ['phone' => '0812abc'],
            'Website skema ftp ditolak'      => ['website' => 'ftp://contoh.com'],
            'Website skema javascript ditolak' => ['website' => 'javascript:alert(1)'],
            'NIP berisi huruf'               => ['principal_nip' => '1980abc'],
            'logo_path berisi path traversal' => ['logo_path' => '../../.env'],
            'logo_path ekstensi svg ditolak' => ['logo_path' => str_repeat('a', 32) . '.svg'],
        ];

        foreach ($invalid as $label => $data) {
            $this->ok($m->validate($data) === false, $label . ' ditolak');
        }

        $this->ok($m->validate([
            'school_name'    => 'SMP Uji',
            'npsn'           => '12345678',
            'email'          => 'uji@contoh.sch.id',
            'phone'          => '+62 (380) 123-456',
            'website'        => 'https://contoh.sch.id',
            'principal_nip'  => '198001012005011001',
            'logo_path'      => str_repeat('a', 32) . '.png',
        ]) === true, 'Data valid lengkap diterima');

        $this->ok($m->validate(['npsn' => '', 'email' => '', 'phone' => '', 'website' => '', 'principal_nip' => '']) === true, 'Kolom opsional kosong diterima');

        // --- Baris tunggal
        $existing = $m->current();

        if ($existing === null) {
            $id = $m->insert(['school_name' => 'UJI-5 SEKOLAH']);
            $this->ok($id !== false && $count() === 1, 'Insert pertama berhasil (tabel semula kosong)');
        } else {
            CLI::write('  [info]  Tabel sudah berisi satu baris; uji dijalankan pada baris itu (di-rollback).', 'light_gray');
            $this->ok($count() === 1, 'Tabel berisi tepat satu baris');
        }

        $row   = $m->current();
        $rowId = (int) $row['id'];

        $this->ok($m->insert(['school_name' => 'UJI-5 KEDUA']) === false, 'Insert kedua ditolak');
        $this->ok($m->errors() !== [], 'Insert kedua menghasilkan pesan error');
        $this->ok($count() === 1, 'Jumlah baris tetap satu setelah insert kedua ditolak');

        // --- saveSettings: update baris tunggal
        $ok = $m->saveSettings([
            'school_name'    => '  SMP UJI5  ',
            'npsn'           => '',
            'address'        => '',
            'village'        => '',
            'district'       => '',
            'email'          => '',
            'phone'          => '',
            'website'        => '',
            'principal_name' => '',
            'principal_nip'  => '',
            'logo_path'      => '',
        ]);
        $row = $m->current();
        $this->ok($ok === true && $count() === 1 && (int) $row['id'] === $rowId, 'saveSettings memperbarui baris yang sama (tidak ada baris baru)');
        $this->ok($row['school_name'] === 'SMP UJI5', 'Nama sekolah di-trim');
        $this->ok($row['npsn'] === null && $row['address'] === null && $row['email'] === null && $row['website'] === null && $row['phone'] === null, 'String kosong disimpan sebagai NULL');
        $this->ok($row['principal_name'] === null && $row['principal_nip'] === null && $row['logo_path'] === null, 'Kepala sekolah dan logo kosong disimpan sebagai NULL');
        $this->ok(! empty($row['updated_at']), 'updated_at terisi');

        // --- Simpan data lengkap
        $logo = str_repeat('b', 32) . '.webp';
        $ok   = $m->saveSettings([
            'school_name'    => 'SMP UJI5',
            'npsn'           => '12345678',
            'email'          => 'uji@contoh.sch.id',
            'phone'          => '0812-3456-7890',
            'website'        => 'https://contoh.sch.id',
            'principal_name' => 'Nama Uji',
            'principal_nip'  => '198001012005011001',
            'logo_path'      => $logo,
        ]);
        $row = $m->current();
        $this->ok($ok === true && $row['npsn'] === '12345678' && $row['email'] === 'uji@contoh.sch.id' && $row['website'] === 'https://contoh.sch.id', 'Data lengkap tersimpan');
        $this->ok($row['logo_path'] === $logo, 'logo_path tersimpan');

        // --- Penyimpanan tidak valid tidak mengubah data
        $this->ok($m->saveSettings(['school_name' => '', 'email' => 'x']) === false, 'saveSettings dengan data tidak valid ditolak');
        $this->ok($m->errors() !== [], 'Penolakan menghasilkan pesan error');
        $row = $m->current();
        $this->ok($row['school_name'] === 'SMP UJI5' && $row['email'] === 'uji@contoh.sch.id', 'Data lama utuh setelah simpan tidak valid');

        // --- Hapus logo (set NULL)
        $ok  = $m->saveSettings(['school_name' => 'SMP UJI5', 'logo_path' => null]);
        $row = $m->current();
        $this->ok($ok === true && $row['logo_path'] === null && $count() === 1, 'logo_path dapat dikosongkan (NULL)');
    }

    private function ok(bool $cond, string $label): void
    {
        if ($cond) {
            $this->passed++;
            CLI::write('  [OK]    ' . $label, 'green');

            return;
        }

        $this->failed++;
        CLI::write('  [GAGAL] ' . $label, 'red');
    }
}

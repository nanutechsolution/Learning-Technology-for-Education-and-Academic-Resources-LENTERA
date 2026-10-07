<?php

namespace App\Database\Seeds;

use App\Models\SchoolSettingModel;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

class SchoolSettingSeeder extends Seeder
{
    public function run(): void
    {
        $model = new SchoolSettingModel();

        if ($model->current() !== null) {
            CLI::write('school_settings sudah berisi data; seeder dilewati (tidak ada yang diubah).', 'yellow');

            return;
        }

        $id = $model->insert([
            'school_name' => 'SMP Negeri 1 Wewewa Timur',
            'regency'     => 'Sumba Barat Daya',
            'province'    => 'Nusa Tenggara Timur',
        ]);

        if ($id === false) {
            CLI::error('Seeder gagal: ' . implode(' ', array_values($model->errors())));

            return;
        }

        CLI::write('school_settings diisi (id ' . $id . ').', 'green');
    }
}

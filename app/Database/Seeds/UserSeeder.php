<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * PASSWORD DEMO UNTUK DEVELOPMENT SAJA.
     * WAJIB diganti sebelum aplikasi digunakan di production.
     */
    private const DEMO_PASSWORD = 'Lentera@123';

    public function run()
    {
        $model = new UserModel();

        $users = [
            [
                'name'     => 'Administrator LENTERA',
                'username' => 'admin',
                'email'    => 'admin@lentera.test',
                'role'     => 'admin',
            ],
            [
                'name'     => 'Guru Demo',
                'username' => 'guru.demo',
                'email'    => 'guru.demo@lentera.test',
                'role'     => 'guru',
            ],
            [
                'name'     => 'Siswa Demo',
                'username' => 'siswa.demo',
                'email'    => null,
                'role'     => 'siswa',
            ],
        ];

        foreach ($users as $user) {
            if ($model->findByUsername($user['username']) !== null) {
                CLI::write('[SKIP] User sudah ada: ' . $user['username'], 'yellow');
                continue;
            }

            $user['password'] = self::DEMO_PASSWORD;
            $user['is_active'] = 1;

            if ($model->insert($user) === false) {
                throw new \RuntimeException(
                    'Gagal membuat user ' . $user['username'] . ': ' . implode('; ', $model->errors())
                );
            }

            CLI::write('[OK]   User dibuat: ' . $user['username'] . ' (' . $user['role'] . ')', 'green');
        }

        CLI::newLine();
        CLI::write('PERINGATAN: password demo hanya untuk development.', 'red');
        CLI::write('WAJIB diganti sebelum aplikasi digunakan di production.', 'red');
        CLI::newLine();
    }
}

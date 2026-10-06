<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name' => [
            'label' => 'Nama',
            'rules' => 'required|min_length[3]|max_length[100]',
        ],
        'username' => [
            'label' => 'Username',
            'rules' => 'required|min_length[3]|max_length[50]|regex_match[/^[A-Za-z0-9._-]+$/]|is_unique[users.username,id,{id}]',
        ],
        'email' => [
            'label' => 'Email',
            'rules' => 'permit_empty|valid_email|max_length[150]|is_unique[users.email,id,{id}]',
        ],
        'password' => [
            'label' => 'Kata sandi',
            'rules' => 'required|min_length[8]|max_length[72]',
        ],
        'role' => [
            'label' => 'Role',
            'rules' => 'required|in_list[admin,guru,siswa]',
        ],
        'is_active' => [
            'label' => 'Status aktif',
            'rules' => 'permit_empty|in_list[0,1]',
        ],
    ];

    protected $skipValidation     = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['prepareData'];
    protected $beforeUpdate   = ['prepareData'];

    /**
     * Hash password dan ubah email kosong menjadi NULL sebelum disimpan.
     * Password yang dikirim ke model harus berupa teks biasa (plain text).
     */
    protected function prepareData(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        if (array_key_exists('password', $data['data'])) {
            if ($data['data']['password'] === '' || $data['data']['password'] === null) {
                unset($data['data']['password']);
            } else {
                $data['data']['password'] = password_hash((string) $data['data']['password'], PASSWORD_DEFAULT);
            }
        }

        if (array_key_exists('email', $data['data']) && trim((string) $data['data']['email']) === '') {
            $data['data']['email'] = null;
        }

        return $data;
    }

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    public function countByRole(string $role): int
    {
        return $this->where('role', $role)->countAllResults();
    }
}

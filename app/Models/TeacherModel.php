<?php

namespace App\Models;

use CodeIgniter\Model;

class TeacherModel extends Model
{
    protected $table            = 'teachers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'nip',
        'phone',
        'address',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'user_id' => [
            'label' => 'Akun pengguna',
            'rules' => 'required|is_natural_no_zero|is_not_unique[users.id]|is_unique[teachers.user_id,id,{id}]',
        ],
        'nip' => [
            'label' => 'NIP',
            'rules' => 'permit_empty|max_length[30]|is_unique[teachers.nip,id,{id}]',
        ],
        'phone' => [
            'label' => 'Nomor telepon',
            'rules' => 'permit_empty|max_length[20]|regex_match[/^[0-9+ -]+$/]',
        ],
        'address' => [
            'label' => 'Alamat',
            'rules' => 'permit_empty|max_length[500]',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['normalizeData'];
    protected $beforeUpdate   = ['normalizeData'];

    protected function normalizeData(array $data): array
    {
        if (
            isset($data['data']) && is_array($data['data'])
            && array_key_exists('nip', $data['data'])
            && trim((string) $data['data']['nip']) === ''
        ) {
            $data['data']['nip'] = null;
        }

        return $data;
    }

    private function baseQuery(): static
    {
        return $this->select('teachers.*, users.name, users.username, users.email, users.is_active')
            ->join('users', 'users.id = teachers.user_id');
    }

    public function getAllWithUser(): array
    {
        return $this->baseQuery()
            ->orderBy('users.name', 'ASC')
            ->findAll();
    }

    public function findWithUser(int $id): ?array
    {
        return $this->baseQuery()
            ->where('teachers.id', $id)
            ->first();
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->baseQuery()
            ->where('teachers.user_id', $userId)
            ->first();
    }
}

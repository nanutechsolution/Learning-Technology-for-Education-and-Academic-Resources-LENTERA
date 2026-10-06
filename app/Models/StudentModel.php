<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table            = 'students';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'nis',
        'nisn',
        'class_id',
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
            'rules' => 'required|is_natural_no_zero|is_not_unique[users.id]|is_unique[students.user_id,id,{id}]',
        ],
        'nis' => [
            'label' => 'NIS',
            'rules' => 'required|max_length[20]|is_unique[students.nis,id,{id}]',
        ],
        'nisn' => [
            'label' => 'NISN',
            'rules' => 'permit_empty|is_natural|exact_length[10]|is_unique[students.nisn,id,{id}]',
        ],
        'class_id' => [
            'label' => 'Kelas',
            'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[classes.id]',
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
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        foreach (['nisn', 'class_id'] as $field) {
            if (array_key_exists($field, $data['data']) && trim((string) $data['data'][$field]) === '') {
                $data['data'][$field] = null;
            }
        }

        return $data;
    }

    private function baseQuery(): static
    {
        return $this->select('students.*, users.name, users.username, users.email, users.is_active, classes.name AS class_name, classes.grade AS class_grade')
            ->join('users', 'users.id = students.user_id')
            ->join('classes', 'classes.id = students.class_id', 'left');
    }

    public function getAllWithRelations(): array
    {
        return $this->baseQuery()
            ->orderBy('users.name', 'ASC')
            ->findAll();
    }

    public function findWithRelations(int $id): ?array
    {
        return $this->baseQuery()
            ->where('students.id', $id)
            ->first();
    }

    public function findByUserId(int $userId): ?array
    {
        return $this->baseQuery()
            ->where('students.user_id', $userId)
            ->first();
    }

    public function getByClass(int $classId): array
    {
        return $this->baseQuery()
            ->where('students.class_id', $classId)
            ->orderBy('users.name', 'ASC')
            ->findAll();
    }
}

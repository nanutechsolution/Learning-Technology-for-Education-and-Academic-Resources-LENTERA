<?php

namespace App\Models;

use CodeIgniter\Model;

class SubjectModel extends Model
{
    protected $table            = 'subjects';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'code' => [
            'label' => 'Kode mata pelajaran',
            'rules' => 'required|min_length[2]|max_length[20]|regex_match[/^[A-Za-z0-9._-]+$/]|is_unique[subjects.code,id,{id}]',
        ],
        'name' => [
            'label' => 'Nama mata pelajaran',
            'rules' => 'required|min_length[3]|max_length[100]',
        ],
        'description' => [
            'label' => 'Deskripsi',
            'rules' => 'permit_empty|max_length[1000]',
        ],
        'is_active' => [
            'label' => 'Status aktif',
            'rules' => 'permit_empty|in_list[0,1]',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    public function getActive(): array
    {
        return $this->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}

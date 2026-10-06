<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicYearModel extends Model
{
    protected $table            = 'academic_years';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'name' => [
            'label' => 'Tahun akademik',
            'rules' => 'required|exact_length[9]|regex_match[/^[0-9][0-9][0-9][0-9]\/[0-9][0-9][0-9][0-9]$/]|is_unique[academic_years.name,id,{id}]',
        ],
        'start_date' => [
            'label' => 'Tanggal mulai',
            'rules' => 'required|valid_date[Y-m-d]',
        ],
        'end_date' => [
            'label' => 'Tanggal selesai',
            'rules' => 'required|valid_date[Y-m-d]',
        ],
        'is_active' => [
            'label' => 'Status aktif',
            'rules' => 'permit_empty|in_list[0,1]',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    public function getActive(): ?array
    {
        return $this->where('is_active', 1)->first();
    }

    /**
     * Mengaktifkan satu tahun akademik dan menonaktifkan yang lain.
     */
    public function activate(int $id): bool
    {
        $this->db->transStart();

        $this->builder()->where('is_active', 1)->update(['is_active' => 0]);
        $this->builder()->where('id', $id)->update([
            'is_active'  => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Tanggal selesai harus sama dengan atau setelah tanggal mulai.
     */
    public function isDateRangeValid(string $startDate, string $endDate): bool
    {
        $start = strtotime($startDate);
        $end   = strtotime($endDate);

        if ($start === false || $end === false) {
            return false;
        }

        return $end >= $start;
    }
}

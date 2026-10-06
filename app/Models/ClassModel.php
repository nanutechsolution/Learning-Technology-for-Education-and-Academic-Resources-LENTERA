<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassModel extends Model
{
    protected $table            = 'classes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'academic_year_id',
        'name',
        'grade',
        'homeroom_teacher_id',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'academic_year_id' => [
            'label' => 'Tahun akademik',
            'rules' => 'required|is_natural_no_zero|is_not_unique[academic_years.id]',
        ],
        'name' => [
            'label' => 'Nama kelas',
            'rules' => 'required|min_length[1]|max_length[50]',
        ],
        'grade' => [
            'label' => 'Tingkat',
            'rules' => 'required|in_list[7,8,9]',
        ],
        'homeroom_teacher_id' => [
            'label' => 'Wali kelas',
            'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[teachers.id]',
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
            && array_key_exists('homeroom_teacher_id', $data['data'])
            && trim((string) $data['data']['homeroom_teacher_id']) === ''
        ) {
            $data['data']['homeroom_teacher_id'] = null;
        }

        return $data;
    }

    private function baseQuery(): static
    {
        return $this->select(
            'classes.*, '
                . 'academic_years.name AS academic_year_name, '
                . 'users.name AS homeroom_teacher_name, '
                . '(SELECT COUNT(*) FROM students WHERE students.class_id = classes.id) AS student_count',
            false
        )
            ->join('academic_years', 'academic_years.id = classes.academic_year_id')
            ->join('teachers', 'teachers.id = classes.homeroom_teacher_id', 'left')
            ->join('users', 'users.id = teachers.user_id', 'left');
    }

    public function getAllWithRelations(): array
    {
        return $this->baseQuery()
            ->orderBy('academic_years.name', 'DESC')
            ->orderBy('classes.grade', 'ASC')
            ->orderBy('classes.name', 'ASC')
            ->findAll();
    }

    public function findWithRelations(int $id): ?array
    {
        return $this->baseQuery()
            ->where('classes.id', $id)
            ->first();
    }

    public function getByAcademicYear(int $academicYearId): array
    {
        return $this->baseQuery()
            ->where('classes.academic_year_id', $academicYearId)
            ->orderBy('classes.grade', 'ASC')
            ->orderBy('classes.name', 'ASC')
            ->findAll();
    }

    /**
     * Cek nama kelas yang sama pada tahun akademik yang sama.
     * Saat edit, isi $ignoreId dengan id kelas yang sedang diedit.
     */
    public function isNameTaken(string $name, int $academicYearId, ?int $ignoreId = null): bool
    {
        $this->where('academic_year_id', $academicYearId)
            ->where('name', $name);

        if ($ignoreId !== null) {
            $this->where('id !=', $ignoreId);
        }

        return $this->countAllResults() > 0;
    }
}

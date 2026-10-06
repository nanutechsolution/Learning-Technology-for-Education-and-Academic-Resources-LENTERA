<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseModel extends Model
{
    protected $table            = 'courses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'subject_id',
        'teacher_id',
        'class_id',
        'academic_year_id',
        'title',
        'description',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'subject_id' => [
            'label' => 'Mata pelajaran',
            'rules' => 'required|is_natural_no_zero|is_not_unique[subjects.id]',
        ],
        'teacher_id' => [
            'label' => 'Guru',
            'rules' => 'required|is_natural_no_zero|is_not_unique[teachers.id]',
        ],
        'class_id' => [
            'label' => 'Kelas',
            'rules' => 'required|is_natural_no_zero|is_not_unique[classes.id]',
        ],
        'academic_year_id' => [
            'label' => 'Tahun akademik',
            'rules' => 'required|is_natural_no_zero|is_not_unique[academic_years.id]',
        ],
        'title' => [
            'label' => 'Judul course',
            'rules' => 'required|min_length[3]|max_length[150]',
        ],
        'description' => [
            'label' => 'Deskripsi',
            'rules' => 'permit_empty|max_length[2000]',
        ],
        'status' => [
            'label' => 'Status',
            'rules' => 'required|in_list[draft,active,archived]',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    private const RELATION_FIELDS =
    'courses.*, '
        . 'subjects.code AS subject_code, '
        . 'subjects.name AS subject_name, '
        . 'users.name AS teacher_name, '
        . 'classes.name AS class_name, '
        . 'classes.grade AS class_grade, '
        . 'academic_years.name AS academic_year_name, '
        . '(SELECT COUNT(*) FROM course_students WHERE course_students.course_id = courses.id) AS student_count';

    private function baseQuery(): static
    {
        return $this->select(self::RELATION_FIELDS, false)
            ->join('subjects', 'subjects.id = courses.subject_id')
            ->join('teachers', 'teachers.id = courses.teacher_id')
            ->join('users', 'users.id = teachers.user_id')
            ->join('classes', 'classes.id = courses.class_id')
            ->join('academic_years', 'academic_years.id = courses.academic_year_id');
    }

    public function getAllWithRelations(): array
    {
        return $this->baseQuery()
            ->orderBy('courses.created_at', 'DESC')
            ->findAll();
    }

    public function findWithRelations(int $id): ?array
    {
        return $this->baseQuery()
            ->where('courses.id', $id)
            ->first();
    }

    public function getByTeacher(int $teacherId): array
    {
        return $this->baseQuery()
            ->where('courses.teacher_id', $teacherId)
            ->orderBy('courses.title', 'ASC')
            ->findAll();
    }

    public function getByStudent(int $studentId): array
    {
        return $this->baseQuery()
            ->join('course_students', 'course_students.course_id = courses.id')
            ->where('course_students.student_id', $studentId)
            ->orderBy('courses.title', 'ASC')
            ->findAll();
    }

    public function countByTeacher(int $teacherId): int
    {
        return $this->where('teacher_id', $teacherId)->countAllResults();
    }

    public function countClassesByTeacher(int $teacherId): int
    {
        $row = $this->select('COUNT(DISTINCT class_id) AS total', false)
            ->where('teacher_id', $teacherId)
            ->first();

        return (int) ($row['total'] ?? 0);
    }
}

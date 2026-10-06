<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseStudentModel extends Model
{
    protected $table            = 'course_students';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'course_id',
        'student_id',
        'enrolled_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'course_id' => [
            'label' => 'Course',
            'rules' => 'required|is_natural_no_zero|is_not_unique[courses.id]',
        ],
        'student_id' => [
            'label' => 'Siswa',
            'rules' => 'required|is_natural_no_zero|is_not_unique[students.id]',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    public function isEnrolled(int $courseId, int $studentId): bool
    {
        return $this->where('course_id', $courseId)
            ->where('student_id', $studentId)
            ->countAllResults() > 0;
    }

    /**
     * Mendaftarkan siswa ke course. Mengembalikan false jika sudah terdaftar
     * atau jika validasi gagal.
     */
    public function enroll(int $courseId, int $studentId): bool
    {
        if ($this->isEnrolled($courseId, $studentId)) {
            return false;
        }

        return $this->insert([
            'course_id'   => $courseId,
            'student_id'  => $studentId,
            'enrolled_at' => date('Y-m-d H:i:s'),
        ]) !== false;
    }

    public function unenroll(int $courseId, int $studentId): bool
    {
        $this->where('course_id', $courseId)
            ->where('student_id', $studentId)
            ->delete();

        return true;
    }

    public function countByCourse(int $courseId): int
    {
        return $this->where('course_id', $courseId)->countAllResults();
    }

    public function getStudentsByCourse(int $courseId): array
    {
        return $this->select('course_students.*, students.nis, students.nisn, users.name, classes.name AS class_name')
            ->join('students', 'students.id = course_students.student_id')
            ->join('users', 'users.id = students.user_id')
            ->join('classes', 'classes.id = students.class_id', 'left')
            ->where('course_students.course_id', $courseId)
            ->orderBy('users.name', 'ASC')
            ->findAll();
    }
}

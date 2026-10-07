<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pengumuman.
 *  - course_id NULL     => pengumuman sekolah (dibuat admin, target: semua pengguna)
 *  - course_id terisi   => pengumuman course (dibuat guru pemilik course, target: peserta course)
 */
class AnnouncementModel extends Model
{
    protected $table            = 'announcements';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'course_id',
        'author_id',
        'title',
        'content',
        'published_at',
        'is_published',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'course_id'    => 'permit_empty|is_natural_no_zero|is_not_unique[courses.id]',
        'author_id'    => 'required|is_natural_no_zero|is_not_unique[users.id]',
        'title'        => 'required|max_length[255]',
        'content'      => 'required|max_length[10000]',
        'is_published' => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'course_id' => [
            'is_natural_no_zero' => 'Course tidak valid.',
            'is_not_unique'      => 'Course tidak ditemukan.',
        ],
        'author_id' => [
            'required'      => 'Penulis tidak valid.',
            'is_not_unique' => 'Penulis tidak ditemukan.',
        ],
        'title' => [
            'required'   => 'Judul pengumuman wajib diisi.',
            'max_length' => 'Judul pengumuman maksimal 255 karakter.',
        ],
        'content' => [
            'required'   => 'Isi pengumuman wajib diisi.',
            'max_length' => 'Isi pengumuman maksimal 10.000 karakter.',
        ],
        'is_published' => [
            'in_list' => 'Status pengumuman tidak valid.',
        ],
    ];

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['prepare'];
    protected $beforeUpdate   = ['prepare'];

    /**
     * Aturan published_at (sama dengan MaterialModel):
     * - dipublish pertama kali: published_at = sekarang
     * - tetap published: published_at tidak diubah
     * - di-unpublish: published_at = NULL
     */
    protected function prepare(array $eventData): array
    {
        $data = $eventData['data'];

        if (array_key_exists('is_published', $data)) {
            $data['is_published'] = (int) $data['is_published'] === 1 ? 1 : 0;

            if ($data['is_published'] === 0) {
                $data['published_at'] = null;
            } else {
                $id = $eventData['id'] ?? null;

                if (is_array($id)) {
                    $id = count($id) === 1 ? reset($id) : null;
                }

                $existing = null;

                if ($id !== null) {
                    $existing = $this->db->table($this->table)
                        ->select('published_at')
                        ->where('id', (int) $id)
                        ->get()
                        ->getRowArray();
                }

                if ($existing !== null && ! empty($existing['published_at'])) {
                    unset($data['published_at']);
                } else {
                    $data['published_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        $eventData['data'] = $data;

        return $eventData;
    }

    // ------------------------------------------------------------------
    // Query dasar
    // ------------------------------------------------------------------

    private function withRelations(): static
    {
        return $this->select(
            'announcements.*, courses.title AS course_title, courses.teacher_id AS course_teacher_id, '
                . 'classes.name AS class_name, subjects.name AS subject_name, users.name AS author_name'
        )
            ->join('courses', 'courses.id = announcements.course_id', 'left')
            ->join('classes', 'classes.id = courses.class_id', 'left')
            ->join('subjects', 'subjects.id = courses.subject_id', 'left')
            ->join('users', 'users.id = announcements.author_id', 'left');
    }

    private function newestFirst(): static
    {
        return $this->orderBy('COALESCE(announcements.published_at, announcements.created_at)', 'DESC', false)
            ->orderBy('announcements.id', 'DESC');
    }

    // ------------------------------------------------------------------
    // Admin
    // ------------------------------------------------------------------

    /**
     * Semua pengumuman (sekolah + course), semua status.
     */
    public function getForAdmin(): array
    {
        return $this->withRelations()->newestFirst()->findAll();
    }

    public function findForAdmin(int $id): ?array
    {
        $row = $this->withRelations()->where('announcements.id', $id)->first();

        return $row ?: null;
    }

    /**
     * Pengumuman sekolah saja (course_id NULL). Dipakai untuk aksi kelola admin.
     */
    public function findSchool(int $id): ?array
    {
        $row = $this->withRelations()
            ->where('announcements.id', $id)
            ->where('announcements.course_id IS NULL', null, false)
            ->first();

        return $row ?: null;
    }

    public function latestForAdmin(int $limit = 5): array
    {
        return $this->withRelations()->newestFirst()->findAll($limit);
    }

    // ------------------------------------------------------------------
    // Guru
    // ------------------------------------------------------------------

    /**
     * Yang terlihat oleh guru: semua pengumuman pada course miliknya (draft + published)
     * ditambah pengumuman sekolah yang sudah published.
     */
    public function getForTeacher(int $teacherId): array
    {
        return $this->visibleToTeacher($teacherId)->newestFirst()->findAll();
    }

    public function latestForTeacher(int $teacherId, int $limit = 5): array
    {
        return $this->visibleToTeacher($teacherId)->newestFirst()->findAll($limit);
    }

    /**
     * Detail yang boleh dilihat guru: milik course-nya, atau pengumuman sekolah published.
     */
    public function findForTeacher(int $id, int $teacherId): ?array
    {
        $row = $this->visibleToTeacher($teacherId)->where('announcements.id', $id)->first();

        return $row ?: null;
    }

    /**
     * Hanya pengumuman pada course milik guru (untuk edit/hapus/publish).
     */
    public function findOwnedByTeacher(int $id, int $teacherId): ?array
    {
        $row = $this->withRelations()
            ->where('announcements.id', $id)
            ->where('courses.teacher_id', $teacherId)
            ->first();

        return $row ?: null;
    }

    public function getByCourse(int $courseId): array
    {
        return $this->withRelations()
            ->where('announcements.course_id', $courseId)
            ->newestFirst()
            ->findAll();
    }

    private function visibleToTeacher(int $teacherId): static
    {
        return $this->withRelations()
            ->groupStart()
            ->where('courses.teacher_id', $teacherId)
            ->orGroupStart()
            ->where('announcements.course_id IS NULL', null, false)
            ->where('announcements.is_published', 1)
            ->groupEnd()
            ->groupEnd();
    }

    // ------------------------------------------------------------------
    // Siswa
    // ------------------------------------------------------------------

    /**
     * Yang terlihat oleh siswa: published DAN (pengumuman sekolah ATAU course yang diikuti).
     * $studentId NULL (akun tanpa profil siswa) hanya melihat pengumuman sekolah.
     */
    public function getForStudent(?int $studentId): array
    {
        return $this->visibleToStudent($studentId)->newestFirst()->findAll();
    }

    public function latestForStudent(?int $studentId, int $limit = 5): array
    {
        return $this->visibleToStudent($studentId)->newestFirst()->findAll($limit);
    }

    public function findForStudent(int $id, ?int $studentId): ?array
    {
        $row = $this->visibleToStudent($studentId)->where('announcements.id', $id)->first();

        return $row ?: null;
    }

    private function visibleToStudent(?int $studentId): static
    {
        $builder = $this->withRelations()->where('announcements.is_published', 1);

        if ($studentId === null) {
            return $builder->where('announcements.course_id IS NULL', null, false);
        }

        // course_students punya UNIQUE(course_id, student_id) sehingga left join tidak menggandakan baris.
        return $builder
            ->join('course_students cs', 'cs.course_id = announcements.course_id AND cs.student_id = ' . (int) $studentId, 'left')
            ->groupStart()
            ->where('announcements.course_id IS NULL', null, false)
            ->orWhere('cs.id IS NOT NULL', null, false)
            ->groupEnd();
    }
}

<?php

namespace App\Models;

use CodeIgniter\Model;

class AssignmentModel extends Model
{
    protected $table            = 'assignments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'course_id',
        'title',
        'description',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'attachment_type',
        'due_at',
        'published_at',
        'is_published',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'course_id'       => 'required|is_natural_no_zero|is_not_unique[courses.id]',
        'title'           => 'required|max_length[255]',
        'due_at'          => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'attachment_size' => 'permit_empty|is_natural',
        'is_published'    => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'course_id' => [
            'required'           => 'Course wajib dipilih.',
            'is_natural_no_zero' => 'Course tidak valid.',
            'is_not_unique'      => 'Course tidak ditemukan.',
        ],
        'title' => [
            'required'   => 'Judul tugas wajib diisi.',
            'max_length' => 'Judul tugas maksimal 255 karakter.',
        ],
        'due_at' => [
            'valid_date' => 'Format batas waktu pengumpulan tidak valid.',
        ],
        'attachment_size' => [
            'is_natural' => 'Ukuran lampiran tidak valid.',
        ],
        'is_published' => [
            'in_list' => 'Status tugas tidak valid.',
        ],
    ];

    protected $skipValidation     = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['prepare'];
    protected $beforeUpdate   = ['prepare'];

    /**
     * Rapikan data sebelum disimpan:
     * - string kosong pada kolom opsional menjadi NULL
     * - is_published dinormalisasi ke 0/1
     * - published_at: terisi saat publish pertama, tidak berubah saat edit,
     *   NULL saat dikembalikan ke draft
     */
    protected function prepare(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        $row = $data['data'];

        foreach (['description', 'attachment_path', 'attachment_name', 'attachment_type', 'attachment_size', 'due_at'] as $field) {
            if (array_key_exists($field, $row) && ($row[$field] === '' || $row[$field] === null)) {
                $row[$field] = null;
            }
        }

        if (array_key_exists('is_published', $row)) {
            $publish             = (int) $row['is_published'] === 1 ? 1 : 0;
            $row['is_published'] = $publish;

            if ($publish === 0) {
                $row['published_at'] = null;
            } else {
                $existing = null;
                $ids      = $data['id'] ?? null;

                if (! empty($ids)) {
                    $first    = is_array($ids) ? reset($ids) : $ids;
                    $existing = $this->db->table($this->table)
                        ->select('published_at')
                        ->where('id', (int) $first)
                        ->get()
                        ->getRowArray();
                }

                if (! $existing || empty($existing['published_at'])) {
                    $row['published_at'] = date('Y-m-d H:i:s');
                } else {
                    unset($row['published_at']);
                }
            }
        }

        $data['data'] = $row;

        return $data;
    }

    // ------------------------------------------------------------------
    // Pengecekan waktu
    // ------------------------------------------------------------------

    /**
     * True jika tugas punya batas waktu dan batas itu sudah lewat.
     */
    public static function isOverdue(?array $assignment, ?string $now = null): bool
    {
        if ($assignment === null || empty($assignment['due_at'])) {
            return false;
        }

        return strtotime($now ?? date('Y-m-d H:i:s')) > strtotime((string) $assignment['due_at']);
    }

    /**
     * True jika siswa masih boleh mengumpulkan: tugas published dan belum lewat batas waktu.
     */
    public static function isOpenForSubmission(?array $assignment, ?string $now = null): bool
    {
        if ($assignment === null || (int) ($assignment['is_published'] ?? 0) !== 1) {
            return false;
        }

        return ! self::isOverdue($assignment, $now);
    }

    // ------------------------------------------------------------------
    // Pencarian tunggal
    // ------------------------------------------------------------------

    /**
     * Tugas + info course tanpa batas pemilik.
     */
    public function findWithCourse(int $id): ?array
    {
        $row = $this->baseQuery()
            ->where('a.id', $id)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Tugas milik guru (lewat courses.teacher_id). NULL jika bukan miliknya.
     */
    public function findOwnedByTeacher(int $id, int $teacherId): ?array
    {
        $row = $this->baseQuery()
            ->where('a.id', $id)
            ->where('c.teacher_id', $teacherId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Tugas untuk siswa: hanya published dan siswa terdaftar di course-nya.
     * NULL jika tidak berhak.
     */
    public function findForStudent(int $id, int $studentId): ?array
    {
        $row = $this->baseQuery()
            ->join('course_students cs', 'cs.course_id = a.course_id')
            ->where('a.id', $id)
            ->where('a.is_published', 1)
            ->where('cs.student_id', $studentId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Daftar
    // ------------------------------------------------------------------

    /**
     * Semua tugas pada satu course (untuk guru) + ringkasan pengumpulan.
     * Hanya siswa yang masih terdaftar yang dihitung.
     */
    public function getByCourse(int $courseId): array
    {
        return $this->db->table('assignments a')
            ->select(
                'a.*, '
                . '(SELECT COUNT(*) FROM submissions s '
                . ' INNER JOIN course_students cs ON cs.student_id = s.student_id AND cs.course_id = a.course_id '
                . ' WHERE s.assignment_id = a.id AND s.submitted_at IS NOT NULL) AS submitted_total, '
                . '(SELECT COUNT(*) FROM submissions s2 '
                . ' INNER JOIN course_students cs2 ON cs2.student_id = s2.student_id AND cs2.course_id = a.course_id '
                . ' WHERE s2.assignment_id = a.id AND s2.graded_at IS NOT NULL) AS graded_total, '
                . '(SELECT COUNT(*) FROM course_students cs3 WHERE cs3.course_id = a.course_id) AS student_total',
                false
            )
            ->where('a.course_id', $courseId)
            ->orderBy('a.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Tugas published pada satu course. Jika $studentId diisi, setiap baris memuat
     * submitted_at, score, graded_at milik siswa itu (NULL = belum mengumpulkan / belum dinilai).
     * Urutan: batas waktu terdekat dulu, tugas tanpa batas waktu di akhir.
     */
    public function getPublishedByCourse(int $courseId, ?int $studentId = null): array
    {
        $builder = $this->db->table('assignments a')->select('a.*');

        if ($studentId !== null) {
            $builder->select('s.submitted_at, s.score, s.graded_at')
                ->join(
                    'submissions s',
                    's.assignment_id = a.id AND s.student_id = ' . (int) $studentId,
                    'left'
                );
        }

        return $builder
            ->where('a.course_id', $courseId)
            ->where('a.is_published', 1)
            ->orderBy('a.due_at IS NULL', 'ASC', false)
            ->orderBy('a.due_at', 'ASC')
            ->orderBy('a.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    // ------------------------------------------------------------------
    // Hitungan
    // ------------------------------------------------------------------

    /**
     * @return array{total:int, published:int, draft:int}
     */
    public function countByCourse(int $courseId): array
    {
        $row = $this->db->table('assignments')
            ->select(
                'COUNT(*) AS total, '
                . 'COALESCE(SUM(is_published = 1), 0) AS published, '
                . 'COALESCE(SUM(is_published = 0), 0) AS draft',
                false
            )
            ->where('course_id', $courseId)
            ->get()
            ->getRowArray();

        return $this->normalizeCounts($row);
    }

    /**
     * @return array{total:int, published:int, draft:int}
     */
    public function countByTeacher(int $teacherId): array
    {
        $row = $this->db->table('assignments a')
            ->select(
                'COUNT(a.id) AS total, '
                . 'COALESCE(SUM(a.is_published = 1), 0) AS published, '
                . 'COALESCE(SUM(a.is_published = 0), 0) AS draft',
                false
            )
            ->join('courses c', 'c.id = a.course_id')
            ->where('c.teacher_id', $teacherId)
            ->get()
            ->getRowArray();

        return $this->normalizeCounts($row);
    }

    // ------------------------------------------------------------------
    // Privat
    // ------------------------------------------------------------------

    private function baseQuery()
    {
        return $this->db->table('assignments a')
            ->select('a.*, c.title AS course_title, c.teacher_id AS course_teacher_id, sub.name AS subject_name')
            ->join('courses c', 'c.id = a.course_id')
            ->join('subjects sub', 'sub.id = c.subject_id', 'left');
    }

    private function normalizeCounts(?array $row): array
    {
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'draft'     => (int) ($row['draft'] ?? 0),
        ];
    }
}
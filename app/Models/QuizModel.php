<?php

namespace App\Models;

use CodeIgniter\Model;

class QuizModel extends Model
{
    public const STATE_DRAFT    = 'draft';
    public const STATE_UPCOMING = 'upcoming';
    public const STATE_OPEN     = 'open';
    public const STATE_CLOSED   = 'closed';

    private const COUNT_FIELDS =
    '(SELECT COUNT(*) FROM questions qs WHERE qs.quiz_id = q.id) AS question_count, '
        . '(SELECT COALESCE(SUM(qs2.points), 0) FROM questions qs2 WHERE qs2.quiz_id = q.id) AS total_points';

    protected $table            = 'quizzes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'course_id',
        'title',
        'description',
        'duration_minutes',
        'start_at',
        'end_at',
        'published_at',
        'is_published',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'course_id'        => 'required|is_natural_no_zero|is_not_unique[courses.id]',
        'title'            => 'required|max_length[255]',
        'description'      => 'permit_empty|max_length[10000]',
        'duration_minutes' => 'permit_empty|is_natural_no_zero|less_than_equal_to[1440]',
        'start_at'         => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'end_at'           => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'is_published'     => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'course_id' => [
            'required'           => 'Course wajib dipilih.',
            'is_natural_no_zero' => 'Course tidak valid.',
            'is_not_unique'      => 'Course tidak ditemukan.',
        ],
        'title' => [
            'required'   => 'Judul quiz wajib diisi.',
            'max_length' => 'Judul quiz maksimal 255 karakter.',
        ],
        'description' => [
            'max_length' => 'Instruksi quiz maksimal 10.000 karakter.',
        ],
        'duration_minutes' => [
            'is_natural_no_zero'    => 'Durasi harus berupa bilangan bulat lebih dari 0.',
            'less_than_equal_to'    => 'Durasi maksimal 1440 menit (24 jam).',
        ],
        'start_at' => [
            'valid_date' => 'Format waktu mulai tidak valid.',
        ],
        'end_at' => [
            'valid_date' => 'Format waktu berakhir tidak valid.',
        ],
        'is_published' => [
            'in_list' => 'Status quiz tidak valid.',
        ],
    ];

    protected $skipValidation       = false;
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

        foreach (['description', 'duration_minutes', 'start_at', 'end_at'] as $field) {
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
    // Aturan waktu (helper murni)
    // ------------------------------------------------------------------

    /**
     * Pesan error jika waktu berakhir lebih awal dari waktu mulai; NULL jika valid
     * atau salah satunya kosong. Dipanggil controller sebelum insert/update.
     */
    public static function scheduleError(?string $startAt, ?string $endAt): ?string
    {
        if (empty($startAt) || empty($endAt)) {
            return null;
        }

        if (strtotime($endAt) < strtotime($startAt)) {
            return 'Waktu berakhir tidak boleh lebih awal dari waktu mulai.';
        }

        return null;
    }

    /**
     * Status akses quiz bagi siswa:
     * draft (belum published) | upcoming (belum mulai) | open (bisa dikerjakan) | closed (sudah ditutup).
     */
    public static function accessState(?array $quiz, ?string $now = null): string
    {
        if ($quiz === null || (int) ($quiz['is_published'] ?? 0) !== 1) {
            return self::STATE_DRAFT;
        }

        $ts = strtotime($now ?? date('Y-m-d H:i:s'));

        if (! empty($quiz['start_at']) && $ts < strtotime((string) $quiz['start_at'])) {
            return self::STATE_UPCOMING;
        }

        if (! empty($quiz['end_at']) && $ts > strtotime((string) $quiz['end_at'])) {
            return self::STATE_CLOSED;
        }

        return self::STATE_OPEN;
    }

    public static function isOpen(?array $quiz, ?string $now = null): bool
    {
        return self::accessState($quiz, $now) === self::STATE_OPEN;
    }

    // ------------------------------------------------------------------
    // Pencarian tunggal
    // ------------------------------------------------------------------

    /**
     * Quiz + info course tanpa batas pemilik. Memuat question_count dan total_points.
     */
    public function findWithCourse(int $id): ?array
    {
        $row = $this->baseQuery()
            ->where('q.id', $id)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Quiz milik guru (lewat courses.teacher_id). NULL jika bukan miliknya.
     */
    public function findOwnedByTeacher(int $id, int $teacherId): ?array
    {
        $row = $this->baseQuery()
            ->where('q.id', $id)
            ->where('c.teacher_id', $teacherId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Quiz untuk siswa: hanya published dan siswa terdaftar di course-nya.
     * NULL jika tidak berhak. Status dibuka/ditutup diperiksa lewat accessState().
     */
    public function findForStudent(int $id, int $studentId): ?array
    {
        $row = $this->baseQuery()
            ->join('course_students cs', 'cs.course_id = q.course_id')
            ->where('q.id', $id)
            ->where('q.is_published', 1)
            ->where('cs.student_id', $studentId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Daftar
    // ------------------------------------------------------------------

    /**
     * Semua quiz pada satu course (untuk guru) + ringkasan.
     * Hanya siswa yang masih terdaftar yang dihitung.
     */
    public function getByCourse(int $courseId): array
    {
        return $this->db->table('quizzes q')
            ->select(
                'q.*, '
                . self::COUNT_FIELDS . ', '
                . "(SELECT COUNT(*) FROM quiz_attempts qa "
                . ' INNER JOIN course_students cs ON cs.student_id = qa.student_id AND cs.course_id = q.course_id '
                . " WHERE qa.quiz_id = q.id AND qa.status = 'submitted') AS submitted_total, "
                . '(SELECT COUNT(*) FROM course_students cs3 WHERE cs3.course_id = q.course_id) AS student_total',
                false
            )
            ->where('q.course_id', $courseId)
            ->orderBy('q.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Quiz published pada satu course. Jika $studentId diisi, setiap baris memuat
     * attempt_id, attempt_status, attempt_score, attempt_started_at, attempt_submitted_at
     * milik siswa itu (NULL = belum pernah memulai).
     * Urutan: batas akhir terdekat dulu, quiz tanpa batas akhir di belakang.
     */
    public function getPublishedByCourse(int $courseId, ?int $studentId = null): array
    {
        $builder = $this->db->table('quizzes q')
            ->select('q.*, ' . self::COUNT_FIELDS, false);

        if ($studentId !== null) {
            $builder->select('qa.id AS attempt_id, qa.status AS attempt_status, qa.score AS attempt_score, '
                . 'qa.started_at AS attempt_started_at, qa.submitted_at AS attempt_submitted_at')
                ->join(
                    'quiz_attempts qa',
                    'qa.quiz_id = q.id AND qa.student_id = ' . (int) $studentId,
                    'left'
                );
        }

        return $builder
            ->where('q.course_id', $courseId)
            ->where('q.is_published', 1)
            ->orderBy('q.end_at IS NULL', 'ASC', false)
            ->orderBy('q.end_at', 'ASC')
            ->orderBy('q.id', 'DESC')
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
        $row = $this->db->table('quizzes')
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
        $row = $this->db->table('quizzes q')
            ->select(
                'COUNT(q.id) AS total, '
                . 'COALESCE(SUM(q.is_published = 1), 0) AS published, '
                . 'COALESCE(SUM(q.is_published = 0), 0) AS draft',
                false
            )
            ->join('courses c', 'c.id = q.course_id')
            ->where('c.teacher_id', $teacherId)
            ->get()
            ->getRowArray();

        return $this->normalizeCounts($row);
    }

    // ------------------------------------------------------------------
    // Publish
    // ------------------------------------------------------------------

    /**
     * True jika sudah ada siswa yang memulai quiz ini (termasuk yang belum submit).
     */
    public function hasAttempts(int $quizId): bool
    {
        return $this->db->table('quiz_attempts')
            ->where('quiz_id', $quizId)
            ->countAllResults() > 0;
    }

    /**
     * Pesan alasan quiz belum boleh dipublikasikan; NULL jika boleh.
     */
    public function publishError(int $quizId): ?string
    {
        $count = $this->db->table('questions')
            ->where('quiz_id', $quizId)
            ->countAllResults();

        return $count < 1
            ? 'Quiz belum memiliki pertanyaan, sehingga belum dapat dipublikasikan.'
            : null;
    }

    /**
     * Publish atau kembalikan ke draft. Mengembalikan NULL jika berhasil,
     * atau pesan error jika gagal.
     */
    public function setPublished(int $quizId, bool $publish): ?string
    {
        if ($publish) {
            $error = $this->publishError($quizId);

            if ($error !== null) {
                return $error;
            }
        }

        if ($this->update($quizId, ['is_published' => $publish ? 1 : 0]) === false) {
            $errors = array_values($this->errors());

            return $errors !== [] ? (string) $errors[0] : 'Status quiz gagal diubah.';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Privat
    // ------------------------------------------------------------------

    private function baseQuery()
    {
        return $this->db->table('quizzes q')
            ->select(
                'q.*, c.title AS course_title, c.teacher_id AS course_teacher_id, sub.name AS subject_name, '
                . self::COUNT_FIELDS,
                false
            )
            ->join('courses c', 'c.id = q.course_id')
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
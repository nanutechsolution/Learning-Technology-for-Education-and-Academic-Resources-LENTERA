<?php

namespace App\Models;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

class SubmissionModel extends Model
{
    private const SCORE_RULE = 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]';

    protected $table            = 'submissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'assignment_id',
        'student_id',
        'answer_text',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'submitted_at',
        'score',
        'feedback',
        'graded_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'assignment_id' => 'required|is_natural_no_zero|is_not_unique[assignments.id]',
        'student_id'    => 'required|is_natural_no_zero|is_not_unique[students.id]',
        'answer_text'   => 'permit_empty|max_length[20000]',
        'score'         => self::SCORE_RULE,
        'feedback'      => 'permit_empty|max_length[5000]',
    ];

    protected $validationMessages = [
        'assignment_id' => [
            'required'           => 'Tugas wajib dipilih.',
            'is_natural_no_zero' => 'Tugas tidak valid.',
            'is_not_unique'      => 'Tugas tidak ditemukan.',
        ],
        'student_id' => [
            'required'           => 'Siswa wajib dipilih.',
            'is_natural_no_zero' => 'Siswa tidak valid.',
            'is_not_unique'      => 'Siswa tidak ditemukan.',
        ],
        'answer_text' => [
            'max_length' => 'Jawaban teks maksimal 20.000 karakter.',
        ],
        'score' => [
            'required'              => 'Nilai wajib diisi.',
            'decimal'               => 'Nilai harus berupa angka.',
            'greater_than_equal_to' => 'Nilai minimal 0.',
            'less_than_equal_to'    => 'Nilai maksimal 100.',
        ],
        'feedback' => [
            'max_length' => 'Feedback maksimal 5.000 karakter.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['prepare'];
    protected $beforeUpdate   = ['prepare'];

    /**
     * String kosong pada kolom opsional menjadi NULL.
     */
    protected function prepare(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        $row = $data['data'];

        foreach (['answer_text', 'file_path', 'file_name', 'file_type', 'file_size', 'feedback', 'score'] as $field) {
            if (array_key_exists($field, $row) && ($row[$field] === '' || $row[$field] === null)) {
                $row[$field] = null;
            }
        }

        $data['data'] = $row;

        return $data;
    }

    // ------------------------------------------------------------------
    // Helper murni
    // ------------------------------------------------------------------

    /**
     * Minimal salah satu dari jawaban teks atau file harus ada.
     */
    public static function hasContent(array $data, bool $hasExistingFile = false): bool
    {
        return trim((string) ($data['answer_text'] ?? '')) !== ''
            || ! empty($data['file_path'])
            || $hasExistingFile;
    }

    // ------------------------------------------------------------------
    // Pencarian
    // ------------------------------------------------------------------

    /**
     * Submission satu siswa pada satu tugas, atau NULL jika belum ada.
     */
    public function findByAssignmentAndStudent(int $assignmentId, int $studentId): ?array
    {
        $row = $this->db->table($this->table)
            ->where('assignment_id', $assignmentId)
            ->where('student_id', $studentId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Submission untuk dinilai guru: hanya jika tugasnya pada course milik guru.
     * Memuat assignment_title, course_id, due_at, student_name, student_nis.
     */
    public function findForTeacher(int $submissionId, int $teacherId): ?array
    {
        $row = $this->db->table('submissions sub')
            ->select(
                'sub.*, a.title AS assignment_title, a.course_id, a.due_at, '
                . 'u.name AS student_name, s.nis AS student_nis'
            )
            ->join('assignments a', 'a.id = sub.assignment_id')
            ->join('courses c', 'c.id = a.course_id')
            ->join('students s', 's.id = sub.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('sub.id', $submissionId)
            ->where('c.teacher_id', $teacherId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Pengumpulan (siswa)
    // ------------------------------------------------------------------

    /**
     * Kumpulkan atau perbarui jawaban: satu record per siswa dan tugas.
     * Kunci $data yang dipakai: answer_text, file_path, file_name, file_size, file_type.
     * Kolom file hanya berubah jika dikirim, sehingga file lama dipertahankan
     * saat siswa resubmit tanpa file baru. score/feedback/graded_at tidak disentuh.
     *
     * Aturan deadline dan status nilai diperiksa oleh controller, bukan di sini.
     */
    public function submit(int $assignmentId, int $studentId, array $data): bool
    {
        $allowed = ['answer_text', 'file_path', 'file_name', 'file_size', 'file_type'];
        $payload = array_intersect_key($data, array_flip($allowed));

        $payload['submitted_at'] = date('Y-m-d H:i:s');

        $existing = $this->findByAssignmentAndStudent($assignmentId, $studentId);

        if ($existing !== null) {
            return $this->update((int) $existing['id'], $payload) !== false;
        }

        $payload['assignment_id'] = $assignmentId;
        $payload['student_id']    = $studentId;

        $inserted = false;

        try {
            $inserted = $this->insert($payload) !== false;
        } catch (DatabaseException $e) {
            $inserted = false;
        }

        if ($inserted) {
            return true;
        }

        // Dua permintaan bersamaan: yang kalah balapan memperbarui record pemenang.
        $existing = $this->findByAssignmentAndStudent($assignmentId, $studentId);

        if ($existing !== null) {
            unset($payload['assignment_id'], $payload['student_id']);

            return $this->update((int) $existing['id'], $payload) !== false;
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Penilaian (guru)
    // ------------------------------------------------------------------

    /**
     * Beri nilai dan feedback. Hanya submission yang sudah dikumpulkan yang bisa dinilai.
     * Nilai 0-100, maksimal 2 desimal; koma desimal diterima ("85,5").
     * Jika gagal, alasan ada di $this->errors().
     */
    public function grade(int $submissionId, $score, ?string $feedback = null): bool
    {
        $row = $this->find($submissionId);

        if (! $row || empty($row['submitted_at'])) {
            return false;
        }

        if (is_string($score)) {
            $score = trim(str_replace(',', '.', $score));
        }

        if (is_numeric($score)) {
            $score = round((float) $score, 2);
        }

        $this->setValidationRule('score', 'required|decimal|greater_than_equal_to[0]|less_than_equal_to[100]');

        try {
            return $this->update($submissionId, [
                'score'     => $score,
                'feedback'  => $feedback === null ? null : trim($feedback),
                'graded_at' => date('Y-m-d H:i:s'),
            ]) !== false;
        } finally {
            $this->setValidationRule('score', self::SCORE_RULE);
        }
    }

    // ------------------------------------------------------------------
    // Rekap untuk guru
    // ------------------------------------------------------------------

    /**
     * Semua siswa terdaftar di course tugas ini beserta status pengumpulannya.
     * submission_id/submitted_at NULL berarti belum mengumpulkan.
     */
    public function getRosterByAssignment(int $assignmentId): array
    {
        $assignment = $this->db->table('assignments')
            ->select('course_id')
            ->where('id', $assignmentId)
            ->get()
            ->getRowArray();

        if (! $assignment) {
            return [];
        }

        return $this->db->table('course_students cs')
            ->select(
                's.id AS student_id, s.nis, u.name, c.name AS class_name, '
                . 'sub.id AS submission_id, sub.submitted_at, sub.score, sub.feedback, sub.graded_at, '
                . '(sub.file_path IS NOT NULL) AS has_file, (sub.answer_text IS NOT NULL) AS has_text',
                false
            )
            ->join('students s', 's.id = cs.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('classes c', 'c.id = s.class_id', 'left')
            ->join(
                'submissions sub',
                'sub.assignment_id = ' . (int) $assignmentId . ' AND sub.student_id = s.id',
                'left'
            )
            ->where('cs.course_id', (int) $assignment['course_id'])
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Statistik satu tugas (hanya siswa yang masih terdaftar).
     *
     * @return array{total:int, submitted:int, not_submitted:int, graded:int, late:int, average:?float}
     */
    public function getStatistics(int $assignmentId): array
    {
        $row = $this->db->query(
            'SELECT COUNT(cs.student_id) AS total,
                    COALESCE(SUM(sub.submitted_at IS NOT NULL), 0) AS submitted,
                    COALESCE(SUM(sub.graded_at IS NOT NULL), 0) AS graded,
                    COALESCE(SUM(a.due_at IS NOT NULL AND sub.submitted_at > a.due_at), 0) AS late,
                    AVG(sub.score) AS average
             FROM assignments a
             LEFT JOIN course_students cs ON cs.course_id = a.course_id
             LEFT JOIN submissions sub ON sub.assignment_id = a.id AND sub.student_id = cs.student_id
             WHERE a.id = ?',
            [$assignmentId]
        )->getRowArray();

        $total     = (int) ($row['total'] ?? 0);
        $submitted = (int) ($row['submitted'] ?? 0);

        return [
            'total'         => $total,
            'submitted'     => $submitted,
            'not_submitted' => max($total - $submitted, 0),
            'graded'        => (int) ($row['graded'] ?? 0),
            'late'          => (int) ($row['late'] ?? 0),
            'average'       => isset($row['average']) && $row['average'] !== null ? round((float) $row['average'], 2) : null,
        ];
    }

    // ------------------------------------------------------------------
    // Rekap untuk siswa
    // ------------------------------------------------------------------

    /**
     * Ringkasan tugas siswa: hanya tugas published pada course yang diikuti.
     *
     * @return array{total:int, submitted:int, graded:int, pending:int}
     */
    public function getProgressByStudent(int $studentId): array
    {
        $row = $this->db->query(
            'SELECT COUNT(a.id) AS total,
                    COALESCE(SUM(s.submitted_at IS NOT NULL), 0) AS submitted,
                    COALESCE(SUM(s.graded_at IS NOT NULL), 0) AS graded
             FROM assignments a
             INNER JOIN course_students cs ON cs.course_id = a.course_id AND cs.student_id = ?
             LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = cs.student_id
             WHERE a.is_published = 1',
            [$studentId]
        )->getRowArray();

        $total     = (int) ($row['total'] ?? 0);
        $submitted = (int) ($row['submitted'] ?? 0);

        return [
            'total'     => $total,
            'submitted' => $submitted,
            'graded'    => (int) ($row['graded'] ?? 0),
            'pending'   => max($total - $submitted, 0),
        ];
    }
}
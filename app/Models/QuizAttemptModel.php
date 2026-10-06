<?php

namespace App\Models;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

class QuizAttemptModel extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED   = 'submitted';

    protected $table            = 'quiz_attempts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'quiz_id',
        'student_id',
        'started_at',
        'submitted_at',
        'score',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'quiz_id'    => 'required|is_natural_no_zero|is_not_unique[quizzes.id]',
        'student_id' => 'required|is_natural_no_zero|is_not_unique[students.id]',
        'started_at' => 'required|valid_date[Y-m-d H:i:s]',
        'status'     => 'required|in_list[in_progress,submitted]',
        'score'      => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];

    protected $validationMessages = [
        'quiz_id' => [
            'required'           => 'Quiz wajib dipilih.',
            'is_natural_no_zero' => 'Quiz tidak valid.',
            'is_not_unique'      => 'Quiz tidak ditemukan.',
        ],
        'student_id' => [
            'required'           => 'Siswa wajib dipilih.',
            'is_natural_no_zero' => 'Siswa tidak valid.',
            'is_not_unique'      => 'Siswa tidak ditemukan.',
        ],
        'started_at' => [
            'required'   => 'Waktu mulai wajib diisi.',
            'valid_date' => 'Format waktu mulai tidak valid.',
        ],
        'status' => [
            'required' => 'Status wajib diisi.',
            'in_list'  => 'Status tidak valid.',
        ],
        'score' => [
            'decimal'               => 'Nilai harus berupa angka.',
            'greater_than_equal_to' => 'Nilai minimal 0.',
            'less_than_equal_to'    => 'Nilai maksimal 100.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // ------------------------------------------------------------------
    // Batas waktu (helper murni)
    // ------------------------------------------------------------------

    /**
     * Batas waktu pengerjaan (timestamp) = yang lebih awal antara
     * started_at + durasi dan end_at quiz. NULL jika tidak ada batas.
     * $quiz memuat duration_minutes dan end_at; $attempt memuat started_at.
     */
    public static function deadline(array $quiz, array $attempt): ?int
    {
        $limits = [];

        if (! empty($quiz['duration_minutes']) && ! empty($attempt['started_at'])) {
            $limits[] = strtotime((string) $attempt['started_at']) + ((int) $quiz['duration_minutes'] * 60);
        }

        if (! empty($quiz['end_at'])) {
            $limits[] = strtotime((string) $quiz['end_at']);
        }

        return $limits === [] ? null : min($limits);
    }

    /**
     * True jika batas waktu ada dan sudah lewat (ditambah kelonggaran detik, bila diberikan).
     */
    public static function isExpired(array $quiz, array $attempt, ?string $now = null, int $graceSeconds = 0): bool
    {
        $deadline = self::deadline($quiz, $attempt);

        if ($deadline === null) {
            return false;
        }

        return strtotime($now ?? date('Y-m-d H:i:s')) > $deadline + max($graceSeconds, 0);
    }

    /**
     * Sisa detik pengerjaan, tidak kurang dari 0. NULL jika tidak ada batas.
     */
    public static function remainingSeconds(array $quiz, array $attempt, ?string $now = null): ?int
    {
        $deadline = self::deadline($quiz, $attempt);

        if ($deadline === null) {
            return null;
        }

        return max($deadline - strtotime($now ?? date('Y-m-d H:i:s')), 0);
    }

    // ------------------------------------------------------------------
    // Pencarian
    // ------------------------------------------------------------------

    public function findByQuizAndStudent(int $quizId, int $studentId): ?array
    {
        $row = $this->db->table($this->table)
            ->where('quiz_id', $quizId)
            ->where('student_id', $studentId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Attempt untuk dilihat guru: hanya jika quiz-nya pada course milik guru.
     * Memuat quiz_title, course_id, duration_minutes, start_at, end_at, student_name, student_nis.
     */
    public function findForTeacher(int $attemptId, int $teacherId): ?array
    {
        $row = $this->db->table('quiz_attempts qa')
            ->select(
                'qa.*, qz.title AS quiz_title, qz.course_id, qz.duration_minutes, qz.start_at, qz.end_at, '
                . 'u.name AS student_name, s.nis AS student_nis'
            )
            ->join('quizzes qz', 'qz.id = qa.quiz_id')
            ->join('courses c', 'c.id = qz.course_id')
            ->join('students s', 's.id = qa.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('qa.id', $attemptId)
            ->where('c.teacher_id', $teacherId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    // ------------------------------------------------------------------
    // Mulai, simpan sementara, submit
    // ------------------------------------------------------------------

    /**
     * Mulai attempt, atau kembalikan attempt yang sudah ada (satu per siswa per quiz).
     * Aturan akses (terdaftar, published, sudah dibuka/ditutup) diperiksa controller.
     */
    public function start(int $quizId, int $studentId): ?array
    {
        $existing = $this->findByQuizAndStudent($quizId, $studentId);

        if ($existing !== null) {
            return $existing;
        }

        try {
            $this->insert([
                'quiz_id'    => $quizId,
                'student_id' => $studentId,
                'started_at' => date('Y-m-d H:i:s'),
                'status'     => self::STATUS_IN_PROGRESS,
            ]);
        } catch (DatabaseException $e) {
            // Dua permintaan bersamaan: yang kalah memakai attempt pemenang.
        }

        return $this->findByQuizAndStudent($quizId, $studentId);
    }

    /**
     * Simpan jawaban tanpa menyelesaikan attempt. Hanya untuk attempt in_progress.
     * $answers: [question_id => option_id]. Data yang tidak cocok diabaikan.
     */
    public function saveDraft(int $attemptId, array $answers): bool
    {
        $attempt = $this->find($attemptId);

        if (! $attempt || $attempt['status'] !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $this->db->transBegin();

        try {
            $this->persistAnswers($attemptId, (int) $attempt['quiz_id'], $answers);
            $this->db->transCommit();
        } catch (DatabaseException $e) {
            $this->db->transRollback();

            return false;
        }

        return true;
    }

    /**
     * Simpan jawaban lalu selesaikan attempt dan hitung nilai.
     * $answers boleh kosong untuk menyelesaikan dengan jawaban yang sudah tersimpan.
     * False jika attempt tidak ada atau sudah submitted (tidak ada submit ganda).
     * Pemeriksaan batas waktu dilakukan controller sebelum memanggil ini.
     */
    public function submit(int $attemptId, array $answers = [], ?string $now = null): bool
    {
        return $this->complete($attemptId, $answers, $now ?? date('Y-m-d H:i:s'));
    }

    /**
     * Selesaikan otomatis attempt in_progress yang batas waktunya sudah lewat,
     * dengan jawaban yang sudah tersimpan. submitted_at diisi batas waktunya.
     * Mengembalikan true jika attempt baru saja diselesaikan.
     */
    public function finalizeIfExpired(int $attemptId, ?string $now = null): bool
    {
        $row = $this->db->table('quiz_attempts qa')
            ->select('qa.*, qz.duration_minutes, qz.end_at')
            ->join('quizzes qz', 'qz.id = qa.quiz_id')
            ->where('qa.id', $attemptId)
            ->get()
            ->getRowArray();

        if (! $row || $row['status'] !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $deadline = self::deadline($row, $row);

        if ($deadline === null || strtotime($now ?? date('Y-m-d H:i:s')) <= $deadline) {
            return false;
        }

        return $this->complete($attemptId, [], date('Y-m-d H:i:s', $deadline));
    }

    /**
     * Selesaikan semua attempt in_progress yang sudah lewat batas pada satu quiz.
     * Mengembalikan jumlah attempt yang diselesaikan.
     */
    public function finalizeExpiredByQuiz(int $quizId, ?string $now = null): int
    {
        $ids = $this->db->table($this->table)
            ->select('id')
            ->where('quiz_id', $quizId)
            ->where('status', self::STATUS_IN_PROGRESS)
            ->get()
            ->getResultArray();

        $done = 0;

        foreach ($ids as $row) {
            if ($this->finalizeIfExpired((int) $row['id'], $now)) {
                $done++;
            }
        }

        return $done;
    }

    // ------------------------------------------------------------------
    // Nilai dan ulasan
    // ------------------------------------------------------------------

    /**
     * Poin yang diperoleh dan total poin quiz.
     *
     * @return array{earned:int, total:int}
     */
    public function pointsSummary(int $attemptId, int $quizId): array
    {
        return [
            'earned' => (new QuizAnswerModel())->earnedPoints($attemptId),
            'total'  => (new QuestionModel())->totalPoints($quizId),
        ];
    }

    /**
     * Ulasan per pertanyaan: opsi, opsi yang dipilih, opsi yang benar, dan apakah benar.
     * Controller memutuskan kapan ulasan ini boleh ditampilkan ke siswa.
     */
    public function getReview(int $attemptId, int $quizId): array
    {
        $questions = (new QuestionModel())->getWithOptions($quizId, true);
        $answers   = (new QuizAnswerModel())->getByAttempt($attemptId);

        foreach ($questions as &$question) {
            $selected = $answers[(int) $question['id']] ?? null;
            $correct  = null;

            foreach ($question['options'] as $option) {
                if ((int) $option['is_correct'] === 1) {
                    $correct = (int) $option['id'];
                    break;
                }
            }

            $question['selected_option_id'] = $selected;
            $question['correct_option_id']  = $correct;
            $question['is_correct']         = $selected !== null && $correct !== null && $selected === $correct;
        }
        unset($question);

        return $questions;
    }

    // ------------------------------------------------------------------
    // Rekap untuk guru
    // ------------------------------------------------------------------

    /**
     * Semua siswa terdaftar di course quiz ini beserta status pengerjaannya.
     * attempt_id/status NULL berarti belum memulai.
     */
    public function getRosterByQuiz(int $quizId): array
    {
        $quiz = $this->db->table('quizzes')
            ->select('course_id')
            ->where('id', $quizId)
            ->get()
            ->getRowArray();

        if (! $quiz) {
            return [];
        }

        return $this->db->table('course_students cs')
            ->select(
                's.id AS student_id, s.nis, u.name, c.name AS class_name, '
                . 'qa.id AS attempt_id, qa.status, qa.started_at, qa.submitted_at, qa.score'
            )
            ->join('students s', 's.id = cs.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('classes c', 'c.id = s.class_id', 'left')
            ->join(
                'quiz_attempts qa',
                'qa.quiz_id = ' . (int) $quizId . ' AND qa.student_id = s.id',
                'left'
            )
            ->where('cs.course_id', (int) $quiz['course_id'])
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Statistik satu quiz (hanya siswa yang masih terdaftar).
     * Nilai rata-rata, tertinggi, dan terendah hanya dari attempt yang sudah submitted.
     *
     * @return array{total:int, submitted:int, in_progress:int, not_started:int, average:?float, highest:?float, lowest:?float}
     */
    public function getStatistics(int $quizId): array
    {
        $row = $this->db->query(
            "SELECT COUNT(cs.student_id) AS total,
                    COALESCE(SUM(qa.status = 'submitted'), 0) AS submitted,
                    COALESCE(SUM(qa.status = 'in_progress'), 0) AS in_progress,
                    AVG(CASE WHEN qa.status = 'submitted' THEN qa.score END) AS average,
                    MAX(CASE WHEN qa.status = 'submitted' THEN qa.score END) AS highest,
                    MIN(CASE WHEN qa.status = 'submitted' THEN qa.score END) AS lowest
             FROM quizzes q
             LEFT JOIN course_students cs ON cs.course_id = q.course_id
             LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = cs.student_id
             WHERE q.id = ?",
            [$quizId]
        )->getRowArray();

        $total     = (int) ($row['total'] ?? 0);
        $submitted = (int) ($row['submitted'] ?? 0);
        $progress  = (int) ($row['in_progress'] ?? 0);

        return [
            'total'       => $total,
            'submitted'   => $submitted,
            'in_progress' => $progress,
            'not_started' => max($total - $submitted - $progress, 0),
            'average'     => isset($row['average']) ? round((float) $row['average'], 2) : null,
            'highest'     => isset($row['highest']) ? round((float) $row['highest'], 2) : null,
            'lowest'      => isset($row['lowest']) ? round((float) $row['lowest'], 2) : null,
        ];
    }

    // ------------------------------------------------------------------
    // Privat
    // ------------------------------------------------------------------

    /**
     * Skala 0-100: poin diperoleh / total poin quiz, dua desimal. Tanpa nilai negatif.
     */
    private function calculateScore(int $attemptId, int $quizId): float
    {
        $total = (new QuestionModel())->totalPoints($quizId);

        if ($total < 1) {
            return 0.0;
        }

        $earned = (new QuizAnswerModel())->earnedPoints($attemptId);

        return round(min(100, max(0, $earned / $total * 100)), 2);
    }

    /**
     * Simpan jawaban yang valid: opsi harus milik pertanyaan tersebut,
     * dan pertanyaan harus milik quiz attempt ini. Selain itu diabaikan.
     * Melempar DatabaseException jika penyimpanan gagal.
     */
    private function persistAnswers(int $attemptId, int $quizId, array $answers): void
    {
        $rows = $this->db->table('quiz_options o')
            ->select('o.id, o.question_id')
            ->join('questions q', 'q.id = o.question_id')
            ->where('q.quiz_id', $quizId)
            ->get()
            ->getResultArray();

        $valid = [];

        foreach ($rows as $row) {
            $valid[(int) $row['question_id']][(int) $row['id']] = true;
        }

        $answerModel = new QuizAnswerModel();

        foreach ($answers as $questionId => $optionId) {
            if (! is_scalar($optionId)) {
                continue;
            }

            $qid = (int) $questionId;
            $oid = (int) $optionId;

            if ($oid > 0 && isset($valid[$qid][$oid])) {
                if (! $answerModel->upsert($attemptId, $qid, $oid)) {
                    throw new DatabaseException('Jawaban gagal disimpan.');
                }
            }
        }
    }

    /**
     * Selesaikan attempt dalam satu transaksi. Update bersyarat status = in_progress
     * mencegah submit ganda: yang kalah balapan dibatalkan seluruhnya.
     */
    private function complete(int $attemptId, array $answers, string $submittedAt): bool
    {
        $attempt = $this->find($attemptId);

        if (! $attempt || $attempt['status'] !== self::STATUS_IN_PROGRESS) {
            return false;
        }

        $quizId = (int) $attempt['quiz_id'];

        $this->db->transBegin();

        try {
            $this->persistAnswers($attemptId, $quizId, $answers);

            $score = $this->calculateScore($attemptId, $quizId);

            $this->db->table($this->table)
                ->where('id', $attemptId)
                ->where('status', self::STATUS_IN_PROGRESS)
                ->update([
                    'status'       => self::STATUS_SUBMITTED,
                    'submitted_at' => $submittedAt,
                    'score'        => $score,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);

            if ($this->db->affectedRows() !== 1) {
                $this->db->transRollback();

                return false;
            }

            $this->db->transCommit();
        } catch (DatabaseException $e) {
            $this->db->transRollback();

            return false;
        }

        return true;
    }
}
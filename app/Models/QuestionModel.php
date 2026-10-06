<?php

namespace App\Models;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

class QuestionModel extends Model
{
    public const MIN_OPTIONS       = 2;
    public const MAX_OPTIONS       = 5;
    public const OPTION_MAX_LENGTH = 2000;
    public const OPTION_KEYS       = ['A', 'B', 'C', 'D', 'E'];

    protected $table            = 'questions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'quiz_id',
        'question_text',
        'question_type',
        'points',
        'order_number',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'quiz_id'       => 'required|is_natural_no_zero|is_not_unique[quizzes.id]',
        'question_text' => 'required|max_length[10000]',
        'question_type' => 'required|in_list[multiple_choice]',
        'points'        => 'required|is_natural_no_zero|less_than_equal_to[1000]',
        'order_number'  => 'required|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'quiz_id' => [
            'required'           => 'Quiz wajib dipilih.',
            'is_natural_no_zero' => 'Quiz tidak valid.',
            'is_not_unique'      => 'Quiz tidak ditemukan.',
        ],
        'question_text' => [
            'required'   => 'Teks pertanyaan wajib diisi.',
            'max_length' => 'Teks pertanyaan maksimal 10.000 karakter.',
        ],
        'question_type' => [
            'required' => 'Tipe pertanyaan wajib diisi.',
            'in_list'  => 'Tipe pertanyaan tidak valid.',
        ],
        'points' => [
            'required'           => 'Poin wajib diisi.',
            'is_natural_no_zero' => 'Poin harus berupa bilangan bulat lebih dari 0.',
            'less_than_equal_to' => 'Poin maksimal 1000 per pertanyaan.',
        ],
        'order_number' => [
            'required'           => 'Nomor urut wajib diisi.',
            'is_natural_no_zero' => 'Nomor urut harus berupa bilangan bulat lebih dari 0.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Error kustom dari saveWithOptions() dan deleteFromQuiz().
     *
     * @var list<string>
     */
    private array $saveErrors = [];

    /**
     * Daftar pesan error dari operasi simpan/hapus terakhir.
     *
     * @return list<string>
     */
    public function saveErrors(): array
    {
        return $this->saveErrors;
    }

    // ------------------------------------------------------------------
    // Kunci dan urutan
    // ------------------------------------------------------------------

    /**
     * Quiz terkunci jika sudah ada siswa yang memulai (soal tidak boleh berubah lagi).
     */
    public function isQuizLocked(int $quizId): bool
    {
        return (new QuizModel())->hasAttempts($quizId);
    }

    public function nextOrder(int $quizId): int
    {
        $row = $this->db->table($this->table)
            ->select('COALESCE(MAX(order_number), 0) + 1 AS next_order', false)
            ->where('quiz_id', $quizId)
            ->get()
            ->getRowArray();

        return (int) ($row['next_order'] ?? 1);
    }

    public function totalPoints(int $quizId): int
    {
        $row = $this->db->table($this->table)
            ->select('COALESCE(SUM(points), 0) AS total', false)
            ->where('quiz_id', $quizId)
            ->get()
            ->getRowArray();

        return (int) ($row['total'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Pencarian
    // ------------------------------------------------------------------

    /**
     * Pertanyaan pada quiz milik guru. NULL jika bukan miliknya.
     * Memuat quiz_title, course_id, quiz_is_published.
     */
    public function findOwnedByTeacher(int $id, int $teacherId): ?array
    {
        $row = $this->db->table('questions qs')
            ->select('qs.*, qz.title AS quiz_title, qz.course_id, qz.is_published AS quiz_is_published')
            ->join('quizzes qz', 'qz.id = qs.quiz_id')
            ->join('courses c', 'c.id = qz.course_id')
            ->where('qs.id', $id)
            ->where('c.teacher_id', $teacherId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * Semua pertanyaan satu quiz berurutan, masing-masing dengan kunci 'options'.
     * Untuk siswa, pakai $includeCorrect = false agar kolom is_correct tidak ikut terkirim.
     */
    public function getWithOptions(int $quizId, bool $includeCorrect = true): array
    {
        $questions = $this->where('quiz_id', $quizId)
            ->orderBy('order_number', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        if ($questions === []) {
            return [];
        }

        $ids  = array_map('intval', array_column($questions, 'id'));
        $cols = 'id, question_id, option_text, option_key, order_number' . ($includeCorrect ? ', is_correct' : '');

        $rows = $this->db->table('quiz_options')
            ->select($cols)
            ->whereIn('question_id', $ids)
            ->orderBy('order_number', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $byQuestion = [];
        foreach ($rows as $row) {
            $byQuestion[(int) $row['question_id']][] = $row;
        }

        foreach ($questions as &$question) {
            $question['options'] = $byQuestion[(int) $question['id']] ?? [];
        }
        unset($question);

        return $questions;
    }

    // ------------------------------------------------------------------
    // Simpan dan hapus
    // ------------------------------------------------------------------

    /**
     * Simpan pertanyaan beserta opsinya dalam satu transaksi (tambah atau edit).
     *
     * $question    : question_text, points, order_number (opsional; kosong = otomatis).
     * $optionTexts : teks opsi sesuai urutan form; yang kosong dibuang.
     * $correctIndex: kunci/indeks opsi benar pada $optionTexts (sebelum opsi kosong dibuang).
     *
     * @return int|false ID pertanyaan, atau false jika gagal (alasan di saveErrors()).
     */
    public function saveWithOptions(int $quizId, array $question, array $optionTexts, $correctIndex, ?int $questionId = null)
    {
        $this->saveErrors = [];

        if ($this->isQuizLocked($quizId)) {
            $this->saveErrors[] = 'Quiz sudah dikerjakan siswa, sehingga pertanyaan tidak dapat ditambah atau diubah.';

            return false;
        }

        $existing = null;

        if ($questionId !== null) {
            $existing = $this->where('id', $questionId)->where('quiz_id', $quizId)->first();

            if ($existing === null) {
                $this->saveErrors[] = 'Pertanyaan tidak ditemukan pada quiz ini.';

                return false;
            }
        }

        // Rapikan opsi: buang yang kosong, catat posisi jawaban benar setelah dirapikan.
        $options    = [];
        $correctPos = null;
        $tooLong    = false;

        foreach ($optionTexts as $i => $raw) {
            $text = trim((string) $raw);

            if ($text === '') {
                continue;
            }

            if (mb_strlen($text) > self::OPTION_MAX_LENGTH) {
                $tooLong = true;
            }

            $options[] = $text;

            if ($correctIndex !== null && $correctIndex !== '' && (string) $i === (string) $correctIndex) {
                $correctPos = count($options) - 1;
            }
        }

        if (count($options) < self::MIN_OPTIONS) {
            $this->saveErrors[] = 'Pertanyaan minimal memiliki ' . self::MIN_OPTIONS . ' opsi jawaban yang terisi.';
        }

        if (count($options) > self::MAX_OPTIONS) {
            $this->saveErrors[] = 'Pertanyaan maksimal memiliki ' . self::MAX_OPTIONS . ' opsi jawaban.';
        }

        if ($tooLong) {
            $this->saveErrors[] = 'Teks opsi jawaban maksimal ' . self::OPTION_MAX_LENGTH . ' karakter.';
        }

        if ($correctPos === null) {
            $this->saveErrors[] = 'Pilih satu jawaban yang benar di antara opsi yang terisi.';
        }

        if ($this->saveErrors !== []) {
            return false;
        }

        $order = $question['order_number'] ?? '';

        if ($order === '' || $order === null) {
            $order = $existing !== null ? (int) $existing['order_number'] : $this->nextOrder($quizId);
        }

        $row = [
            'quiz_id'       => $quizId,
            'question_text' => trim((string) ($question['question_text'] ?? '')),
            'question_type' => 'multiple_choice',
            'points'        => $question['points'] ?? '',
            'order_number'  => $order,
        ];

        $optionModel = new QuizOptionModel();

        $this->db->transBegin();

        try {
            if ($questionId === null) {
                $newId = $this->insert($row);

                if ($newId === false) {
                    $this->db->transRollback();
                    $this->saveErrors = array_values($this->errors());

                    return false;
                }

                $savedId = (int) $newId;
            } else {
                if ($this->update($questionId, $row) === false) {
                    $this->db->transRollback();
                    $this->saveErrors = array_values($this->errors());

                    return false;
                }

                $savedId = $questionId;

                $this->db->table('quiz_options')->where('question_id', $savedId)->delete();
            }

            foreach ($options as $i => $text) {
                $inserted = $optionModel->insert([
                    'question_id'  => $savedId,
                    'option_text'  => $text,
                    'option_key'   => self::OPTION_KEYS[$i],
                    'is_correct'   => $i === $correctPos ? 1 : 0,
                    'order_number' => $i + 1,
                ]);

                if ($inserted === false) {
                    $this->db->transRollback();
                    $this->saveErrors = array_values($optionModel->errors());

                    if ($this->saveErrors === []) {
                        $this->saveErrors[] = 'Opsi jawaban gagal disimpan.';
                    }

                    return false;
                }
            }

            $this->db->transCommit();
        } catch (DatabaseException $e) {
            $this->db->transRollback();
            $this->saveErrors[] = 'Pertanyaan gagal disimpan.';

            return false;
        }

        return $savedId;
    }

    /**
     * Hapus satu pertanyaan dari quiz (opsi ikut terhapus oleh CASCADE).
     * Ditolak jika quiz sudah dikerjakan siswa.
     */
    public function deleteFromQuiz(int $questionId, int $quizId): bool
    {
        $this->saveErrors = [];

        if ($this->isQuizLocked($quizId)) {
            $this->saveErrors[] = 'Quiz sudah dikerjakan siswa, sehingga pertanyaan tidak dapat dihapus.';

            return false;
        }

        $row = $this->where('id', $questionId)->where('quiz_id', $quizId)->first();

        if ($row === null) {
            $this->saveErrors[] = 'Pertanyaan tidak ditemukan pada quiz ini.';

            return false;
        }

        if ($this->delete($questionId) === false) {
            $this->saveErrors[] = 'Pertanyaan gagal dihapus.';

            return false;
        }

        return true;
    }
}
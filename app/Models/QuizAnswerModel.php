<?php

namespace App\Models;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

class QuizAnswerModel extends Model
{
    protected $table            = 'quiz_answers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'attempt_id',
        'question_id',
        'option_id',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'attempt_id'  => 'required|is_natural_no_zero|is_not_unique[quiz_attempts.id]',
        'question_id' => 'required|is_natural_no_zero|is_not_unique[questions.id]',
        'option_id'   => 'permit_empty|is_natural_no_zero|is_not_unique[quiz_options.id]',
    ];

    protected $validationMessages = [
        'attempt_id' => [
            'required'           => 'Attempt wajib diisi.',
            'is_natural_no_zero' => 'Attempt tidak valid.',
            'is_not_unique'      => 'Attempt tidak ditemukan.',
        ],
        'question_id' => [
            'required'           => 'Pertanyaan wajib diisi.',
            'is_natural_no_zero' => 'Pertanyaan tidak valid.',
            'is_not_unique'      => 'Pertanyaan tidak ditemukan.',
        ],
        'option_id' => [
            'is_natural_no_zero' => 'Opsi tidak valid.',
            'is_not_unique'      => 'Opsi tidak ditemukan.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Simpan atau ganti jawaban satu pertanyaan dalam satu attempt.
     * Kecocokan opsi dengan pertanyaan diperiksa oleh QuizAttemptModel, bukan di sini.
     */
    public function upsert(int $attemptId, int $questionId, ?int $optionId): bool
    {
        $existing = $this->findByAttemptAndQuestion($attemptId, $questionId);

        if ($existing !== null) {
            return $this->update((int) $existing['id'], ['option_id' => $optionId]) !== false;
        }

        try {
            if ($this->insert([
                'attempt_id'  => $attemptId,
                'question_id' => $questionId,
                'option_id'   => $optionId,
            ]) !== false) {
                return true;
            }
        } catch (DatabaseException $e) {
            // Dua permintaan bersamaan: ditangani di bawah.
        }

        $existing = $this->findByAttemptAndQuestion($attemptId, $questionId);

        if ($existing !== null) {
            return $this->update((int) $existing['id'], ['option_id' => $optionId]) !== false;
        }

        return false;
    }

    /**
     * Jawaban satu attempt sebagai peta [question_id => option_id|null].
     *
     * @return array<int, int|null>
     */
    public function getByAttempt(int $attemptId): array
    {
        $rows = $this->db->table($this->table)
            ->select('question_id, option_id')
            ->where('attempt_id', $attemptId)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['question_id']] = $row['option_id'] === null ? null : (int) $row['option_id'];
        }

        return $map;
    }

    /**
     * Total poin dari jawaban yang benar. Jawaban salah atau kosong bernilai 0.
     * Opsi harus milik pertanyaan yang sama dengan jawabannya.
     */
    public function earnedPoints(int $attemptId): int
    {
        $row = $this->db->table('quiz_answers a')
            ->select('COALESCE(SUM(q.points), 0) AS earned', false)
            ->join('quiz_options o', 'o.id = a.option_id AND o.question_id = a.question_id')
            ->join('questions q', 'q.id = a.question_id')
            ->where('a.attempt_id', $attemptId)
            ->where('o.is_correct', 1)
            ->get()
            ->getRowArray();

        return (int) ($row['earned'] ?? 0);
    }

    private function findByAttemptAndQuestion(int $attemptId, int $questionId): ?array
    {
        $row = $this->db->table($this->table)
            ->where('attempt_id', $attemptId)
            ->where('question_id', $questionId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }
}
<?php

namespace App\Commands;

use App\Models\QuestionModel;
use App\Models\QuizAnswerModel;
use App\Models\QuizAttemptModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class VerifyPhase4 extends BaseCommand
{
    protected $group       = 'Lentera';
    protected $name        = 'lentera:verify4';
    protected $description = 'Memeriksa konsistensi data Phase 4 (quiz, soal, opsi, attempt, jawaban, nilai). Hanya membaca.';

    private int $problems = 0;

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        CLI::write('LENTERA - verifikasi Phase 4', 'yellow');
        CLI::newLine();

        // 1. Aturan quiz
        $this->section('Aturan quiz');

        $badSchedule = $this->count(
            $db,
            'SELECT COUNT(*) AS c FROM quizzes WHERE start_at IS NOT NULL AND end_at IS NOT NULL AND end_at < start_at'
        );
        $this->check($badSchedule === 0, 'Semua jadwal quiz valid (end_at >= start_at)', "{$badSchedule} quiz punya waktu berakhir lebih awal dari waktu mulai.");

        $badDuration = $this->count(
            $db,
            'SELECT COUNT(*) AS c FROM quizzes WHERE duration_minutes IS NOT NULL AND (duration_minutes < 1 OR duration_minutes > 1440)'
        );
        $this->check($badDuration === 0, 'Semua durasi quiz berada di rentang 1-1440 menit', "{$badDuration} quiz punya durasi di luar 1-1440 menit.");

        $noQuestions = $this->count(
            $db,
            'SELECT COUNT(*) AS c FROM quizzes q WHERE q.is_published = 1 AND NOT EXISTS (SELECT 1 FROM questions s WHERE s.quiz_id = q.id)'
        );
        $this->warn($noQuestions === 0, 'Semua quiz published punya minimal satu soal', "{$noQuestions} quiz published tidak punya soal (siswa tidak bisa memulainya; kembalikan ke draft atau tambahkan soal).");

        // 2. Soal dan opsi
        $this->section('Soal dan opsi');

        $badType = $this->count($db, "SELECT COUNT(*) AS c FROM questions WHERE question_type <> 'multiple_choice'");
        $this->check($badType === 0, 'Semua soal bertipe multiple_choice', "{$badType} soal bertipe selain multiple_choice.");

        $badPoints = $this->count($db, 'SELECT COUNT(*) AS c FROM questions WHERE points < 1 OR points > 1000');
        $this->check($badPoints === 0, 'Semua poin soal berada di rentang 1-1000', "{$badPoints} soal punya poin di luar 1-1000.");

        $badOptionCount = $db->query(
            'SELECT qs.id, COUNT(o.id) AS n FROM questions qs LEFT JOIN quiz_options o ON o.question_id = qs.id GROUP BY qs.id HAVING n < ' . QuestionModel::MIN_OPTIONS . ' OR n > ' . QuestionModel::MAX_OPTIONS
        )->getResultArray();
        $this->check(
            $badOptionCount === [],
            'Setiap soal punya 2-5 opsi',
            count($badOptionCount) . ' soal punya jumlah opsi di luar 2-5 (id: ' . $this->ids($badOptionCount, 'id') . ').'
        );

        $badCorrect = $db->query(
            'SELECT qs.id, COALESCE(SUM(o.is_correct), 0) AS c FROM questions qs LEFT JOIN quiz_options o ON o.question_id = qs.id GROUP BY qs.id HAVING c <> 1'
        )->getResultArray();
        $this->check(
            $badCorrect === [],
            'Setiap soal punya tepat satu jawaban benar',
            count($badCorrect) . ' soal tidak memiliki tepat satu jawaban benar (id: ' . $this->ids($badCorrect, 'id') . ').'
        );

        // 3. Attempt
        $this->section('Attempt');

        $dups = $db->table('quiz_attempts')
            ->select('quiz_id, student_id, COUNT(*) AS c')
            ->groupBy('quiz_id, student_id')
            ->having('COUNT(*) >', 1)
            ->get()->getResultArray();
        $this->check($dups === [], 'Tidak ada attempt ganda per siswa dan quiz', count($dups) . ' pasangan (quiz, siswa) punya lebih dari satu attempt.');

        $submittedIncomplete = $db->table('quiz_attempts')
            ->where('status', 'submitted')
            ->groupStart()->where('submitted_at IS NULL', null, false)->orWhere('score IS NULL', null, false)->groupEnd()
            ->countAllResults();
        $this->check($submittedIncomplete === 0, 'Semua attempt submitted punya waktu kumpul dan nilai', "{$submittedIncomplete} attempt submitted tanpa submitted_at atau nilai.");

        $inProgressDirty = $db->table('quiz_attempts')
            ->where('status', 'in_progress')
            ->groupStart()->where('submitted_at IS NOT NULL', null, false)->orWhere('score IS NOT NULL', null, false)->groupEnd()
            ->countAllResults();
        $this->check($inProgressDirty === 0, 'Attempt in_progress tidak punya waktu kumpul atau nilai', "{$inProgressDirty} attempt in_progress sudah punya submitted_at atau nilai.");

        $badScore = $db->table('quiz_attempts')
            ->where('score IS NOT NULL', null, false)
            ->groupStart()->where('score <', 0)->orWhere('score >', 100)->groupEnd()
            ->countAllResults();
        $this->check($badScore === 0, 'Semua nilai berada di rentang 0-100', "{$badScore} attempt bernilai di luar 0-100.");

        $timeOrder = $db->table('quiz_attempts')
            ->where('status', 'submitted')
            ->where('submitted_at < started_at', null, false)
            ->countAllResults();
        $this->check($timeOrder === 0, 'Waktu kumpul tidak lebih awal dari waktu mulai', "{$timeOrder} attempt punya submitted_at lebih awal dari started_at.");

        // 4. Jawaban dan nilai
        $this->section('Jawaban dan nilai');

        $foreignOption = $this->count(
            $db,
            'SELECT COUNT(*) AS c FROM quiz_answers a INNER JOIN quiz_options o ON o.id = a.option_id WHERE o.question_id <> a.question_id'
        );
        $this->check($foreignOption === 0, 'Semua jawaban memakai opsi milik soalnya', "{$foreignOption} jawaban memakai opsi dari soal lain.");

        $foreignQuestion = $this->count(
            $db,
            'SELECT COUNT(*) AS c FROM quiz_answers a INNER JOIN quiz_attempts qa ON qa.id = a.attempt_id INNER JOIN questions q ON q.id = a.question_id WHERE q.quiz_id <> qa.quiz_id'
        );
        $this->check($foreignQuestion === 0, 'Semua jawaban merujuk soal dari quiz attempt-nya', "{$foreignQuestion} jawaban merujuk soal dari quiz lain.");

        $answerModel   = new QuizAnswerModel();
        $questionModel = new QuestionModel();
        $mismatch      = [];

        $submitted = $db->table('quiz_attempts')
            ->select('id, quiz_id, score')
            ->where('status', 'submitted')
            ->get()->getResultArray();

        foreach ($submitted as $row) {
            $total    = $questionModel->totalPoints((int) $row['quiz_id']);
            $earned   = $answerModel->earnedPoints((int) $row['id']);
            $expected = $total > 0 ? round(min(100, max(0, $earned / $total * 100)), 2) : 0.0;

            if ($row['score'] === null || abs((float) $row['score'] - $expected) > 0.01) {
                $mismatch[] = '#' . $row['id'] . ' (tersimpan ' . ($row['score'] ?? 'NULL') . ', seharusnya ' . number_format($expected, 2, '.', '') . ')';
            }
        }

        $this->check(
            $mismatch === [],
            'Nilai tersimpan cocok dengan hitungan ulang dari jawaban (' . count($submitted) . ' attempt diperiksa)',
            count($mismatch) . ' attempt bernilai tidak cocok: ' . implode(', ', array_slice($mismatch, 0, 5)) . (count($mismatch) > 5 ? ', ...' : '')
        );

        // Informasi saja
        $notEnrolled = $this->count(
            $db,
            'SELECT COUNT(*) AS c FROM quiz_attempts qa INNER JOIN quizzes q ON q.id = qa.quiz_id LEFT JOIN course_students cs ON cs.course_id = q.course_id AND cs.student_id = qa.student_id WHERE cs.student_id IS NULL'
        );
        CLI::write("  [info] {$notEnrolled} attempt berasal dari siswa yang kini tidak terdaftar di course (wajar jika siswa pernah dikeluarkan).", 'light_gray');

        $draftWithAttempt = $this->count(
            $db,
            'SELECT COUNT(DISTINCT q.id) AS c FROM quizzes q INNER JOIN quiz_attempts qa ON qa.quiz_id = q.id WHERE q.is_published = 0'
        );
        CLI::write("  [info] {$draftWithAttempt} quiz berstatus draft sudah punya attempt (guru mengembalikan ke draft setelah dikerjakan).", 'light_gray');

        $running = $db->table('quiz_attempts qa')
            ->select('qa.id, qa.started_at, qz.duration_minutes, qz.end_at')
            ->join('quizzes qz', 'qz.id = qa.quiz_id')
            ->where('qa.status', 'in_progress')
            ->get()->getResultArray();

        $expired = 0;
        foreach ($running as $row) {
            if (QuizAttemptModel::isExpired($row, $row)) {
                $expired++;
            }
        }
        CLI::write("  [info] {$expired} attempt in_progress sudah lewat batas waktu dan akan diselesaikan otomatis saat siswa atau guru membuka halamannya.", 'light_gray');

        CLI::newLine();

        if ($this->problems === 0) {
            CLI::write('Semua pemeriksaan lolos.', 'green');

            return EXIT_SUCCESS;
        }

        CLI::write($this->problems . ' masalah ditemukan.', 'red');

        return EXIT_ERROR;
    }

    private function section(string $title): void
    {
        CLI::newLine();
        CLI::write($title, 'cyan');
    }

    private function check(bool $ok, string $passMsg, string $failMsg): void
    {
        if ($ok) {
            CLI::write('  [OK]    ' . $passMsg, 'green');

            return;
        }

        $this->problems++;
        CLI::write('  [GAGAL] ' . $failMsg, 'red');
    }

    /**
     * Peringatan: tidak dihitung sebagai masalah.
     */
    private function warn(bool $ok, string $passMsg, string $warnMsg): void
    {
        if ($ok) {
            CLI::write('  [OK]    ' . $passMsg, 'green');

            return;
        }

        CLI::write('  [peringatan] ' . $warnMsg, 'yellow');
    }

    private function count($db, string $sql): int
    {
        $row = $db->query($sql)->getRowArray();

        return (int) ($row['c'] ?? 0);
    }

    private function ids(array $rows, string $key): string
    {
        $ids = array_map(static fn ($r) => (string) $r[$key], array_slice($rows, 0, 5));

        return implode(', ', $ids) . (count($rows) > 5 ? ', ...' : '');
    }
}
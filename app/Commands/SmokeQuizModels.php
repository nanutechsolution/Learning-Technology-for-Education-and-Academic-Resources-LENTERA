<?php

namespace App\Commands;

use App\Models\CourseStudentModel;
use App\Models\QuestionModel;
use App\Models\QuizAnswerModel;
use App\Models\QuizAttemptModel;
use App\Models\QuizModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SmokeQuizModels extends BaseCommand
{
    protected $group       = 'Lentera';
    protected $name        = 'lentera:smoke4b';
    protected $description = 'Uji cepat Model quiz (STEP 4B). Semua data uji dibatalkan (rollback) di akhir.';

    private int $passed = 0;
    private int $failed = 0;

    public function run(array $params)
    {
        $db      = \Config\Database::connect();
        $course  = $db->table('courses')->get()->getRowArray();
        $student = $db->table('students')->get()->getRowArray();

        if (! $course || ! $student) {
            CLI::error('Butuh minimal satu course dan satu siswa di database.');

            return EXIT_ERROR;
        }

        CLI::write('LENTERA - uji Model quiz (data uji di-rollback)', 'yellow');
        CLI::newLine();

        $db->transBegin();

        try {
            $this->runChecks((int) $course['id'], (int) $course['teacher_id'], (int) $student['id']);
        } catch (\Throwable $e) {
            $this->failed++;
            CLI::write('  [GAGAL] Exception: ' . $e->getMessage(), 'red');
        } finally {
            $db->transRollback();
        }

        CLI::newLine();
        CLI::write("Hasil: {$this->passed} lolos, {$this->failed} gagal. Semua data uji sudah dibatalkan.", $this->failed === 0 ? 'green' : 'red');

        return $this->failed === 0 ? EXIT_SUCCESS : EXIT_ERROR;
    }

    private function runChecks(int $cid, int $teacherId, int $sid): void
    {
        $db = \Config\Database::connect();

        $enroll = new CourseStudentModel();
        if (! $enroll->isEnrolled($cid, $sid)) {
            $enroll->enroll($cid, $sid);
        }

        $quizzes   = new QuizModel();
        $questions = new QuestionModel();
        $attempts  = new QuizAttemptModel();
        $answers   = new QuizAnswerModel();

        // --- Validasi quiz
        $this->ok($quizzes->insert(['course_id' => $cid, 'title' => '', 'is_published' => 0]) === false, 'Quiz tanpa judul ditolak');
        $this->ok($quizzes->insert(['course_id' => $cid, 'title' => 'UJI', 'duration_minutes' => 0]) === false, 'Durasi 0 ditolak');
        $this->ok(QuizModel::scheduleError('2026-10-07 10:00:00', '2026-10-07 09:00:00') !== null, 'end_at lebih awal dari start_at ditolak');
        $this->ok(QuizModel::scheduleError('2026-10-07 09:00:00', '2026-10-07 10:00:00') === null, 'end_at setelah start_at diterima');

        $emptyId = $quizzes->insert(['course_id' => $cid, 'title' => 'UJI-4B-KOSONG', 'is_published' => 0]);
        $quizId  = $quizzes->insert([
            'course_id' => $cid, 'title' => 'UJI-4B', 'description' => '', 'duration_minutes' => '',
            'start_at' => '', 'end_at' => '', 'is_published' => 0,
        ]);
        $this->ok($emptyId !== false && $quizId !== false, 'Quiz valid tersimpan');
        $emptyId = (int) $emptyId;
        $quizId  = (int) $quizId;

        $row = $quizzes->find($quizId);
        $this->ok($row['duration_minutes'] === null && $row['start_at'] === null && $row['description'] === null, 'String kosong disimpan sebagai NULL');

        // --- Publish
        $this->ok($quizzes->setPublished($emptyId, true) !== null, 'Quiz tanpa soal tidak bisa dipublikasikan');

        // --- Soal dan opsi
        $this->ok($questions->saveWithOptions($quizId, ['question_text' => 'x', 'points' => 5], ['Satu'], 0) === false, 'Soal dengan 1 opsi ditolak');
        $this->ok($questions->saveWithOptions($quizId, ['question_text' => 'x', 'points' => 5], ['a', 'b', 'c', 'd', 'e', 'f'], 0) === false, 'Soal dengan 6 opsi ditolak');
        $this->ok($questions->saveWithOptions($quizId, ['question_text' => 'x', 'points' => 5], ['a', 'b'], null) === false, 'Soal tanpa jawaban benar ditolak');
        $this->ok($questions->saveWithOptions($quizId, ['question_text' => 'x', 'points' => 5], ['a', '', 'b'], 1) === false, 'Jawaban benar pada opsi kosong ditolak');
        $this->ok($questions->saveWithOptions($quizId, ['question_text' => 'x', 'points' => 0], ['a', 'b'], 0) === false, 'Poin 0 ditolak');
        $this->ok($questions->saveWithOptions($quizId, ['question_text' => '', 'points' => 5], ['a', 'b'], 0) === false, 'Teks soal kosong ditolak');

        $q1 = $questions->saveWithOptions($quizId, ['question_text' => 'Soal 1', 'points' => 10], ['Benar', 'Salah', '', ''], 0);
        $q2 = $questions->saveWithOptions($quizId, ['question_text' => 'Soal 2', 'points' => 30], ['A1', 'B1', 'C1'], 2);
        $this->ok(is_int($q1) && is_int($q2), 'Dua soal valid tersimpan');

        $list = $questions->getWithOptions($quizId, true);
        $this->ok(count($list) === 2 && count($list[0]['options']) === 2 && count($list[1]['options']) === 3, 'Opsi kosong dibuang, jumlah opsi benar');
        $this->ok((int) $list[0]['order_number'] === 1 && (int) $list[1]['order_number'] === 2, 'Nomor urut otomatis');
        $this->ok($list[0]['options'][0]['option_key'] === 'A' && $list[1]['options'][2]['option_key'] === 'C', 'Kunci opsi A-E otomatis');
        $this->ok((int) $list[0]['options'][0]['is_correct'] === 1 && (int) $list[1]['options'][2]['is_correct'] === 1, 'Jawaban benar tertandai pada opsi yang dipilih');

        $forStudent = $questions->getWithOptions($quizId, false);
        $this->ok(! array_key_exists('is_correct', $forStudent[0]['options'][0]), 'Versi siswa tidak memuat is_correct');

        // --- Edit soal sebelum ada attempt
        $edited = $questions->saveWithOptions($quizId, ['question_text' => 'Soal 1 (edit)', 'points' => 10], ['Benar', 'Salah', 'Mungkin'], 0, (int) $q1);
        $this->ok($edited === (int) $q1 && count($questions->getWithOptions($quizId)[0]['options']) === 3, 'Soal dapat diedit dan opsinya diganti');

        $list = $questions->getWithOptions($quizId, true);

        // --- Publish dan akses
        $this->ok($quizzes->setPublished($quizId, true) === null, 'Quiz dengan soal dapat dipublikasikan');
        $quiz = $quizzes->find($quizId);
        $this->ok((int) $quiz['is_published'] === 1 && ! empty($quiz['published_at']), 'published_at terisi saat publish');

        $this->ok(QuizModel::accessState($quiz) === 'open', 'Status akses: open');
        $this->ok(QuizModel::accessState(array_merge($quiz, ['start_at' => '2999-01-01 00:00:00'])) === 'upcoming', 'Status akses: belum mulai');
        $this->ok(QuizModel::accessState(array_merge($quiz, ['end_at' => '2000-01-01 00:00:00'])) === 'closed', 'Status akses: ditutup');
        $this->ok(QuizModel::accessState(array_merge($quiz, ['is_published' => 0])) === 'draft', 'Status akses: draft');

        // --- Otorisasi
        $this->ok($quizzes->findOwnedByTeacher($quizId, $teacherId) !== null, 'Guru pemilik dapat membuka quiz');
        $this->ok($quizzes->findOwnedByTeacher($quizId, $teacherId + 99999) === null, 'Guru lain tidak dapat membuka quiz');
        $this->ok($quizzes->findForStudent($quizId, $sid) !== null, 'Siswa terdaftar dapat membuka quiz published');
        $this->ok($quizzes->findForStudent($emptyId, $sid) === null, 'Siswa tidak dapat membuka quiz draft');
        $this->ok($questions->findOwnedByTeacher((int) $q1, $teacherId + 99999) === null, 'Guru lain tidak dapat membuka soal');

        $summary = null;
        foreach ($quizzes->getByCourse($cid) as $r) {
            if ((int) $r['id'] === $quizId) {
                $summary = $r;
            }
        }
        $this->ok($summary !== null && (int) $summary['question_count'] === 2 && (int) $summary['total_points'] === 40, 'Daftar quiz memuat 2 soal dan total 40 poin');

        // --- Attempt, submit, nilai
        $a = $attempts->start($quizId, $sid);
        $b = $attempts->start($quizId, $sid);
        $this->ok($a !== null && $a['status'] === 'in_progress' && (int) $a['id'] === (int) $b['id'], 'Start dua kali memakai attempt yang sama');

        $correct1 = $this->optionId($list[0], true);
        $wrong2   = $this->optionId($list[1], false);
        $foreign  = $this->optionId($list[1], true);

        // Opsi milik soal lain pada soal 1 harus diabaikan.
        $this->ok($attempts->saveDraft((int) $a['id'], [(int) $q1 => $foreign]) === true && $answers->getByAttempt((int) $a['id']) === [], 'Opsi milik soal lain diabaikan');

        $this->ok($attempts->submit((int) $a['id'], [(int) $q1 => $correct1, (int) $q2 => $wrong2]) === true, 'Submit berhasil');
        $done = $attempts->find((int) $a['id']);
        $this->ok($done['status'] === 'submitted' && ! empty($done['submitted_at']), 'Status menjadi submitted');
        $this->ok(abs((float) $done['score'] - 25.0) < 0.001, 'Nilai otomatis 25,00 (10 dari 40 poin)');
        $this->ok($attempts->submit((int) $a['id'], []) === false, 'Submit kedua ditolak');
        $this->ok($attempts->saveDraft((int) $a['id'], [(int) $q1 => $correct1]) === false, 'Simpan sementara setelah submit ditolak');

        $points = $attempts->pointsSummary((int) $a['id'], $quizId);
        $this->ok($points['earned'] === 10 && $points['total'] === 40, 'Ringkasan poin 10 dari 40');

        $review = $attempts->getReview((int) $a['id'], $quizId);
        $this->ok($review[0]['is_correct'] === true && $review[1]['is_correct'] === false, 'Ulasan: soal 1 benar, soal 2 salah');

        // --- Penguncian setelah ada attempt
        $this->ok($questions->saveWithOptions($quizId, ['question_text' => 'baru', 'points' => 5], ['a', 'b'], 0) === false, 'Tambah soal ditolak setelah ada attempt');
        $this->ok($questions->deleteFromQuiz((int) $q1, $quizId) === false, 'Hapus soal ditolak setelah ada attempt');

        // --- Rekap guru
        $stats = $attempts->getStatistics($quizId);
        $this->ok($stats['submitted'] === 1 && $stats['total'] >= 1 && abs((float) $stats['average'] - 25.0) < 0.001, 'Statistik: 1 submitted, rata-rata 25');
        $found = false;
        foreach ($attempts->getRosterByQuiz($quizId) as $r) {
            if ((int) $r['student_id'] === $sid && $r['status'] === 'submitted') {
                $found = true;
            }
        }
        $this->ok($found, 'Daftar hasil memuat siswa dengan status submitted');

        // --- Batas waktu: attempt kedaluwarsa diselesaikan otomatis
        $timedId = $quizzes->insert(['course_id' => $cid, 'title' => 'UJI-4B-WAKTU', 'duration_minutes' => 1, 'is_published' => 0]);
        $tq      = $questions->saveWithOptions((int) $timedId, ['question_text' => 'Soal waktu', 'points' => 10], ['Ya', 'Tidak'], 0);
        $this->ok($timedId !== false && is_int($tq), 'Quiz berdurasi tersimpan');

        $timed = $attempts->start((int) $timedId, $sid);
        $db    = \Config\Database::connect();
        $db->table('quiz_attempts')->where('id', (int) $timed['id'])->update(['started_at' => date('Y-m-d H:i:s', time() - 7200)]);

        $quizTimed = $quizzes->find((int) $timedId);
        $timed     = $attempts->find((int) $timed['id']);

        $this->ok(QuizAttemptModel::isExpired($quizTimed, $timed) === true, 'Attempt melewati durasi terdeteksi kedaluwarsa');
        $this->ok(QuizAttemptModel::remainingSeconds($quizTimed, $timed) === 0, 'Sisa waktu 0');
        $this->ok($attempts->finalizeIfExpired((int) $timed['id']) === true, 'Attempt kedaluwarsa diselesaikan otomatis');

        $timedDone = $attempts->find((int) $timed['id']);
        $this->ok($timedDone['status'] === 'submitted' && abs((float) $timedDone['score']) < 0.001, 'Hasil otomatis: submitted dengan nilai 0');
        $this->ok($attempts->finalizeIfExpired((int) $timed['id']) === false, 'Penyelesaian otomatis tidak berulang');

        // --- Batas kelonggaran (durasi 60 detik: batas = started_at + 60)
        $startTs = strtotime((string) $timed['started_at']);
        $at      = static fn (int $offset): string => date('Y-m-d H:i:s', $startTs + $offset);

        $this->ok(QuizAttemptModel::isExpired($quizTimed, $timed, $at(59), 0) === false, 'Sebelum batas: belum kedaluwarsa');
        $this->ok(QuizAttemptModel::isExpired($quizTimed, $timed, $at(61), 0) === true, 'Tanpa kelonggaran: kedaluwarsa setelah batas');
        $this->ok(QuizAttemptModel::isExpired($quizTimed, $timed, $at(63), 5) === false, 'Kelonggaran 5 detik: masih diterima pada detik ke-63');
        $this->ok(QuizAttemptModel::isExpired($quizTimed, $timed, $at(66), 5) === true, 'Kelonggaran 5 detik: kedaluwarsa pada detik ke-66');
        $this->ok(QuizAttemptModel::isExpired($quizTimed, $timed, $at(66), 10) === false, 'Kelonggaran 10 detik: masih diterima pada detik ke-66');
    }

    /**
     * ID opsi benar ($correct = true) atau opsi salah pertama ($correct = false) pada satu pertanyaan.
     */
    private function optionId(array $question, bool $correct): int
    {
        foreach ($question['options'] as $option) {
            if (((int) $option['is_correct'] === 1) === $correct) {
                return (int) $option['id'];
            }
        }

        return 0;
    }

    private function ok(bool $cond, string $label): void
    {
        if ($cond) {
            $this->passed++;
            CLI::write('  [OK]    ' . $label, 'green');

            return;
        }

        $this->failed++;
        CLI::write('  [GAGAL] ' . $label, 'red');
    }
}
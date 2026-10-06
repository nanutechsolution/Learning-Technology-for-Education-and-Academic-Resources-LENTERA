<?php

namespace App\Controllers\Siswa;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\CourseStudentModel;
use App\Models\QuestionModel;
use App\Models\QuizAnswerModel;
use App\Models\QuizAttemptModel;
use App\Models\QuizModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Quizzes extends BaseController
{
    /**
     * Kelonggaran (detik) untuk pengiriman jawaban tepat di batas waktu.
     */
    private const SUBMIT_GRACE = 10;

    public function __construct()
    {
        helper(['auth', 'url']);
    }

    /**
     * Daftar quiz published pada satu course yang diikuti siswa.
     */
    public function index($courseId)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $courseId = (int) $courseId;

        if (! (new CourseStudentModel())->isEnrolled($courseId, $studentId)) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $course = (new CourseModel())->findWithRelations($courseId);
        if (! $course) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $attempts = new QuizAttemptModel();
        $quizzes  = [];

        foreach ((new QuizModel())->getPublishedByCourse($courseId, $studentId) as $row) {
            if (($row['attempt_status'] ?? null) === QuizAttemptModel::STATUS_IN_PROGRESS) {
                $attempt = $this->settle($row, $attempts->findByQuizAndStudent((int) $row['id'], $studentId));

                if ($attempt !== null) {
                    $row['attempt_status']       = $attempt['status'];
                    $row['attempt_score']        = $attempt['score'];
                    $row['attempt_submitted_at'] = $attempt['submitted_at'];
                }
            }

            if (($row['attempt_status'] ?? null) === QuizAttemptModel::STATUS_SUBMITTED) {
                $row['state'] = 'submitted';
            } elseif (($row['attempt_status'] ?? null) === QuizAttemptModel::STATUS_IN_PROGRESS) {
                $row['state'] = 'in_progress';
            } else {
                $row['state'] = QuizModel::accessState($row); // upcoming | open | closed
            }

            $quizzes[] = $row;
        }

        $course = (array) $course;

        return auth_no_cache(
            $this->response->setBody(view('siswa/quizzes/index', [
                'title'   => 'Quiz: ' . $course['title'],
                'course'  => $course,
                'quizzes' => $quizzes,
            ]))
        );
    }

    /**
     * Detail quiz: instruksi, status, tombol mulai/lanjutkan, dan hasil jika sudah selesai.
     */
    public function show($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $quiz     = $this->findQuiz((int) $id, $studentId);
        $attempts = new QuizAttemptModel();
        $attempt  = $this->settle($quiz, $attempts->findByQuizAndStudent((int) $quiz['id'], $studentId));
        $access   = QuizModel::accessState($quiz);

        $state = $access;
        if ($attempt !== null && $attempt['status'] === QuizAttemptModel::STATUS_SUBMITTED) {
            $state = 'submitted';
        } elseif ($attempt !== null && $attempt['status'] === QuizAttemptModel::STATUS_IN_PROGRESS) {
            $state = 'in_progress';
        }

        $points        = null;
        $review        = [];
        $reviewAllowed = false;

        if ($state === 'submitted') {
            $points        = $attempts->pointsSummary((int) $attempt['id'], (int) $quiz['id']);
            $reviewAllowed = empty($quiz['end_at']) || strtotime(date('Y-m-d H:i:s')) > strtotime((string) $quiz['end_at']);

            if ($reviewAllowed) {
                $review = $attempts->getReview((int) $attempt['id'], (int) $quiz['id']);
            }
        }

        return auth_no_cache(
            $this->response->setBody(view('siswa/quizzes/show', [
                'title'         => $quiz['title'],
                'quiz'          => $quiz,
                'attempt'       => $attempt,
                'state'         => $state,
                'canStart'      => $state === QuizModel::STATE_OPEN && (int) $quiz['question_count'] > 0,
                'points'        => $points,
                'review'        => $review,
                'reviewAllowed' => $reviewAllowed,
            ]))
        );
    }

    /**
     * Mulai quiz (POST). Attempt dibuat sekali per siswa dan quiz.
     */
    public function start($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $quiz     = $this->findQuiz((int) $id, $studentId);
        $back     = 'siswa/quizzes/' . (int) $quiz['id'];
        $attempts = new QuizAttemptModel();

        $existing = $this->settle($quiz, $attempts->findByQuizAndStudent((int) $quiz['id'], $studentId));

        if ($existing !== null) {
            if ($existing['status'] === QuizAttemptModel::STATUS_IN_PROGRESS) {
                return redirect()->to($back . '/take');
            }

            return redirect()->to($back)->with('warning', 'Anda sudah mengerjakan quiz ini dan tidak dapat mengulang.');
        }

        $access = QuizModel::accessState($quiz);

        if ($access === QuizModel::STATE_UPCOMING) {
            return redirect()->to($back)->with('error', 'Quiz belum dibuka.');
        }

        if ($access !== QuizModel::STATE_OPEN) {
            return redirect()->to($back)->with('error', 'Quiz sudah ditutup.');
        }

        if ((int) $quiz['question_count'] < 1) {
            return redirect()->to($back)->with('error', 'Quiz ini belum memiliki pertanyaan.');
        }

        if ($attempts->start((int) $quiz['id'], $studentId) === null) {
            return redirect()->to($back)->with('error', 'Quiz gagal dimulai. Coba lagi.');
        }

        return redirect()->to($back . '/take');
    }

    /**
     * Halaman pengerjaan quiz (hanya attempt in_progress milik siswa).
     */
    public function take($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $quiz    = $this->findQuiz((int) $id, $studentId);
        $back    = 'siswa/quizzes/' . (int) $quiz['id'];
        $attempt = $this->settle($quiz, (new QuizAttemptModel())->findByQuizAndStudent((int) $quiz['id'], $studentId));

        if ($attempt === null) {
            return redirect()->to($back)->with('warning', 'Mulai quiz terlebih dahulu.');
        }

        if ($attempt['status'] !== QuizAttemptModel::STATUS_IN_PROGRESS) {
            return redirect()->to($back)->with('warning', 'Quiz ini sudah selesai dikerjakan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('siswa/quizzes/take', [
                'title'     => $quiz['title'],
                'quiz'      => $quiz,
                'attempt'   => $attempt,
                'questions' => (new QuestionModel())->getWithOptions((int) $quiz['id'], false),
                'answers'   => (new QuizAnswerModel())->getByAttempt((int) $attempt['id']),
                'remaining' => QuizAttemptModel::remainingSeconds($quiz, $attempt),
            ]))
        );
    }

    /**
     * Simpan jawaban sementara (POST) tanpa menyelesaikan quiz.
     */
    public function save($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $quiz     = $this->findQuiz((int) $id, $studentId);
        $back     = 'siswa/quizzes/' . (int) $quiz['id'];
        $attempts = new QuizAttemptModel();
        $attempt  = $this->settle($quiz, $attempts->findByQuizAndStudent((int) $quiz['id'], $studentId));

        if ($attempt === null) {
            return redirect()->to($back)->with('warning', 'Mulai quiz terlebih dahulu.');
        }

        if ($attempt['status'] !== QuizAttemptModel::STATUS_IN_PROGRESS) {
            return redirect()->to($back)->with('warning', 'Waktu habis atau quiz sudah dikumpulkan. Jawaban tidak dapat diubah lagi.');
        }

        if (! $attempts->saveDraft((int) $attempt['id'], $this->collectAnswers())) {
            return redirect()->to($back . '/take')->with('error', 'Jawaban gagal disimpan. Coba lagi.');
        }

        return redirect()->to($back . '/take')->with('success', 'Jawaban sementara tersimpan.');
    }

    /**
     * Kumpulkan quiz (POST). Satu kali saja; nilai dihitung otomatis.
     */
    public function submit($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $quiz     = $this->findQuiz((int) $id, $studentId);
        $back     = 'siswa/quizzes/' . (int) $quiz['id'];
        $attempts = new QuizAttemptModel();
        $attempt  = $attempts->findByQuizAndStudent((int) $quiz['id'], $studentId);

        if ($attempt === null) {
            return redirect()->to($back)->with('warning', 'Mulai quiz terlebih dahulu.');
        }

        if ($attempt['status'] !== QuizAttemptModel::STATUS_IN_PROGRESS) {
            return redirect()->to($back)->with('warning', 'Quiz ini sudah dikumpulkan sebelumnya.');
        }

        // Lewat batas (termasuk kelonggaran): selesaikan otomatis dengan jawaban yang sudah tersimpan.
        if (QuizAttemptModel::isExpired($quiz, $attempt, null, self::SUBMIT_GRACE)) {
            $attempts->finalizeIfExpired((int) $attempt['id']);

            return redirect()->to($back)
                ->with('warning', 'Waktu pengerjaan telah habis. Quiz diselesaikan otomatis dengan jawaban yang sudah tersimpan.');
        }

        if (! $attempts->submit((int) $attempt['id'], $this->collectAnswers())) {
            return redirect()->to($back)->with('warning', 'Quiz ini sudah dikumpulkan sebelumnya.');
        }

        return redirect()->to($back)->with('success', 'Quiz berhasil dikumpulkan.');
    }

    // ------------------------------------------------------------------
    // Helper privat
    // ------------------------------------------------------------------

    private function studentId(): ?int
    {
        $student = (new StudentModel())->findByUserId(auth_id());

        return $student ? (int) ((array) $student)['id'] : null;
    }

    private function noProfile()
    {
        return redirect()->to('siswa/dashboard')
            ->with('warning', 'Akun Anda belum terhubung dengan profil siswa. Hubungi admin.');
    }

    /**
     * Quiz published pada course yang diikuti siswa, atau 404.
     */
    private function findQuiz(int $id, int $studentId): array
    {
        $quiz = (new QuizModel())->findForStudent($id, $studentId);

        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        return $quiz;
    }

    /**
     * Jika attempt in_progress sudah lewat batas waktu, selesaikan otomatis
     * lalu kembalikan attempt terbaru.
     */
    private function settle(array $quiz, ?array $attempt): ?array
    {
        if ($attempt === null || $attempt['status'] !== QuizAttemptModel::STATUS_IN_PROGRESS) {
            return $attempt;
        }

        if (! QuizAttemptModel::isExpired($quiz, $attempt)) {
            return $attempt;
        }

        $model = new QuizAttemptModel();
        $model->finalizeIfExpired((int) $attempt['id']);

        $fresh = $model->find((int) $attempt['id']);

        return $fresh ?: null;
    }

    /**
     * Jawaban dari form sebagai [question_id => option_id]. Hanya bilangan bulat positif;
     * kecocokan opsi dengan soal diperiksa lagi oleh Model.
     *
     * @return array<int, int>
     */
    private function collectAnswers(): array
    {
        $raw = $this->request->getPost('answers');

        if (! is_array($raw)) {
            return [];
        }

        $answers = [];

        foreach ($raw as $questionId => $optionId) {
            if (! is_scalar($optionId) || ! ctype_digit((string) $questionId) || ! ctype_digit((string) $optionId)) {
                continue;
            }

            if ((int) $questionId > 0 && (int) $optionId > 0) {
                $answers[(int) $questionId] = (int) $optionId;
            }
        }

        return $answers;
    }
}
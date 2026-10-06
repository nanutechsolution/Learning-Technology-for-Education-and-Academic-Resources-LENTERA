<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\QuizAttemptModel;
use App\Models\QuizModel;
use App\Models\TeacherModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Attempts extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    /**
     * Hasil pengerjaan satu quiz: statistik dan daftar siswa.
     */
    public function index($quizId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $quiz = (new QuizModel())->findOwnedByTeacher((int) $quizId, (int) $teacher['id']);
        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        $attempts = new QuizAttemptModel();

        // Selesaikan otomatis attempt yang sudah lewat batas waktu sebelum menghitung.
        $attempts->finalizeExpiredByQuiz((int) $quiz['id']);

        return auth_no_cache(
            $this->response->setBody(view('guru/attempts/index', [
                'title'  => 'Hasil: ' . $quiz['title'],
                'quiz'   => $quiz,
                'stats'  => $attempts->getStatistics((int) $quiz['id']),
                'roster' => $attempts->getRosterByQuiz((int) $quiz['id']),
            ]))
        );
    }

    /**
     * Detail jawaban satu siswa (hanya attempt yang sudah selesai).
     */
    public function show($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $attempts = new QuizAttemptModel();
        $attempt  = $attempts->findForTeacher((int) $id, (int) $teacher['id']);
        if ($attempt === null) {
            throw PageNotFoundException::forPageNotFound('Hasil pengerjaan tidak ditemukan.');
        }

        // Jika batas waktunya sudah lewat, selesaikan otomatis lalu muat ulang.
        if ($attempts->finalizeIfExpired((int) $attempt['id'])) {
            $attempt = $attempts->findForTeacher((int) $id, (int) $teacher['id']);
        }

        if ($attempt === null) {
            throw PageNotFoundException::forPageNotFound('Hasil pengerjaan tidak ditemukan.');
        }

        if ($attempt['status'] !== QuizAttemptModel::STATUS_SUBMITTED) {
            return redirect()->to('guru/quizzes/' . (int) $attempt['quiz_id'] . '/results')
                ->with('warning', 'Siswa ini masih mengerjakan quiz. Detail jawaban tersedia setelah dikumpulkan.');
        }

        $quiz = (new QuizModel())->findOwnedByTeacher((int) $attempt['quiz_id'], (int) $teacher['id']);
        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/attempts/show', [
                'title'   => 'Jawaban: ' . $attempt['student_name'],
                'quiz'    => $quiz,
                'attempt' => $attempt,
                'points'  => $attempts->pointsSummary((int) $attempt['id'], (int) $quiz['id']),
                'review'  => $attempts->getReview((int) $attempt['id'], (int) $quiz['id']),
            ]))
        );
    }

    // ------------------------------------------------------------------
    // Helper privat
    // ------------------------------------------------------------------

    private function teacher(): ?array
    {
        $teacher = (new TeacherModel())->findByUserId(auth_id());

        return $teacher ? (array) $teacher : null;
    }

    private function noProfile()
    {
        return redirect()->to('guru/dashboard')
            ->with('warning', 'Akun Anda belum terhubung dengan profil guru. Hubungi admin.');
    }
}

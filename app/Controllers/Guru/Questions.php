<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\QuestionModel;
use App\Models\QuizModel;
use App\Models\QuizOptionModel;
use App\Models\TeacherModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Questions extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    /**
     * Form tambah pertanyaan pada satu quiz milik guru.
     */
    public function create($quizId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $quiz = $this->findQuiz((int) $quizId, (int) $teacher['id']);
        $model = new QuestionModel();

        if ($model->isQuizLocked((int) $quiz['id'])) {
            return $this->lockedRedirect($quiz);
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/questions/create', [
                'title'     => 'Tambah Pertanyaan',
                'quiz'      => $quiz,
                'question'  => null,
                'options'   => [],
                'nextOrder' => $model->nextOrder((int) $quiz['id']),
            ]))
        );
    }

    /**
     * Simpan pertanyaan baru beserta opsinya.
     */
    public function store($quizId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $quiz  = $this->findQuiz((int) $quizId, (int) $teacher['id']);
        $model = new QuestionModel();

        [$question, $optionTexts, $correct] = $this->collectInput();

        $saved = $model->saveWithOptions((int) $quiz['id'], $question, $optionTexts, $correct);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('error', $this->errorsOf($model));
        }

        return redirect()->to('guru/quizzes/' . (int) $quiz['id'])
            ->with('success', 'Pertanyaan berhasil ditambahkan.');
    }

    /**
     * Form edit pertanyaan.
     */
    public function edit($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model    = new QuestionModel();
        $question = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($question === null) {
            throw PageNotFoundException::forPageNotFound('Pertanyaan tidak ditemukan.');
        }

        $quiz = $this->findQuiz((int) $question['quiz_id'], (int) $teacher['id']);

        if ($model->isQuizLocked((int) $quiz['id'])) {
            return $this->lockedRedirect($quiz);
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/questions/edit', [
                'title'     => 'Edit Pertanyaan',
                'quiz'      => $quiz,
                'question'  => $question,
                'options'   => (new QuizOptionModel())->getByQuestion((int) $question['id']),
                'nextOrder' => (int) $question['order_number'],
            ]))
        );
    }

    /**
     * Simpan perubahan pertanyaan; opsi lama diganti seluruhnya dalam satu transaksi.
     */
    public function update($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model    = new QuestionModel();
        $question = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($question === null) {
            throw PageNotFoundException::forPageNotFound('Pertanyaan tidak ditemukan.');
        }

        $quiz = $this->findQuiz((int) $question['quiz_id'], (int) $teacher['id']);

        [$input, $optionTexts, $correct] = $this->collectInput();

        $saved = $model->saveWithOptions((int) $quiz['id'], $input, $optionTexts, $correct, (int) $question['id']);

        if ($saved === false) {
            return redirect()->back()->withInput()->with('error', $this->errorsOf($model));
        }

        return redirect()->to('guru/quizzes/' . (int) $quiz['id'])
            ->with('success', 'Pertanyaan berhasil diperbarui.');
    }

    /**
     * Hapus pertanyaan (POST). Ditolak jika quiz sudah dikerjakan siswa.
     */
    public function delete($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model    = new QuestionModel();
        $question = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($question === null) {
            throw PageNotFoundException::forPageNotFound('Pertanyaan tidak ditemukan.');
        }

        $quizId = (int) $question['quiz_id'];

        if ($model->deleteFromQuiz((int) $question['id'], $quizId) === false) {
            return redirect()->to('guru/quizzes/' . $quizId)->with('error', $this->errorsOf($model));
        }

        return redirect()->to('guru/quizzes/' . $quizId)
            ->with('success', 'Pertanyaan berhasil dihapus.');
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

    /**
     * Quiz milik guru, atau 404.
     */
    private function findQuiz(int $quizId, int $teacherId): array
    {
        $quiz = (new QuizModel())->findOwnedByTeacher($quizId, $teacherId);

        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        return $quiz;
    }

    private function lockedRedirect(array $quiz)
    {
        return redirect()->to('guru/quizzes/' . (int) $quiz['id'])
            ->with('warning', 'Quiz sudah dikerjakan siswa, sehingga pertanyaan tidak dapat ditambah atau diubah.');
    }

    /**
     * @return list<string>
     */
    private function errorsOf(QuestionModel $model): array
    {
        $errors = $model->saveErrors();

        return $errors === [] ? ['Pertanyaan gagal disimpan.'] : array_values($errors);
    }

    /**
     * Ambil input form: [data pertanyaan, teks opsi (indeks 0-4), indeks jawaban benar atau NULL].
     *
     * @return array{0: array, 1: array<int, string>, 2: ?string}
     */
    private function collectInput(): array
    {
        $question = [
            'question_text' => trim((string) $this->request->getPost('question_text')),
            'points'        => trim((string) $this->request->getPost('points')),
            'order_number'  => trim((string) $this->request->getPost('order_number')),
        ];

        $raw     = $this->request->getPost('options');
        $raw     = is_array($raw) ? $raw : [];
        $options = [];

        for ($i = 0; $i < QuestionModel::MAX_OPTIONS; $i++) {
            $options[$i] = isset($raw[$i]) && is_scalar($raw[$i]) ? (string) $raw[$i] : '';
        }

        $correctRaw = $this->request->getPost('correct');
        $correct    = null;

        if (is_scalar($correctRaw) && ctype_digit((string) $correctRaw) && (int) $correctRaw < QuestionModel::MAX_OPTIONS) {
            $correct = (string) (int) $correctRaw;
        }

        return [$question, $options, $correct];
    }
}

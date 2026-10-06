<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\QuestionModel;
use App\Models\QuizModel;
use App\Models\TeacherModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;

class Quizzes extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    /**
     * Daftar quiz pada satu course milik guru.
     */
    public function index($courseId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $course = $this->findCourse($this->ownedCourses((int) $teacher['id']), (int) $courseId);
        if ($course === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $model = new QuizModel();

        return auth_no_cache(
            $this->response->setBody(view('guru/quizzes/index', [
                'title'   => 'Quiz: ' . $course['title'],
                'course'  => $course,
                'quizzes' => $model->getByCourse((int) $course['id']),
                'counts'  => $model->countByCourse((int) $course['id']),
            ]))
        );
    }

    /**
     * Detail quiz + daftar pertanyaan (dengan kunci jawaban, khusus guru pemilik).
     */
    public function show($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model = new QuizModel();
        $quiz  = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/quizzes/show', [
                'title'     => $quiz['title'],
                'quiz'      => $quiz,
                'state'     => QuizModel::accessState($quiz),
                'locked'    => $model->hasAttempts((int) $quiz['id']),
                'questions' => (new QuestionModel())->getWithOptions((int) $quiz['id'], true),
            ]))
        );
    }

    /**
     * Form tambah quiz.
     */
    public function create($courseId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $course = $this->findCourse($this->ownedCourses((int) $teacher['id']), (int) $courseId);
        if ($course === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/quizzes/create', [
                'title'  => 'Tambah Quiz',
                'course' => $course,
                'quiz'   => null,
                'locked' => false,
            ]))
        );
    }

    /**
     * Simpan quiz baru. Quiz baru selalu berstatus draft.
     */
    public function store($courseId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $course = $this->findCourse($this->ownedCourses((int) $teacher['id']), (int) $courseId);
        if ($course === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        [$data, $errors] = $this->collectInput(true);

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', $errors);
        }

        $data['course_id']    = (int) $course['id'];
        $data['is_published'] = 0;

        $model = new QuizModel();
        $newId = $model->insert($data);

        if ($newId === false) {
            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        return redirect()->to('guru/quizzes/' . (int) $newId)
            ->with('success', 'Quiz berhasil ditambahkan sebagai draft. Silakan tambahkan pertanyaan.');
    }

    /**
     * Form edit quiz.
     */
    public function edit($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model = new QuizModel();
        $quiz  = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        $course = $this->findCourse($this->ownedCourses((int) $teacher['id']), (int) $quiz['course_id']);
        if ($course === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/quizzes/edit', [
                'title'  => 'Edit Quiz',
                'course' => $course,
                'quiz'   => $quiz,
                'locked' => $model->hasAttempts((int) $quiz['id']),
            ]))
        );
    }

    /**
     * Simpan perubahan quiz. Jika sudah ada attempt, durasi dan jadwal tidak diubah.
     */
    public function update($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model = new QuizModel();
        $quiz  = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        $locked = $model->hasAttempts((int) $quiz['id']);

        [$data, $errors] = $this->collectInput(! $locked);

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', $errors);
        }

        if ($model->update((int) $quiz['id'], $data) === false) {
            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        return redirect()->to('guru/quizzes/' . (int) $quiz['id'])
            ->with('success', 'Quiz berhasil diperbarui.');
    }

    /**
     * Hapus quiz (POST). Soal, opsi, attempt, dan jawaban ikut terhapus oleh CASCADE.
     */
    public function delete($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model = new QuizModel();
        $quiz  = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        if ($model->delete((int) $quiz['id']) === false) {
            return redirect()->back()->with('error', 'Quiz gagal dihapus.');
        }

        return redirect()->to('guru/courses/' . (int) $quiz['course_id'] . '/quizzes')
            ->with('success', 'Quiz berhasil dihapus beserta soal dan hasil pengerjaannya.');
    }

    /**
     * Publish / kembalikan ke draft (POST).
     */
    public function togglePublish($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model = new QuizModel();
        $quiz  = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($quiz === null) {
            throw PageNotFoundException::forPageNotFound('Quiz tidak ditemukan.');
        }

        $publish = (int) $quiz['is_published'] !== 1;
        $error   = $model->setPublished((int) $quiz['id'], $publish);

        if ($error !== null) {
            return redirect()->back()->with('error', $error);
        }

        return redirect()->back()->with(
            'success',
            $publish ? 'Quiz berhasil dipublikasikan.' : 'Quiz dikembalikan ke draft.'
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

    /**
     * Semua course milik guru (sumber tunggal otorisasi course).
     */
    private function ownedCourses(int $teacherId): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            (new CourseModel())->getByTeacher($teacherId)
        );
    }

    private function findCourse(array $courses, int $courseId): ?array
    {
        foreach ($courses as $course) {
            if ((int) $course['id'] === $courseId) {
                return $course;
            }
        }

        return null;
    }

    /**
     * Ambil input form yang sudah dirapikan.
     * Jika $withSchedule false, durasi dan jadwal diabaikan (quiz sudah punya attempt).
     *
     * @return array{0: array, 1: list<string>} [data, daftar error]
     */
    private function collectInput(bool $withSchedule): array
    {
        $errors = [];

        $data = [
            'title'       => trim((string) $this->request->getPost('title')),
            'description' => trim((string) $this->request->getPost('description')),
        ];

        if ($withSchedule) {
            [$startAt, $startError] = $this->parseDateTime((string) $this->request->getPost('start_at'), 'Waktu mulai');
            [$endAt, $endError]     = $this->parseDateTime((string) $this->request->getPost('end_at'), 'Waktu berakhir');

            foreach ([$startError, $endError] as $error) {
                if ($error !== null) {
                    $errors[] = $error;
                }
            }

            if ($errors === []) {
                $scheduleError = QuizModel::scheduleError($startAt, $endAt);

                if ($scheduleError !== null) {
                    $errors[] = $scheduleError;
                }
            }

            $data['duration_minutes'] = trim((string) $this->request->getPost('duration_minutes'));
            $data['start_at']         = $startAt;
            $data['end_at']           = $endAt;
        }

        return [$data, $errors];
    }

    /**
     * Ubah input datetime-local atau teks menjadi 'Y-m-d H:i:s'.
     * Kosong = NULL. Tanggal yang tidak ada di kalender ditolak.
     *
     * @return array{0: ?string, 1: ?string} [nilai, pesan error]
     */
    private function parseDateTime(string $raw, string $label): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [null, null];
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $date   = DateTimeImmutable::createFromFormat('!' . $format, $raw);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return [$date->format('Y-m-d H:i:s'), null];
            }
        }

        return [null, 'Format ' . strtolower($label) . ' tidak valid.'];
    }
}
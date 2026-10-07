<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\AnnouncementModel;
use App\Models\CourseModel;
use App\Models\TeacherModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Guru: mengelola pengumuman pada course miliknya; dapat melihat pengumuman sekolah (published).
 */
class Announcements extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    /**
     * Semua pengumuman yang terlihat oleh guru (course miliknya + pengumuman sekolah published).
     */
    public function index()
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/announcements/index', [
                'title'         => 'Pengumuman',
                'course'        => null,
                'announcements' => (new AnnouncementModel())->getForTeacher((int) $teacher['id']),
                'teacherId'     => (int) $teacher['id'],
            ]))
        );
    }

    /**
     * Pengumuman satu course milik guru.
     */
    public function byCourse($courseId)
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
            $this->response->setBody(view('guru/announcements/index', [
                'title'         => 'Pengumuman: ' . $course['title'],
                'course'        => $course,
                'announcements' => (new AnnouncementModel())->getByCourse((int) $course['id']),
                'teacherId'     => (int) $teacher['id'],
            ]))
        );
    }

    /**
     * Form tambah. $courseId (opsional) = course yang dipilih awal.
     */
    public function create($courseId = null)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $courses = $this->ownedCourses((int) $teacher['id']);

        if ($courseId !== null && $this->findCourse($courses, (int) $courseId) === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        if ($courses === []) {
            return redirect()->to('guru/announcements')
                ->with('warning', 'Anda belum memiliki course untuk dibuatkan pengumuman.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/announcements/create', [
                'title'        => 'Tambah Pengumuman',
                'courses'      => $courses,
                'courseId'     => $courseId !== null ? (int) $courseId : (int) $courses[0]['id'],
                'announcement' => null,
            ]))
        );
    }

    public function store()
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        // Course dari form WAJIB salah satu course milik guru ini (anti-IDOR).
        $targetId = (int) $this->request->getPost('course_id');

        if ($targetId < 1) {
            return redirect()->back()->withInput()->with('error', 'Course wajib dipilih.');
        }

        if ($this->findCourse($this->ownedCourses((int) $teacher['id']), $targetId) === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $model = new AnnouncementModel();
        $data  = $this->collectInput();

        $data['course_id'] = $targetId;
        $data['author_id'] = auth_id();

        if ($model->insert($data) === false) {
            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        return redirect()->to('guru/courses/' . $targetId . '/announcements')
            ->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    /**
     * Detail: pengumuman course milik guru (semua status) atau pengumuman sekolah published.
     */
    public function show($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $announcement = (new AnnouncementModel())->findForTeacher((int) $id, (int) $teacher['id']);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/announcements/show', [
                'title'        => $announcement['title'],
                'announcement' => $announcement,
                'canManage'    => $announcement['course_id'] !== null
                    && (int) $announcement['course_teacher_id'] === (int) $teacher['id'],
            ]))
        );
    }

    public function edit($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $announcement = (new AnnouncementModel())->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/announcements/edit', [
                'title'        => 'Edit Pengumuman',
                'announcement' => $announcement,
            ]))
        );
    }

    public function update($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model        = new AnnouncementModel();
        $announcement = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman tidak ditemukan.');
        }

        // Course & penulis tidak dapat diubah lewat form edit.
        if ($model->update((int) $announcement['id'], $this->collectInput()) === false) {
            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        return redirect()->to('guru/announcements/' . (int) $announcement['id'])
            ->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function delete($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model        = new AnnouncementModel();
        $announcement = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman tidak ditemukan.');
        }

        if ($model->delete((int) $announcement['id']) === false) {
            return redirect()->back()->with('error', 'Pengumuman gagal dihapus.');
        }

        return redirect()->to('guru/courses/' . (int) $announcement['course_id'] . '/announcements')
            ->with('success', 'Pengumuman berhasil dihapus.');
    }

    public function togglePublish($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model        = new AnnouncementModel();
        $announcement = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman tidak ditemukan.');
        }

        $publish = (int) $announcement['is_published'] !== 1;

        if ($model->update((int) $announcement['id'], ['is_published' => $publish ? 1 : 0]) === false) {
            return redirect()->back()->with('error', array_values($model->errors()));
        }

        return redirect()->back()->with(
            'success',
            $publish ? 'Pengumuman berhasil dipublikasikan.' : 'Pengumuman dikembalikan ke draft.'
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

    private function ownedCourses(int $teacherId): array
    {
        return array_map(
            static fn($row) => (array) $row,
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

    private function collectInput(): array
    {
        return [
            'title'        => trim((string) $this->request->getPost('title')),
            'content'      => trim((string) $this->request->getPost('content')),
            'is_published' => (string) $this->request->getPost('is_published') === '1' ? 1 : 0,
        ];
    }
}

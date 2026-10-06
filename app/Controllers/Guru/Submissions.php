<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\SubmissionModel;
use App\Models\TeacherModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Submissions extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url', 'assignment_file']);
    }

    /**
     * Detail satu pengumpulan siswa + form penilaian.
     * Hanya untuk tugas pada course milik guru.
     */
    public function show($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $submission = (new SubmissionModel())->findForTeacher((int) $id, (int) $teacher['id']);
        if ($submission === null) {
            throw PageNotFoundException::forPageNotFound('Pengumpulan tidak ditemukan.');
        }

        $isLate = ! empty($submission['due_at'])
            && ! empty($submission['submitted_at'])
            && strtotime((string) $submission['submitted_at']) > strtotime((string) $submission['due_at']);

        return auth_no_cache(
            $this->response->setBody(view('guru/submissions/show', [
                'title'      => 'Pengumpulan: ' . $submission['student_name'],
                'submission' => $submission,
                'isLate'     => $isLate,
            ]))
        );
    }

    /**
     * Simpan nilai dan feedback (POST).
     */
    public function grade($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model      = new SubmissionModel();
        $submission = $model->findForTeacher((int) $id, (int) $teacher['id']);
        if ($submission === null) {
            throw PageNotFoundException::forPageNotFound('Pengumpulan tidak ditemukan.');
        }

        $ok = $model->grade(
            (int) $submission['id'],
            (string) $this->request->getPost('score'),
            (string) $this->request->getPost('feedback')
        );

        if (! $ok) {
            $errors = array_values($model->errors());

            if ($errors === []) {
                $errors = ['Pengumpulan belum dikirim siswa, sehingga belum dapat dinilai.'];
            }

            return redirect()->back()->withInput()->with('error', $errors);
        }

        return redirect()->to('guru/submissions/' . (int) $submission['id'])
            ->with('success', 'Nilai dan feedback berhasil disimpan.');
    }

    /**
     * Download file jawaban siswa (hanya guru pemilik course tugas ini).
     */
    public function download($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $submission = (new SubmissionModel())->findForTeacher((int) $id, (int) $teacher['id']);
        if ($submission === null || empty($submission['file_path'])) {
            throw PageNotFoundException::forPageNotFound('File jawaban tidak ditemukan.');
        }

        $path = submission_file_path($submission['file_path']);
        if ($path === null) {
            throw PageNotFoundException::forPageNotFound('File jawaban tidak ditemukan di server.');
        }

        return $this->response
            ->download($path, null)
            ->setFileName($submission['file_name'] ?: basename($path))
            ->setHeader('X-Content-Type-Options', 'nosniff');
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
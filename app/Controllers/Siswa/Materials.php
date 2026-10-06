<?php

namespace App\Controllers\Siswa;

use App\Controllers\BaseController;
use App\Models\MaterialModel;
use App\Models\MaterialViewModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Materials extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    /**
     * Detail materi + pencatatan view (satu record per siswa+materi).
     */
    public function show($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $material = (new MaterialModel())->findForStudent((int) $id, $studentId);
        if ($material === null) {
            throw PageNotFoundException::forPageNotFound('Materi tidak ditemukan.');
        }

        if (! (new MaterialViewModel())->record((int) $material['id'], $studentId)) {
            log_message('error', 'Gagal mencatat material_views: material ' . $material['id'] . ', siswa ' . $studentId);
        }

        return auth_no_cache(
            $this->response->setBody(view('siswa/materials/show', [
                'title'     => $material['title'],
                'material'  => $material,
                'youtubeId' => MaterialModel::youtubeId($material['video_url'] ?? null),
            ]))
        );
    }

    /**
     * Download file materi (hanya siswa terdaftar, materi published).
     */
    public function download($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $material = (new MaterialModel())->findForStudent((int) $id, $studentId);
        if ($material === null || empty($material['file_path'])) {
            throw PageNotFoundException::forPageNotFound('File materi tidak ditemukan.');
        }

        $name = basename((string) $material['file_path']);
        $path = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'materials' . DIRECTORY_SEPARATOR . $name;

        if ($name === '' || ! is_file($path)) {
            throw PageNotFoundException::forPageNotFound('File materi tidak ditemukan di server.');
        }

        return $this->response
            ->download($path, null)
            ->setFileName($material['file_name'] ?: $name)
            ->setHeader('X-Content-Type-Options', 'nosniff');
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
}

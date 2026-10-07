<?php

namespace App\Controllers\Siswa;

use App\Controllers\BaseController;
use App\Models\AnnouncementModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Siswa: hanya membaca pengumuman published yang ditujukan kepadanya
 * (pengumuman sekolah + pengumuman course yang diikuti).
 */
class Announcements extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    public function index()
    {
        return auth_no_cache(
            $this->response->setBody(view('siswa/announcements/index', [
                'title'         => 'Pengumuman',
                'announcements' => (new AnnouncementModel())->getForStudent($this->studentId()),
            ]))
        );
    }

    public function show($id)
    {
        $announcement = (new AnnouncementModel())->findForStudent((int) $id, $this->studentId());
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('siswa/announcements/show', [
                'title'        => $announcement['title'],
                'announcement' => $announcement,
            ]))
        );
    }

    /**
     * ID profil siswa, atau NULL jika akun belum punya profil
     * (akun tersebut hanya dapat melihat pengumuman sekolah).
     */
    private function studentId(): ?int
    {
        $student = (new StudentModel())->findByUserId(auth_id());

        return $student ? (int) ((array) $student)['id'] : null;
    }
}

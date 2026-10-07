<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AnnouncementModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Admin: melihat semua pengumuman, serta membuat/mengelola PENGUMUMAN SEKOLAH (course_id NULL).
 * Pengumuman course milik guru hanya dapat dilihat, tidak dapat diubah dari sini.
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
            $this->response->setBody(view('admin/announcements/index', [
                'title'         => 'Pengumuman',
                'announcements' => (new AnnouncementModel())->getForAdmin(),
            ]))
        );
    }

    public function create()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/announcements/create', [
                'title'        => 'Tambah Pengumuman Sekolah',
                'announcement' => null,
            ]))
        );
    }

    public function store()
    {
        $model = new AnnouncementModel();

        // Sengaja disusun eksplisit (bukan dari seluruh POST): course_id selalu NULL
        // karena admin hanya membuat pengumuman sekolah, author_id dari sesi.
        $data = $this->collectInput();

        $data['course_id'] = null;
        $data['author_id'] = auth_id();

        if ($model->insert($data) === false) {
            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        return redirect()->to('admin/announcements')
            ->with('success', 'Pengumuman sekolah berhasil ditambahkan.');
    }

    public function show($id)
    {
        $announcement = (new AnnouncementModel())->findForAdmin((int) $id);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('admin/announcements/show', [
                'title'        => $announcement['title'],
                'announcement' => $announcement,
                'canManage'    => $announcement['course_id'] === null,
            ]))
        );
    }

    public function edit($id)
    {
        $announcement = (new AnnouncementModel())->findSchool((int) $id);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman sekolah tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('admin/announcements/edit', [
                'title'        => 'Edit Pengumuman Sekolah',
                'announcement' => $announcement,
            ]))
        );
    }

    public function update($id)
    {
        $model        = new AnnouncementModel();
        $announcement = $model->findSchool((int) $id);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman sekolah tidak ditemukan.');
        }

        // course_id & author_id tidak pernah diubah lewat form.
        if ($model->update((int) $announcement['id'], $this->collectInput()) === false) {
            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        return redirect()->to('admin/announcements/' . (int) $announcement['id'])
            ->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function delete($id)
    {
        $model        = new AnnouncementModel();
        $announcement = $model->findSchool((int) $id);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman sekolah tidak ditemukan.');
        }

        if ($model->delete((int) $announcement['id']) === false) {
            return redirect()->back()->with('error', 'Pengumuman gagal dihapus.');
        }

        return redirect()->to('admin/announcements')
            ->with('success', 'Pengumuman berhasil dihapus.');
    }

    public function togglePublish($id)
    {
        $model        = new AnnouncementModel();
        $announcement = $model->findSchool((int) $id);
        if ($announcement === null) {
            throw PageNotFoundException::forPageNotFound('Pengumuman sekolah tidak ditemukan.');
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

    private function collectInput(): array
    {
        return [
            'title'        => trim((string) $this->request->getPost('title')),
            'content'      => trim((string) $this->request->getPost('content')),
            'is_published' => (string) $this->request->getPost('is_published') === '1' ? 1 : 0,
        ];
    }
}

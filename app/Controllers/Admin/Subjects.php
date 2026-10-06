<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\SubjectModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class Subjects extends BaseController
{
    private SubjectModel $subjects;

    public function __construct()
    {
        helper('auth');
        $this->subjects = new SubjectModel();
    }

    public function index()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/subjects/index', [
                'title'    => 'Mata Pelajaran',
                'subjects' => $this->subjects->orderBy('code', 'ASC')->findAll(),
            ]))
        );
    }

    public function create()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/subjects/form', [
                'title'   => 'Tambah Mata Pelajaran',
                'subject' => null,
                'action'  => base_url('admin/subjects'),
            ]))
        );
    }

    public function store()
    {
        $data = $this->collect();

        if (! $this->subjects->insert($data)) {
            return redirect()->back()->withInput()->with('error', array_values($this->subjects->errors()));
        }

        return redirect()->to('admin/subjects')->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function edit($id = null)
    {
        $subject = $this->findOr404($id);

        return auth_no_cache(
            $this->response->setBody(view('admin/subjects/form', [
                'title'   => 'Edit Mata Pelajaran',
                'subject' => $subject,
                'action'  => base_url('admin/subjects/' . (int) $id . '/update'),
            ]))
        );
    }

    public function update($id = null)
    {
        $this->findOr404($id);

        $data       = $this->collect();
        $data['id'] = (int) $id;

        // Wajib di CI4 >= 4.5: placeholder {id} pada is_unique butuh rule untuk field 'id'.
        $this->subjects->setValidationRule('id', 'permit_empty|is_natural_no_zero');

        if (! $this->subjects->update((int) $id, $data)) {
            return redirect()->back()->withInput()->with('error', array_values($this->subjects->errors()));
        }

        return redirect()->to('admin/subjects')->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        $subject = $this->findOr404($id);

        $courseCount = (new CourseModel())->where('subject_id', (int) $id)->countAllResults();

        if ($courseCount > 0) {
            return redirect()->to('admin/subjects')
                ->with('error', 'Mata pelajaran "' . $subject['name'] . '" tidak dapat dihapus karena masih dipakai oleh ' . $courseCount . ' course. Nonaktifkan saja jika tidak dipakai lagi.');
        }

        try {
            $this->subjects->delete((int) $id);
        } catch (DatabaseException $e) {
            return redirect()->to('admin/subjects')
                ->with('error', 'Mata pelajaran tidak dapat dihapus karena masih dipakai data lain.');
        }

        return redirect()->to('admin/subjects')->with('success', 'Mata pelajaran berhasil dihapus.');
    }

    private function collect(): array
    {
        return [
            'code'        => strtoupper(trim((string) $this->request->getPost('code'))),
            'name'        => trim((string) $this->request->getPost('name')),
            'description' => trim((string) $this->request->getPost('description')),
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function findOr404($id): array
    {
        $subject = $this->subjects->find((int) $id);

        if (! $subject) {
            throw PageNotFoundException::forPageNotFound('Mata pelajaran tidak ditemukan.');
        }

        return $subject;
    }
}

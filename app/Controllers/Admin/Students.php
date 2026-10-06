<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ClassModel;
use App\Models\StudentModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class Students extends BaseController
{
    private StudentModel $students;
    private UserModel $users;

    public function __construct()
    {
        helper(['auth', 'assignment_file']);
        $this->students = new StudentModel();
        $this->users    = new UserModel();
    }

    public function index()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/students/index', [
                'title'    => 'Siswa',
                'students' => $this->students->getAllWithRelations(),
            ]))
        );
    }

    public function create()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/students/form', [
                'title'   => 'Tambah Siswa',
                'student' => null,
                'action'  => base_url('admin/students'),
                'classes' => (new ClassModel())->getAllWithRelations(),
            ]))
        );
    }

    public function store()
    {
        $password = (string) $this->request->getPost('password');
        $confirm  = (string) $this->request->getPost('password_confirm');

        if ($password !== $confirm) {
            return redirect()->back()->withInput()->with('error', 'Konfirmasi password tidak sama.');
        }

        $user             = $this->collectUser();
        $user['password'] = $password;
        $user['role']     = 'siswa';
        $profile          = $this->collectProfile();

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $userId = $this->users->insert($user);

            if (! $userId) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', array_values($this->users->errors()));
            }

            $profile['user_id'] = (int) $userId;

            if (! $this->students->insert($profile)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', array_values($this->students->errors()));
            }

            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan data siswa.');
        }

        return redirect()->to('admin/students')->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function edit($id = null)
    {
        $student = $this->findOr404($id);

        return auth_no_cache(
            $this->response->setBody(view('admin/students/form', [
                'title'   => 'Edit Siswa',
                'student' => $student,
                'action'  => base_url('admin/students/' . (int) $id . '/update'),
                'classes' => (new ClassModel())->getAllWithRelations(),
            ]))
        );
    }

    public function update($id = null)
    {
        $student = $this->findOr404($id);

        $user       = $this->collectUser();
        $user['id'] = (int) $student['user_id'];

        $password = (string) $this->request->getPost('password');
        $confirm  = (string) $this->request->getPost('password_confirm');

        // Password opsional: key 'password' hanya dikirim jika diisi.
        if ($password !== '') {
            if ($password !== $confirm) {
                return redirect()->back()->withInput()->with('error', 'Konfirmasi password tidak sama.');
            }
            $user['password'] = $password;
        }

        $profile       = $this->collectProfile();
        $profile['id'] = (int) $id;

        // Wajib di CI4 >= 4.5: placeholder {id} butuh rule untuk field 'id'.
        $this->users->setValidationRule('id', 'permit_empty|is_natural_no_zero');
        $this->students->setValidationRule('id', 'permit_empty|is_natural_no_zero');

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            if (! $this->users->update((int) $student['user_id'], $user)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', array_values($this->users->errors()));
            }

            if (! $this->students->update((int) $id, $profile)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', array_values($this->students->errors()));
            }

            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui data siswa.');
        }

        return redirect()->to('admin/students')->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        $student = $this->findOr404($id);
        $back    = 'admin/students';

        $db = \Config\Database::connect();

        // Kumpulkan nama file jawaban SEBELUM siswa dihapus
        // (record submissions ikut terhapus oleh CASCADE).
        $answerFiles = array_column(
            $db->table('submissions')
                ->select('file_path')
                ->where('student_id', (int) $id)
                ->where('file_path IS NOT NULL', null, false)
                ->get()
                ->getResultArray(),
            'file_path'
        );

        $db->transBegin();

        try {
            $this->students->delete((int) $id);
            $this->users->delete((int) $student['user_id']);
            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return redirect()->to($back)
                ->with('error', 'Siswa tidak dapat dihapus karena masih dipakai data lain.');
        }

        // Pastikan siswa benar-benar sudah hilang sebelum menyentuh file di disk.
        if ($this->students->find((int) $id) !== null) {
            return redirect()->to($back)
                ->with('error', 'Siswa gagal dihapus.');
        }

        foreach ($answerFiles as $name) {
            submission_delete_file($name);
        }

        return redirect()->to($back)->with('success', 'Siswa dan akun loginnya berhasil dihapus.');
    }

    private function collectUser(): array
    {
        return [
            'name'      => trim((string) $this->request->getPost('name')),
            'username'  => trim((string) $this->request->getPost('username')),
            'email'     => trim((string) $this->request->getPost('email')),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }

    private function collectProfile(): array
    {
        return [
            'nis'      => trim((string) $this->request->getPost('nis')),
            'nisn'     => trim((string) $this->request->getPost('nisn')),
            'class_id' => trim((string) $this->request->getPost('class_id')),
            'phone'    => trim((string) $this->request->getPost('phone')),
            'address'  => trim((string) $this->request->getPost('address')),
        ];
    }

    private function findOr404($id): array
    {
        $student = $this->students->findWithRelations((int) $id);

        if (! $student) {
            throw PageNotFoundException::forPageNotFound('Siswa tidak ditemukan.');
        }

        return $student;
    }
}
<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\TeacherModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class Teachers extends BaseController
{
    private TeacherModel $teachers;
    private UserModel $users;

    public function __construct()
    {
        helper('auth');
        $this->teachers = new TeacherModel();
        $this->users    = new UserModel();
    }

    public function index()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/teachers/index', [
                'title'    => 'Guru',
                'teachers' => $this->teachers->getAllWithUser(),
            ]))
        );
    }

    public function create()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/teachers/form', [
                'title'   => 'Tambah Guru',
                'teacher' => null,
                'action'  => base_url('admin/teachers'),
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
        $user['role']     = 'guru';
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

            if (! $this->teachers->insert($profile)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', array_values($this->teachers->errors()));
            }

            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'Gagal menyimpan data guru.');
        }

        return redirect()->to('admin/teachers')->with('success', 'Guru berhasil ditambahkan.');
    }

    public function edit($id = null)
    {
        $teacher = $this->findOr404($id);

        return auth_no_cache(
            $this->response->setBody(view('admin/teachers/form', [
                'title'   => 'Edit Guru',
                'teacher' => $teacher,
                'action'  => base_url('admin/teachers/' . (int) $id . '/update'),
            ]))
        );
    }

    public function update($id = null)
    {
        $teacher = $this->findOr404($id);

        $user       = $this->collectUser();
        $user['id'] = (int) $teacher['user_id'];

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
        $this->teachers->setValidationRule('id', 'permit_empty|is_natural_no_zero');

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            if (! $this->users->update((int) $teacher['user_id'], $user)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', array_values($this->users->errors()));
            }

            if (! $this->teachers->update((int) $id, $profile)) {
                $db->transRollback();

                return redirect()->back()->withInput()->with('error', array_values($this->teachers->errors()));
            }

            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'Gagal memperbarui data guru.');
        }

        return redirect()->to('admin/teachers')->with('success', 'Data guru berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        $teacher = $this->findOr404($id);

        $courseCount = (new CourseModel())->where('teacher_id', (int) $id)->countAllResults();

        if ($courseCount > 0) {
            return redirect()->to('admin/teachers')
                ->with('error', 'Guru "' . $teacher['name'] . '" tidak dapat dihapus karena masih mengajar ' . $courseCount . ' course.');
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            $this->teachers->delete((int) $id);
            $this->users->delete((int) $teacher['user_id']);
            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return redirect()->to('admin/teachers')
                ->with('error', 'Guru tidak dapat dihapus karena masih dipakai data lain.');
        }

        return redirect()->to('admin/teachers')->with('success', 'Guru dan akun loginnya berhasil dihapus.');
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
            'nip'     => trim((string) $this->request->getPost('nip')),
            'phone'   => trim((string) $this->request->getPost('phone')),
            'address' => trim((string) $this->request->getPost('address')),
        ];
    }

    private function findOr404($id): array
    {
        $teacher = $this->teachers->findWithUser((int) $id);

        if (! $teacher) {
            throw PageNotFoundException::forPageNotFound('Guru tidak ditemukan.');
        }

        return $teacher;
    }
}

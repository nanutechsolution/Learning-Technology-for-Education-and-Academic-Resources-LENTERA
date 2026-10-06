<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\ClassModel;
use App\Models\CourseModel;
use App\Models\TeacherModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class Classes extends BaseController
{
    private ClassModel $classes;

    public function __construct()
    {
        helper('auth');
        $this->classes = new ClassModel();
    }

    public function index()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/classes/index', [
                'title'   => 'Kelas',
                'classes' => $this->classes->getAllWithRelations(),
            ]))
        );
    }

    public function create()
    {
        $active = (new AcademicYearModel())->getActive();

        return auth_no_cache(
            $this->response->setBody(view('admin/classes/form', $this->formData([
                'title'       => 'Tambah Kelas',
                'class'       => null,
                'action'      => base_url('admin/classes'),
                'defaultYear' => $active['id'] ?? '',
            ])))
        );
    }

    public function store()
    {
        $data = $this->collect();

        if ($error = $this->checkName($data, null)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        if (! $this->classes->insert($data)) {
            return redirect()->back()->withInput()->with('error', array_values($this->classes->errors()));
        }

        return redirect()->to('admin/classes')->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function edit($id = null)
    {
        $class = $this->findOr404($id);

        return auth_no_cache(
            $this->response->setBody(view('admin/classes/form', $this->formData([
                'title'       => 'Edit Kelas',
                'class'       => $class,
                'action'      => base_url('admin/classes/' . (int) $id . '/update'),
                'defaultYear' => '',
            ])))
        );
    }

    public function update($id = null)
    {
        $this->findOr404($id);

        $data       = $this->collect();
        $data['id'] = (int) $id;

        if ($error = $this->checkName($data, (int) $id)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        // Wajib di CI4 >= 4.5: placeholder {id} butuh rule untuk field 'id'.
        $this->classes->setValidationRule('id', 'permit_empty|is_natural_no_zero');

        if (! $this->classes->update((int) $id, $data)) {
            return redirect()->back()->withInput()->with('error', array_values($this->classes->errors()));
        }

        return redirect()->to('admin/classes')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        $class = $this->findOr404($id);

        $courseCount = (new CourseModel())->where('class_id', (int) $id)->countAllResults();

        if ($courseCount > 0) {
            return redirect()->to('admin/classes')
                ->with('error', 'Kelas "' . $class['name'] . '" tidak dapat dihapus karena masih dipakai oleh ' . $courseCount . ' course.');
        }

        try {
            $this->classes->delete((int) $id);
        } catch (DatabaseException $e) {
            return redirect()->to('admin/classes')
                ->with('error', 'Kelas tidak dapat dihapus karena masih dipakai data lain.');
        }

        return redirect()->to('admin/classes')->with('success', 'Kelas berhasil dihapus. Siswa di kelas ini menjadi belum punya kelas.');
    }

    private function collect(): array
    {
        return [
            'academic_year_id'    => trim((string) $this->request->getPost('academic_year_id')),
            'name'                => trim((string) $this->request->getPost('name')),
            'grade'               => trim((string) $this->request->getPost('grade')),
            'homeroom_teacher_id' => trim((string) $this->request->getPost('homeroom_teacher_id')),
        ];
    }

    private function checkName(array $data, ?int $ignoreId): ?string
    {
        if ($data['name'] === '' || ! ctype_digit($data['academic_year_id'])) {
            return null; // dilaporkan oleh validasi model
        }

        if ($this->classes->isNameTaken($data['name'], (int) $data['academic_year_id'], $ignoreId)) {
            return 'Nama kelas "' . $data['name'] . '" sudah dipakai pada tahun akademik tersebut.';
        }

        return null;
    }

    private function formData(array $extra): array
    {
        return $extra + [
            'years'    => (new AcademicYearModel())->orderBy('start_date', 'DESC')->findAll(),
            'teachers' => (new TeacherModel())->getAllWithUser(),
        ];
    }

    private function findOr404($id): array
    {
        $class = $this->classes->find((int) $id);

        if (! $class) {
            throw PageNotFoundException::forPageNotFound('Kelas tidak ditemukan.');
        }

        return $class;
    }
}

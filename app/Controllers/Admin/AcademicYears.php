<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\ClassModel;
use App\Models\CourseModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class AcademicYears extends BaseController
{
    private AcademicYearModel $years;

    public function __construct()
    {
        helper('auth');
        $this->years = new AcademicYearModel();
    }

    public function index()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/academic_years/index', [
                'title' => 'Tahun Akademik',
                'years' => $this->years->orderBy('start_date', 'DESC')->findAll(),
            ]))
        );
    }

    public function create()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/academic_years/form', [
                'title'  => 'Tambah Tahun Akademik',
                'year'   => null,
                'action' => base_url('admin/academic-years'),
            ]))
        );
    }

    public function store()
    {
        $data = $this->collect();

        if ($error = $this->checkDateRange($data)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        if (! $this->years->insert($data)) {
            return redirect()->back()->withInput()->with('error', array_values($this->years->errors()));
        }

        return redirect()->to('admin/academic-years')->with('success', 'Tahun akademik berhasil ditambahkan.');
    }

    public function edit($id = null)
    {
        $year = $this->findOr404($id);

        return auth_no_cache(
            $this->response->setBody(view('admin/academic_years/form', [
                'title'  => 'Edit Tahun Akademik',
                'year'   => $year,
                'action' => base_url('admin/academic-years/' . (int) $id . '/update'),
            ]))
        );
    }

    public function update($id = null)
    {
        $this->findOr404($id);

        $data       = $this->collect();
        $data['id'] = (int) $id;

        if ($error = $this->checkDateRange($data)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        // Wajib di CI4 >= 4.5: placeholder {id} pada is_unique butuh rule untuk field 'id'.
        $this->years->setValidationRule('id', 'permit_empty|is_natural_no_zero');

        if (! $this->years->update((int) $id, $data)) {
            return redirect()->back()->withInput()->with('error', array_values($this->years->errors()));
        }

        return redirect()->to('admin/academic-years')->with('success', 'Tahun akademik berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        $year = $this->findOr404($id);

        if (! empty($year['is_active'])) {
            return redirect()->to('admin/academic-years')
                ->with('error', 'Tahun akademik yang sedang aktif tidak dapat dihapus. Aktifkan tahun lain terlebih dahulu.');
        }

        $classCount  = (new ClassModel())->where('academic_year_id', (int) $id)->countAllResults();
        $courseCount = (new CourseModel())->where('academic_year_id', (int) $id)->countAllResults();

        if ($classCount > 0 || $courseCount > 0) {
            return redirect()->to('admin/academic-years')
                ->with('error', 'Tahun akademik tidak dapat dihapus karena masih dipakai oleh ' . $classCount . ' kelas dan ' . $courseCount . ' course.');
        }

        try {
            $this->years->delete((int) $id);
        } catch (DatabaseException $e) {
            return redirect()->to('admin/academic-years')
                ->with('error', 'Tahun akademik tidak dapat dihapus karena masih dipakai data lain.');
        }

        return redirect()->to('admin/academic-years')->with('success', 'Tahun akademik berhasil dihapus.');
    }

    public function activate($id = null)
    {
        $this->findOr404($id);

        try {
            $ok = $this->years->activate((int) $id);
        } catch (DatabaseException $e) {
            $ok = false;
        }

        if (! $ok) {
            return redirect()->to('admin/academic-years')->with('error', 'Gagal mengaktifkan tahun akademik.');
        }

        return redirect()->to('admin/academic-years')->with('success', 'Tahun akademik berhasil diaktifkan.');
    }

    private function collect(): array
    {
        return [
            'name'       => trim((string) $this->request->getPost('name')),
            'start_date' => trim((string) $this->request->getPost('start_date')),
            'end_date'   => trim((string) $this->request->getPost('end_date')),
        ];
    }

    private function checkDateRange(array $data): ?string
    {
        if ($data['start_date'] === '' || $data['end_date'] === '') {
            return null; // dilaporkan oleh validasi model
        }

        if (! $this->years->isDateRangeValid($data['start_date'], $data['end_date'])) {
            return 'Tanggal selesai harus setelah tanggal mulai.';
        }

        return null;
    }

    private function findOr404($id): array
    {
        $year = $this->years->find((int) $id);

        if (! $year) {
            throw PageNotFoundException::forPageNotFound('Tahun akademik tidak ditemukan.');
        }

        return $year;
    }
}

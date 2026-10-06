<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\ClassModel;
use App\Models\CourseModel;
use App\Models\CourseStudentModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;
use App\Models\TeacherModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;

class Courses extends BaseController
{
    private CourseModel $courses;

    public function __construct()
    {
        helper(['auth', 'material_file', 'assignment_file']);
        $this->courses = new CourseModel();
    }

    public function index()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/courses/index', [
                'title'   => 'Course',
                'courses' => $this->courses->getAllWithRelations(),
            ]))
        );
    }

    public function create()
    {
        $active = (new AcademicYearModel())->getActive();

        return auth_no_cache(
            $this->response->setBody(view('admin/courses/form', $this->formData(null, [
                'title'       => 'Tambah Course',
                'course'      => null,
                'action'      => base_url('admin/courses'),
                'defaultYear' => $active['id'] ?? '',
            ])))
        );
    }

    public function store()
    {
        $data = $this->collect();

        if ($error = $this->checkClassYear($data)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        if (! $this->courses->insert($data)) {
            return redirect()->back()->withInput()->with('error', array_values($this->courses->errors()));
        }

        return redirect()->to('admin/courses')->with('success', 'Course berhasil ditambahkan.');
    }

    public function edit($id = null)
    {
        $course = $this->findOr404($id);

        return auth_no_cache(
            $this->response->setBody(view('admin/courses/form', $this->formData($course, [
                'title'       => 'Edit Course',
                'course'      => $course,
                'action'      => base_url('admin/courses/' . (int) $id . '/update'),
                'defaultYear' => '',
            ])))
        );
    }

    public function update($id = null)
    {
        $this->findOr404($id);

        $data       = $this->collect();
        $data['id'] = (int) $id;

        if ($error = $this->checkClassYear($data)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        // Wajib di CI4 >= 4.5: placeholder {id} butuh rule untuk field 'id'.
        $this->courses->setValidationRule('id', 'permit_empty|is_natural_no_zero');

        if (! $this->courses->update((int) $id, $data)) {
            return redirect()->back()->withInput()->with('error', array_values($this->courses->errors()));
        }

        return redirect()->to('admin/courses')->with('success', 'Course berhasil diperbarui.');
    }

    public function delete($id = null)
    {
        $course = $this->findOr404($id);
        $back   = 'admin/courses';
        $db     = \Config\Database::connect();

        // Kumpulkan nama file SEBELUM course dihapus
        // (record materials, assignments, dan submissions ikut terhapus oleh CASCADE).
        $materialFiles = array_column(
            $db->table('materials')
                ->select('file_path')
                ->where('course_id', (int) $id)
                ->where('file_path IS NOT NULL', null, false)
                ->get()
                ->getResultArray(),
            'file_path'
        );

        $attachmentFiles = array_column(
            $db->table('assignments')
                ->select('attachment_path')
                ->where('course_id', (int) $id)
                ->where('attachment_path IS NOT NULL', null, false)
                ->get()
                ->getResultArray(),
            'attachment_path'
        );

        $answerFiles = array_column(
            $db->table('submissions sub')
                ->select('sub.file_path')
                ->join('assignments a', 'a.id = sub.assignment_id')
                ->where('a.course_id', (int) $id)
                ->where('sub.file_path IS NOT NULL', null, false)
                ->get()
                ->getResultArray(),
            'file_path'
        );

        try {
            $this->courses->delete((int) $id);
        } catch (DatabaseException $e) {
            return redirect()->to($back)
                ->with('error', 'Course "' . $course['title'] . '" tidak dapat dihapus karena masih dipakai data lain.');
        }

        // Pastikan course benar-benar sudah hilang sebelum menyentuh file di disk.
        if ($this->courses->find((int) $id) !== null) {
            return redirect()->to($back)
                ->with('error', 'Course "' . $course['title'] . '" gagal dihapus.');
        }

        foreach ($materialFiles as $name) {
            material_delete_file($name);
        }

        foreach ($attachmentFiles as $name) {
            assignment_delete_file($name);
        }

        foreach ($answerFiles as $name) {
            submission_delete_file($name);
        }

        return redirect()->to($back)
            ->with('success', 'Course berhasil dihapus beserta data siswa, materi, dan tugasnya.');
    }

    // ---------------------------------------------------------------
    // Kelola siswa course
    // ---------------------------------------------------------------

    public function students($id = null)
    {
        $course = $this->findOr404($id);
        $db     = \Config\Database::connect();

        $enrolled = $db->table('course_students cs')
            ->select('cs.student_id, cs.enrolled_at, s.nis, s.nisn, u.name, c.name AS class_name')
            ->join('students s', 's.id = cs.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('classes c', 'c.id = s.class_id', 'left')
            ->where('cs.course_id', (int) $id)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();

        $enrolledIds = array_map('intval', array_column($enrolled, 'student_id'));

        $builder = $db->table('students s')
            ->select('s.id, s.nis, u.name, c.name AS class_name')
            ->join('users u', 'u.id = s.user_id')
            ->join('classes c', 'c.id = s.class_id', 'left')
            ->where('u.is_active', 1);

        if ($enrolledIds !== []) {
            $builder->whereNotIn('s.id', $enrolledIds);
        }

        $candidates = $builder->orderBy('u.name', 'ASC')->get()->getResultArray();

        return auth_no_cache(
            $this->response->setBody(view('admin/courses/students', [
                'title'      => 'Kelola Siswa Course',
                'course'     => $course,
                'enrolled'   => $enrolled,
                'candidates' => $candidates,
                'classes'    => (new ClassModel())->getAllWithRelations(),
            ]))
        );
    }

    public function addStudent($id = null)
    {
        $this->findOr404($id);

        $studentId = (int) $this->request->getPost('student_id');
        $back      = 'admin/courses/' . (int) $id . '/students';

        if ($studentId < 1 || ! (new StudentModel())->find($studentId)) {
            return redirect()->to($back)->with('error', 'Pilih siswa yang valid.');
        }

        $pivot = new CourseStudentModel();

        if ($pivot->isEnrolled((int) $id, $studentId)) {
            return redirect()->to($back)->with('warning', 'Siswa tersebut sudah terdaftar di course ini.');
        }

        if (! $pivot->enroll((int) $id, $studentId)) {
            return redirect()->to($back)->with('error', 'Gagal menambahkan siswa ke course.');
        }

        return redirect()->to($back)->with('success', 'Siswa berhasil ditambahkan ke course.');
    }

    public function addClass($id = null)
    {
        $this->findOr404($id);

        $classId = (int) $this->request->getPost('class_id');
        $back    = 'admin/courses/' . (int) $id . '/students';

        if ($classId < 1 || ! (new ClassModel())->find($classId)) {
            return redirect()->to($back)->with('error', 'Pilih kelas yang valid.');
        }

        $members = (new StudentModel())->select('id')->where('class_id', $classId)->findAll();

        if ($members === []) {
            return redirect()->to($back)->with('warning', 'Kelas tersebut belum memiliki siswa.');
        }

        $pivot = new CourseStudentModel();
        $added = 0;
        $skip  = 0;

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            foreach ($members as $m) {
                if ($pivot->isEnrolled((int) $id, (int) $m['id'])) {
                    $skip++;
                    continue;
                }

                if (! $pivot->enroll((int) $id, (int) $m['id'])) {
                    $db->transRollback();

                    return redirect()->to($back)->with('error', 'Gagal menambahkan siswa kelas ke course.');
                }

                $added++;
            }

            $db->transCommit();
        } catch (DatabaseException $e) {
            $db->transRollback();

            return redirect()->to($back)->with('error', 'Gagal menambahkan siswa kelas ke course.');
        }

        return redirect()->to($back)
            ->with('success', $added . ' siswa ditambahkan' . ($skip > 0 ? ', ' . $skip . ' siswa sudah terdaftar sebelumnya.' : '.'));
    }

    public function removeStudent($courseId = null, $studentId = null)
    {
        $this->findOr404($courseId);

        $back  = 'admin/courses/' . (int) $courseId . '/students';
        $pivot = new CourseStudentModel();

        if (! $pivot->isEnrolled((int) $courseId, (int) $studentId)) {
            return redirect()->to($back)->with('warning', 'Siswa tersebut tidak terdaftar di course ini.');
        }

        $pivot->unenroll((int) $courseId, (int) $studentId);

        return redirect()->to($back)->with('success', 'Siswa berhasil dikeluarkan dari course.');
    }

    // ---------------------------------------------------------------

    private function collect(): array
    {
        return [
            'subject_id'       => trim((string) $this->request->getPost('subject_id')),
            'teacher_id'       => trim((string) $this->request->getPost('teacher_id')),
            'class_id'         => trim((string) $this->request->getPost('class_id')),
            'academic_year_id' => trim((string) $this->request->getPost('academic_year_id')),
            'title'            => trim((string) $this->request->getPost('title')),
            'description'      => trim((string) $this->request->getPost('description')),
            'status'           => trim((string) $this->request->getPost('status')),
        ];
    }

    private function checkClassYear(array $data): ?string
    {
        if (! ctype_digit($data['class_id']) || ! ctype_digit($data['academic_year_id'])) {
            return null; // dilaporkan oleh validasi model
        }

        $class = (new ClassModel())->find((int) $data['class_id']);

        if (! $class) {
            return 'Kelas yang dipilih tidak ditemukan.';
        }

        if ((int) $class['academic_year_id'] !== (int) $data['academic_year_id']) {
            return 'Kelas yang dipilih bukan milik tahun akademik tersebut.';
        }

        return null;
    }

    private function formData(?array $course, array $extra): array
    {
        $subjects = (new SubjectModel())->getActive();

        // Saat edit: pertahankan mapel course ini walau sudah dinonaktifkan.
        if ($course !== null) {
            $ids = array_map('intval', array_column($subjects, 'id'));

            if (! in_array((int) $course['subject_id'], $ids, true)) {
                $current = (new SubjectModel())->find((int) $course['subject_id']);

                if ($current) {
                    $subjects[] = $current;
                }
            }
        }

        return $extra + [
            'subjects' => $subjects,
            'teachers' => (new TeacherModel())->getAllWithUser(),
            'classes'  => (new ClassModel())->getAllWithRelations(),
            'years'    => (new AcademicYearModel())->orderBy('start_date', 'DESC')->findAll(),
        ];
    }

    private function findOr404($id): array
    {
        $course = $this->courses->findWithRelations((int) $id);

        if (! $course) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        return $course;
    }
}
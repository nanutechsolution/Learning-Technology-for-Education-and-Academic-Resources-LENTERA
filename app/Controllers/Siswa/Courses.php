<?php

namespace App\Controllers\Siswa;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\CourseStudentModel;
use App\Models\MaterialModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Courses extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    /**
     * "Kelas Saya": daftar course yang diikuti + progres materi per course.
     */
    public function index()
    {
        $student = (new StudentModel())->findByUserId(auth_id());
        $courses = [];

        if ($student) {
            $studentId     = (int) ((array) $student)['id'];
            $materialModel = new MaterialModel();

            foreach ((new CourseModel())->getByStudent($studentId) as $row) {
                $row       = (array) $row;
                $materials = $materialModel->getPublishedByCourse((int) $row['id'], $studentId);
                $viewed    = 0;

                foreach ($materials as $m) {
                    if (! empty($m['viewed_at'])) {
                        $viewed++;
                    }
                }

                $row['material_total']  = count($materials);
                $row['material_viewed'] = $viewed;

                $courses[] = $row;
            }
        }

        return auth_no_cache(
            $this->response->setBody(view('siswa/courses/index', [
                'title'      => 'Kelas Saya',
                'hasProfile' => $student !== null,
                'courses'    => $courses,
            ]))
        );
    }

    /**
     * Detail course: daftar materi published + status sudah/belum dibuka.
     */
    public function show($courseId)
    {
        $student = (new StudentModel())->findByUserId(auth_id());
        if (! $student) {
            return redirect()->to('siswa/dashboard')
                ->with('warning', 'Akun Anda belum terhubung dengan profil siswa. Hubungi admin.');
        }

        $studentId = (int) ((array) $student)['id'];
        $courseId  = (int) $courseId;

        if (! (new CourseStudentModel())->isEnrolled($courseId, $studentId)) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $course = (new CourseModel())->findWithRelations($courseId);
        if (! $course) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $course = (array) $course;

        return auth_no_cache(
            $this->response->setBody(view('siswa/courses/show', [
                'title'     => $course['title'],
                'course'    => $course,
                'materials' => (new MaterialModel())->getPublishedByCourse($courseId, $studentId),
            ]))
        );
    }
}

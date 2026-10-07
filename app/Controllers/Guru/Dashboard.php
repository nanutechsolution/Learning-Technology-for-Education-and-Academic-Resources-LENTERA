<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\MaterialModel;
use App\Models\SubjectModel;
use App\Models\TeacherModel;

class Dashboard extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    public function index()
    {
        $teacher            = (new TeacherModel())->findByUserId(auth_id());
        $teacherId          = null;
        $courseCount        = 0;
        $classCount         = 0;
        $courses            = [];
        $subjectNames       = [];
        $materialStats      = ['total' => 0, 'published' => 0, 'draft' => 0];
        $assignmentStats    = ['total' => 0, 'published' => 0, 'draft' => 0, 'pending' => 0];
        $assignmentByCourse = [];
        $quizStats          = ['total' => 0, 'published' => 0, 'draft' => 0, 'submitted' => 0];
        $quizByCourse       = [];

        if ($teacher) {
            $teacherId = (int) (is_object($teacher) ? $teacher->id : $teacher['id']);

            $courseModel = new CourseModel();
            $courseCount = (int) $courseModel->countByTeacher($teacherId);
            $classCount  = (int) $courseModel->countClassesByTeacher($teacherId);

            $courses = array_map(
                static fn ($row) => (array) $row,
                $courseModel->getByTeacher($teacherId)
            );

            foreach ((new SubjectModel())->findAll() as $s) {
                $s = (array) $s;
                $subjectNames[$s['id']] = $s['name'];
            }

            $counts        = (new MaterialModel())->countByTeacher($teacherId);
            $materialStats = [
                'total'     => (int) $counts['total'],
                'published' => (int) $counts['published'],
                'draft'     => (int) $counts['draft'],
            ];

            [$assignmentStats, $assignmentByCourse] = $this->assignmentSummary($teacherId);
            [$quizStats, $quizByCourse]             = $this->quizSummary($teacherId);
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/dashboard', [
                'title'              => 'Dashboard Guru',
                'hasProfile'         => $teacher !== null,
                'courseCount'        => $courseCount,
                'classCount'         => $classCount,
                'courses'            => $courses,
                'subjectNames'       => $subjectNames,
                'materialStats'      => $materialStats,
                'assignmentStats'    => $assignmentStats,
                'assignmentByCourse' => $assignmentByCourse,
                'quizStats'          => $quizStats,
                'quizByCourse'       => $quizByCourse,
            ]))
        );
    }

    /**
     * Ringkasan tugas untuk seluruh course milik guru.
     * "Perlu dinilai" = pengumpulan yang sudah dikirim, belum dinilai, pada tugas
     * Published dan siswa yang masih terdaftar di course (sama dengan daftar siswa di detail tugas).
     *
     * @return array{0: array, 1: array} [total keseluruhan, rincian per course]
     */
    private function assignmentSummary(int $teacherId): array
    {
        $db        = \Config\Database::connect();
        $perCourse = [];

        $rows = $db->table('assignments a')
            ->select('a.course_id, COUNT(*) AS total, SUM(CASE WHEN a.is_published = 1 THEN 1 ELSE 0 END) AS published')
            ->join('courses c', 'c.id = a.course_id')
            ->where('c.teacher_id', $teacherId)
            ->groupBy('a.course_id')
            ->get()
            ->getResultArray();

        foreach ($rows as $r) {
            $perCourse[(int) $r['course_id']] = [
                'total'     => (int) $r['total'],
                'published' => (int) $r['published'],
                'pending'   => 0,
            ];
        }

        $pendingRows = $db->table('submissions sub')
            ->select('a.course_id, COUNT(*) AS pending')
            ->join('assignments a', 'a.id = sub.assignment_id')
            ->join('courses c', 'c.id = a.course_id')
            ->join('course_students cs', 'cs.course_id = a.course_id AND cs.student_id = sub.student_id')
            ->where('c.teacher_id', $teacherId)
            ->where('a.is_published', 1)
            ->where('sub.submitted_at IS NOT NULL', null, false)
            ->where('sub.graded_at IS NULL', null, false)
            ->groupBy('a.course_id')
            ->get()
            ->getResultArray();

        foreach ($pendingRows as $r) {
            $cid = (int) $r['course_id'];

            if (isset($perCourse[$cid])) {
                $perCourse[$cid]['pending'] = (int) $r['pending'];
            }
        }

        $total     = array_sum(array_column($perCourse, 'total'));
        $published = array_sum(array_column($perCourse, 'published'));

        return [[
            'total'     => $total,
            'published' => $published,
            'draft'     => $total - $published,
            'pending'   => array_sum(array_column($perCourse, 'pending')),
        ], $perCourse];
    }

    /**
     * Ringkasan quiz untuk seluruh course milik guru.
     * "Selesai" = attempt berstatus submitted milik siswa yang masih terdaftar di course
     * (sama dengan hitungan di daftar quiz guru).
     *
     * @return array{0: array, 1: array} [total keseluruhan, rincian per course]
     */
    private function quizSummary(int $teacherId): array
    {
        $db        = \Config\Database::connect();
        $perCourse = [];

        $rows = $db->table('quizzes q')
            ->select('q.course_id, COUNT(*) AS total, SUM(CASE WHEN q.is_published = 1 THEN 1 ELSE 0 END) AS published')
            ->join('courses c', 'c.id = q.course_id')
            ->where('c.teacher_id', $teacherId)
            ->groupBy('q.course_id')
            ->get()
            ->getResultArray();

        foreach ($rows as $r) {
            $perCourse[(int) $r['course_id']] = [
                'total'     => (int) $r['total'],
                'published' => (int) $r['published'],
                'submitted' => 0,
            ];
        }

        $doneRows = $db->table('quiz_attempts qa')
            ->select('q.course_id, COUNT(*) AS submitted')
            ->join('quizzes q', 'q.id = qa.quiz_id')
            ->join('courses c', 'c.id = q.course_id')
            ->join('course_students cs', 'cs.course_id = q.course_id AND cs.student_id = qa.student_id')
            ->where('c.teacher_id', $teacherId)
            ->where('qa.status', 'submitted')
            ->groupBy('q.course_id')
            ->get()
            ->getResultArray();

        foreach ($doneRows as $r) {
            $cid = (int) $r['course_id'];

            if (isset($perCourse[$cid])) {
                $perCourse[$cid]['submitted'] = (int) $r['submitted'];
            }
        }

        $total     = array_sum(array_column($perCourse, 'total'));
        $published = array_sum(array_column($perCourse, 'published'));

        return [[
            'total'     => $total,
            'published' => $published,
            'draft'     => $total - $published,
            'submitted' => array_sum(array_column($perCourse, 'submitted')),
        ], $perCourse];
    }
}
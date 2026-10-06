<?php

namespace App\Controllers\Siswa;

use App\Controllers\BaseController;
use App\Models\AssignmentModel;
use App\Models\ClassModel;
use App\Models\CourseModel;
use App\Models\MaterialViewModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;

class Dashboard extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    public function index()
    {
        $student         = (new StudentModel())->findByUserId(auth_id());
        $className       = null;
        $courses         = [];
        $subjectNames    = [];
        $progress        = ['total' => 0, 'viewed' => 0, 'percent' => 0];
        $assignmentStats = ['total' => 0, 'open' => 0, 'submitted' => 0, 'graded' => 0, 'closed' => 0, 'average' => null];
        $upcoming        = [];

        if ($student) {
            $student   = (array) $student;
            $studentId = (int) $student['id'];

            if (! empty($student['class_id'])) {
                $class     = (new ClassModel())->find($student['class_id']);
                $className = $class ? ((array) $class)['name'] : null;
            }

            $courses = array_map(
                static fn ($row) => (array) $row,
                (new CourseModel())->getByStudent($studentId)
            );

            foreach ((new SubjectModel())->findAll() as $s) {
                $s = (array) $s;
                $subjectNames[$s['id']] = $s['name'];
            }

            $p        = (new MaterialViewModel())->getProgressByStudent($studentId);
            $progress = [
                'total'   => (int) $p['total'],
                'viewed'  => (int) $p['viewed'],
                'percent' => (float) $p['percent'],
            ];

            [$assignmentStats, $upcoming] = $this->assignmentOverview($studentId);
        }

        return auth_no_cache(
            $this->response->setBody(view('siswa/dashboard', [
                'title'           => 'Dashboard Siswa',
                'hasProfile'      => $student !== null,
                'className'       => $className,
                'courses'         => $courses,
                'subjectNames'    => $subjectNames,
                'progress'        => $progress,
                'assignmentStats' => $assignmentStats,
                'upcoming'        => $upcoming,
            ]))
        );
    }

    /**
     * Ringkasan tugas published pada course yang diikuti siswa.
     * Status memakai aturan yang sama dengan daftar tugas siswa:
     * graded > submitted > closed (lewat batas, belum kumpul) > open.
     *
     * @return array{0: array, 1: list<array>} [statistik, maksimal 5 tugas terbuka terdekat]
     */
    private function assignmentOverview(int $studentId): array
    {
        $rows = \Config\Database::connect()
            ->table('assignments a')
            ->select('a.id, a.title, a.due_at, a.course_id, c.title AS course_title, sub.submitted_at, sub.graded_at, sub.score')
            ->join('courses c', 'c.id = a.course_id')
            ->join('course_students cs', 'cs.course_id = a.course_id')
            ->join('submissions sub', 'sub.assignment_id = a.id AND sub.student_id = ' . $studentId, 'left')
            ->where('cs.student_id', $studentId)
            ->where('a.is_published', 1)
            ->get()
            ->getResultArray();

        $stats    = ['total' => 0, 'open' => 0, 'submitted' => 0, 'graded' => 0, 'closed' => 0, 'average' => null];
        $upcoming = [];
        $sum      = 0.0;
        $scored   = 0;

        foreach ($rows as $row) {
            $stats['total']++;

            if (! empty($row['graded_at'])) {
                $stats['graded']++;

                if ($row['score'] !== null) {
                    $sum += (float) $row['score'];
                    $scored++;
                }
            } elseif (! empty($row['submitted_at'])) {
                $stats['submitted']++;
            } elseif (AssignmentModel::isOverdue($row)) {
                $stats['closed']++;
            } else {
                $stats['open']++;
                $upcoming[] = $row;
            }
        }

        $stats['average'] = $scored > 0 ? $sum / $scored : null;

        // Batas waktu terdekat dulu; tanpa batas waktu di urutan terakhir.
        usort($upcoming, static function (array $x, array $y): int {
            $a = empty($x['due_at']) ? PHP_INT_MAX : (int) strtotime((string) $x['due_at']);
            $b = empty($y['due_at']) ? PHP_INT_MAX : (int) strtotime((string) $y['due_at']);

            return $a <=> $b;
        });

        return [$stats, array_slice($upcoming, 0, 5)];
    }
}
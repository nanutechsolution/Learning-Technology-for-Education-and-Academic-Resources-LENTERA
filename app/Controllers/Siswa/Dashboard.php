<?php

namespace App\Controllers\Siswa;

use App\Controllers\BaseController;
use App\Models\AnnouncementModel;
use App\Models\AssignmentModel;
use App\Models\ClassModel;
use App\Models\CourseModel;
use App\Models\MaterialViewModel;
use App\Models\QuizAttemptModel;
use App\Models\QuizModel;
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
        $quizStats       = ['total' => 0, 'available' => 0, 'submitted' => 0, 'upcoming' => 0, 'closed' => 0, 'average' => null];
        $quizUpcoming    = [];
        $announcements   = [];

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
            [$quizStats, $quizUpcoming]   = $this->quizOverview($studentId);
        }

        // Siswa tanpa profil tetap melihat pengumuman sekolah.
        $announcements = (new AnnouncementModel())->latestForStudent($student ? (int) $student['id'] : null, 5);

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
                'quizStats'       => $quizStats,
                'quizUpcoming'    => $quizUpcoming,
                'announcements'   => $announcements,
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

    /**
     * Ringkasan quiz published pada course yang diikuti siswa.
     * Status memakai aturan yang sama dengan daftar quiz siswa:
     * submitted > in_progress > upcoming | open | closed.
     * Attempt in_progress yang sudah lewat batas waktu diselesaikan otomatis lebih dulu.
     * "available" = dapat dikerjakan (belum mulai tetapi dibuka) ditambah sedang dikerjakan.
     *
     * @return array{0: array, 1: list<array>} [statistik, maksimal 5 quiz yang perlu dikerjakan]
     */
    private function quizOverview(int $studentId): array
    {
        $rows = \Config\Database::connect()
            ->table('quizzes q')
            ->select(
                'q.id, q.title, q.course_id, q.duration_minutes, q.start_at, q.end_at, q.is_published, '
                . 'c.title AS course_title, '
                . 'qa.id AS attempt_id, qa.status AS attempt_status, qa.score AS attempt_score, qa.started_at AS attempt_started_at'
            )
            ->join('courses c', 'c.id = q.course_id')
            ->join('course_students cs', 'cs.course_id = q.course_id')
            ->join('quiz_attempts qa', 'qa.quiz_id = q.id AND qa.student_id = ' . $studentId, 'left')
            ->where('cs.student_id', $studentId)
            ->where('q.is_published', 1)
            ->get()
            ->getResultArray();

        $attempts = new QuizAttemptModel();
        $stats    = ['total' => 0, 'available' => 0, 'submitted' => 0, 'upcoming' => 0, 'closed' => 0, 'average' => null];
        $todo     = [];
        $sum      = 0.0;
        $scored   = 0;

        foreach ($rows as $row) {
            $stats['total']++;

            $status = $row['attempt_status'];
            $score  = $row['attempt_score'];

            if ($status === QuizAttemptModel::STATUS_IN_PROGRESS
                && QuizAttemptModel::isExpired($row, ['started_at' => $row['attempt_started_at']])) {
                $attempts->finalizeIfExpired((int) $row['attempt_id']);

                $fresh  = $attempts->find((int) $row['attempt_id']);
                $status = $fresh['status'] ?? $status;
                $score  = $fresh['score'] ?? $score;
            }

            if ($status === QuizAttemptModel::STATUS_SUBMITTED) {
                $stats['submitted']++;

                if ($score !== null) {
                    $sum += (float) $score;
                    $scored++;
                }

                continue;
            }

            if ($status === QuizAttemptModel::STATUS_IN_PROGRESS) {
                $stats['available']++;
                $row['state'] = 'in_progress';
                $todo[]       = $row;

                continue;
            }

            $access = QuizModel::accessState($row);

            if ($access === QuizModel::STATE_OPEN) {
                $stats['available']++;
                $row['state'] = 'open';
                $todo[]       = $row;
            } elseif ($access === QuizModel::STATE_UPCOMING) {
                $stats['upcoming']++;
            } else {
                $stats['closed']++;
            }
        }

        $stats['average'] = $scored > 0 ? $sum / $scored : null;

        // Batas akhir terdekat dulu; tanpa batas akhir di urutan terakhir.
        usort($todo, static function (array $x, array $y): int {
            $a = empty($x['end_at']) ? PHP_INT_MAX : (int) strtotime((string) $x['end_at']);
            $b = empty($y['end_at']) ? PHP_INT_MAX : (int) strtotime((string) $y['end_at']);

            return $a <=> $b;
        });

        return [$stats, array_slice($todo, 0, 5)];
    }
}
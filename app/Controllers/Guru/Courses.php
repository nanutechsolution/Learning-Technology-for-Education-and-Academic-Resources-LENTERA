<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\AssignmentModel;
use App\Models\CourseModel;
use App\Models\MaterialModel;
use App\Models\QuizModel;
use App\Models\TeacherModel;

class Courses extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url']);
    }

    public function index()
    {
        $teacher = (new TeacherModel())->findByUserId(auth_id());
        $courses = [];

        if ($teacher) {
            $teacherId       = (int) ((array) $teacher)['id'];
            $materialModel   = new MaterialModel();
            $assignmentModel = new AssignmentModel();
            $quizModel       = new QuizModel();

            foreach ((new CourseModel())->getByTeacher($teacherId) as $row) {
                $row = (array) $row;
                $cid = (int) $row['id'];

                $materials   = $materialModel->countByCourse($cid);
                $assignments = $assignmentModel->countByCourse($cid);
                $quizzes     = $quizModel->countByCourse($cid);

                $row['material_total']     = (int) $materials['total'];
                $row['material_published'] = (int) $materials['published'];
                $row['material_draft']     = (int) $materials['draft'];

                $row['assignment_total']     = (int) $assignments['total'];
                $row['assignment_published'] = (int) $assignments['published'];
                $row['assignment_draft']     = (int) $assignments['draft'];

                $row['quiz_total']     = (int) $quizzes['total'];
                $row['quiz_published'] = (int) $quizzes['published'];
                $row['quiz_draft']     = (int) $quizzes['draft'];

                $courses[] = $row;
            }
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/courses/index', [
                'title'      => 'Course Saya',
                'hasProfile' => $teacher !== null,
                'courses'    => $courses,
            ]))
        );
    }
}
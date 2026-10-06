<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ClassModel;
use App\Models\CourseModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;
use App\Models\TeacherModel;

class Dashboard extends BaseController
{
    public function index()
    {
        helper('auth');

        $stats = [
            'teachers' => (new TeacherModel())->countAllResults(),
            'students' => (new StudentModel())->countAllResults(),
            'classes'  => (new ClassModel())->countAllResults(),
            'subjects' => (new SubjectModel())->countAllResults(),
            'courses'  => (new CourseModel())->countAllResults(),
        ];

        return auth_no_cache(
            $this->response->setBody(view('admin/dashboard', [
                'title' => 'Dashboard Admin',
                'stats' => $stats,
            ]))
        );
    }
}

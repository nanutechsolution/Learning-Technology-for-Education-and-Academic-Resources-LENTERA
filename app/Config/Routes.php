<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

/*
 * ------------------------------------------------------------------
 * PUBLIK
 * ------------------------------------------------------------------
 */
$routes->get('/', 'Auth::index');
$routes->get('login', 'Auth::login');
$routes->post('login', 'Auth::attempt');
$routes->post('logout', 'Auth::logout');

// Logo sekolah (publik, hanya GET, tanpa parameter): dipakai halaman login dan layout
$routes->get('school-logo', 'Branding::logo');

/*
 * ------------------------------------------------------------------
 * ADMIN  (filter: admin)
 * ------------------------------------------------------------------
 */
$routes->group('admin', ['namespace' => 'App\Controllers\Admin', 'filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index');

    // CRUD master data: slug URL => nama controller
    $modules = [
        'academic-years' => 'AcademicYears',
        'teachers'       => 'Teachers',
        'students'       => 'Students',
        'classes'        => 'Classes',
        'subjects'       => 'Subjects',
        'courses'        => 'Courses',
    ];

    foreach ($modules as $slug => $controller) {
        $routes->get($slug, $controller . '::index');
        $routes->get($slug . '/create', $controller . '::create');
        $routes->post($slug, $controller . '::store');
        $routes->get($slug . '/(:num)/edit', $controller . '::edit/$1');
        $routes->post($slug . '/(:num)/update', $controller . '::update/$1');
        $routes->post($slug . '/(:num)/delete', $controller . '::delete/$1');
    }

    // Tahun akademik: aktifkan satu tahun
    $routes->post('academic-years/(:num)/activate', 'AcademicYears::activate/$1');

    // Course: kelola siswa yang mengikuti course
    $routes->get('courses/(:num)/students', 'Courses::students/$1');
    $routes->post('courses/(:num)/students/add', 'Courses::addStudent/$1');
    $routes->post('courses/(:num)/students/add-class', 'Courses::addClass/$1');
    $routes->post('courses/(:num)/students/(:num)/remove', 'Courses::removeStudent/$1/$2');

    // Pengaturan sekolah (satu baris tunggal): form, simpan, pratinjau logo
    $routes->get('announcements', 'Announcements::index');
    $routes->get('announcements/create', 'Announcements::create');
    $routes->post('announcements', 'Announcements::store');
    $routes->get('announcements/(:num)', 'Announcements::show/$1');
    $routes->get('announcements/(:num)/edit', 'Announcements::edit/$1');
    $routes->post('announcements/(:num)/update', 'Announcements::update/$1');
    $routes->post('announcements/(:num)/delete', 'Announcements::delete/$1');
    $routes->post('announcements/(:num)/toggle-publish', 'Announcements::togglePublish/$1');

    $routes->get('school-settings', 'SchoolSettings::edit');
    $routes->post('school-settings/update', 'SchoolSettings::update');
    $routes->get('school-settings/logo', 'SchoolSettings::logo');
});

/*
 * ------------------------------------------------------------------
 * GURU  (filter: guru)
 * ------------------------------------------------------------------
 */
$routes->group('guru', ['namespace' => 'App\Controllers\Guru', 'filter' => 'guru'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index');

    // Phase 2: daftar course milik guru
    $routes->get('courses', 'Courses::index');

    // Phase 2: materi per course
    $routes->get('courses/(:num)/materials', 'Materials::index/$1');
    $routes->get('courses/(:num)/materials/create', 'Materials::create/$1');
    $routes->post('courses/(:num)/materials', 'Materials::store/$1');

    // Phase 2: aksi pada satu materi
    $routes->get('materials/(:num)', 'Materials::show/$1');
    $routes->get('materials/(:num)/edit', 'Materials::edit/$1');
    $routes->post('materials/(:num)/update', 'Materials::update/$1');
    $routes->post('materials/(:num)/delete', 'Materials::delete/$1');
    $routes->post('materials/(:num)/toggle-publish', 'Materials::togglePublish/$1');
    $routes->get('materials/(:num)/download', 'Materials::download/$1');

    // Phase 3: tugas per course
    $routes->get('courses/(:num)/assignments', 'Assignments::index/$1');
    $routes->get('courses/(:num)/assignments/create', 'Assignments::create/$1');
    $routes->post('courses/(:num)/assignments', 'Assignments::store/$1');

    // Phase 3: aksi pada satu tugas
    $routes->get('assignments/(:num)', 'Assignments::show/$1');
    $routes->get('assignments/(:num)/edit', 'Assignments::edit/$1');
    $routes->post('assignments/(:num)/update', 'Assignments::update/$1');
    $routes->post('assignments/(:num)/delete', 'Assignments::delete/$1');
    $routes->post('assignments/(:num)/toggle-publish', 'Assignments::togglePublish/$1');
    $routes->get('assignments/(:num)/download', 'Assignments::download/$1');

    // Phase 3: pengumpulan siswa, penilaian, dan file jawaban
    $routes->get('submissions/(:num)', 'Submissions::show/$1');
    $routes->post('submissions/(:num)/grade', 'Submissions::grade/$1');
    $routes->get('submissions/(:num)/download', 'Submissions::download/$1');

    // Phase 4: quiz per course
    $routes->get('courses/(:num)/quizzes', 'Quizzes::index/$1');
    $routes->get('courses/(:num)/quizzes/create', 'Quizzes::create/$1');
    $routes->post('courses/(:num)/quizzes', 'Quizzes::store/$1');

    // Phase 4: aksi pada satu quiz
    $routes->get('quizzes/(:num)', 'Quizzes::show/$1');
    $routes->get('quizzes/(:num)/edit', 'Quizzes::edit/$1');
    $routes->post('quizzes/(:num)/update', 'Quizzes::update/$1');
    $routes->post('quizzes/(:num)/delete', 'Quizzes::delete/$1');
    $routes->post('quizzes/(:num)/toggle-publish', 'Quizzes::togglePublish/$1');

    // Phase 4: pertanyaan per quiz
    $routes->get('quizzes/(:num)/questions/create', 'Questions::create/$1');
    $routes->post('quizzes/(:num)/questions', 'Questions::store/$1');

    // Phase 4: aksi pada satu pertanyaan
    $routes->get('questions/(:num)/edit', 'Questions::edit/$1');
    $routes->post('questions/(:num)/update', 'Questions::update/$1');
    $routes->post('questions/(:num)/delete', 'Questions::delete/$1');

    // Phase 4: hasil pengerjaan quiz dan detail jawaban siswa
    $routes->get('quizzes/(:num)/results', 'Attempts::index/$1');
    $routes->get('attempts/(:num)', 'Attempts::show/$1');

    $routes->get('announcements', 'Announcements::index');
    $routes->get('announcements/create', 'Announcements::create');
    $routes->post('announcements', 'Announcements::store');
    $routes->get('courses/(:num)/announcements', 'Announcements::byCourse/$1');
    $routes->get('courses/(:num)/announcements/create', 'Announcements::create/$1');
    $routes->get('announcements/(:num)', 'Announcements::show/$1');
    $routes->get('announcements/(:num)/edit', 'Announcements::edit/$1');
    $routes->post('announcements/(:num)/update', 'Announcements::update/$1');
    $routes->post('announcements/(:num)/delete', 'Announcements::delete/$1');
    $routes->post('announcements/(:num)/toggle-publish', 'Announcements::togglePublish/$1');
});

/*
 * ------------------------------------------------------------------
 * SISWA  (filter: siswa)
 * ------------------------------------------------------------------
 */
$routes->group('siswa', ['namespace' => 'App\Controllers\Siswa', 'filter' => 'siswa'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index');

    // Phase 2: kelas saya + materi
    $routes->get('courses', 'Courses::index');
    $routes->get('courses/(:num)', 'Courses::show/$1');
    $routes->get('materials/(:num)', 'Materials::show/$1');
    $routes->get('materials/(:num)/download', 'Materials::download/$1');

    // Phase 3: tugas
    $routes->get('courses/(:num)/assignments', 'Assignments::index/$1');
    $routes->get('assignments/(:num)', 'Assignments::show/$1');
    $routes->post('assignments/(:num)/submit', 'Assignments::submit/$1');
    $routes->get('assignments/(:num)/download', 'Assignments::download/$1');
    $routes->get('assignments/(:num)/submission/download', 'Assignments::downloadSubmission/$1');

    // Phase 4: quiz
    $routes->get('courses/(:num)/quizzes', 'Quizzes::index/$1');
    $routes->get('quizzes/(:num)', 'Quizzes::show/$1');
    $routes->post('quizzes/(:num)/start', 'Quizzes::start/$1');
    $routes->get('quizzes/(:num)/take', 'Quizzes::take/$1');
    $routes->post('quizzes/(:num)/save', 'Quizzes::save/$1');
    $routes->post('quizzes/(:num)/submit', 'Quizzes::submit/$1');

    $routes->get('announcements', 'Announcements::index');
    $routes->get('announcements/(:num)', 'Announcements::show/$1');
});

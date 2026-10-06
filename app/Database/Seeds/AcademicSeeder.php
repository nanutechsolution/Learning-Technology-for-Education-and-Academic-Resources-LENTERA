<?php

namespace App\Database\Seeds;

use App\Models\AcademicYearModel;
use App\Models\ClassModel;
use App\Models\CourseModel;
use App\Models\CourseStudentModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;
use App\Models\TeacherModel;
use App\Models\UserModel;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\Model;

class AcademicSeeder extends Seeder
{
    public function run()
    {
        $userModel        = new UserModel();
        $yearModel        = new AcademicYearModel();
        $subjectModel     = new SubjectModel();
        $teacherModel     = new TeacherModel();
        $classModel       = new ClassModel();
        $studentModel     = new StudentModel();
        $courseModel      = new CourseModel();
        $courseStudentMdl = new CourseStudentModel();

        $guruUser  = $userModel->findByUsername('guru.demo');
        $siswaUser = $userModel->findByUsername('siswa.demo');

        if ($guruUser === null || $siswaUser === null) {
            throw new \RuntimeException(
                'User demo belum ada. Jalankan: php spark db:seed DatabaseSeeder'
            );
        }

        // 1. Tahun akademik
        $year = $yearModel->where('name', '2026/2027')->first();

        if ($year === null) {
            $yearId = $this->insertOrFail($yearModel, [
                'name'       => '2026/2027',
                'start_date' => '2026-07-13',
                'end_date'   => '2027-06-26',
                'is_active'  => 0,
            ]);
            $yearModel->activate($yearId);
            CLI::write('[OK]   Tahun akademik dibuat: 2026/2027 (aktif)', 'green');
        } else {
            $yearId = (int) $year['id'];
            CLI::write('[SKIP] Tahun akademik sudah ada: 2026/2027', 'yellow');
        }

        // 2. Mata pelajaran
        $subjects = [
            ['code' => 'MTK',  'name' => 'Matematika',                          'description' => 'Mata pelajaran Matematika.'],
            ['code' => 'BIN',  'name' => 'Bahasa Indonesia',                    'description' => 'Mata pelajaran Bahasa Indonesia.'],
            ['code' => 'BIG',  'name' => 'Bahasa Inggris',                      'description' => 'Mata pelajaran Bahasa Inggris.'],
            ['code' => 'IPA',  'name' => 'Ilmu Pengetahuan Alam',               'description' => 'Mata pelajaran IPA.'],
            ['code' => 'IPS',  'name' => 'Ilmu Pengetahuan Sosial',             'description' => 'Mata pelajaran IPS.'],
            ['code' => 'PPKN', 'name' => 'Pendidikan Pancasila dan Kewarganegaraan', 'description' => 'Mata pelajaran PPKn.'],
            ['code' => 'INF',  'name' => 'Informatika',                         'description' => 'Mata pelajaran Informatika.'],
        ];

        $subjectIds = [];

        foreach ($subjects as $subject) {
            $existing = $subjectModel->where('code', $subject['code'])->first();

            if ($existing !== null) {
                $subjectIds[$subject['code']] = (int) $existing['id'];
                CLI::write('[SKIP] Mapel sudah ada: ' . $subject['code'], 'yellow');
                continue;
            }

            $subject['is_active']             = 1;
            $subjectIds[$subject['code']]     = $this->insertOrFail($subjectModel, $subject);
            CLI::write('[OK]   Mapel dibuat: ' . $subject['code'], 'green');
        }

        // 3. Profil guru
        $teacher = $teacherModel->where('user_id', $guruUser['id'])->first();

        if ($teacher === null) {
            $teacherId = $this->insertOrFail($teacherModel, [
                'user_id' => $guruUser['id'],
                'nip'     => '198001012005011001',
                'phone'   => '081234567890',
                'address' => 'Wewewa Timur, Sumba Barat Daya, Nusa Tenggara Timur',
            ]);
            CLI::write('[OK]   Profil guru dibuat: guru.demo', 'green');
        } else {
            $teacherId = (int) $teacher['id'];
            CLI::write('[SKIP] Profil guru sudah ada: guru.demo', 'yellow');
        }

        // 4. Kelas
        $classes = [
            ['name' => 'VII A',  'grade' => 7],
            ['name' => 'VII B',  'grade' => 7],
            ['name' => 'VIII A', 'grade' => 8],
            ['name' => 'VIII B', 'grade' => 8],
            ['name' => 'IX A',   'grade' => 9],
        ];

        $classIds = [];

        foreach ($classes as $class) {
            $existing = $classModel
                ->where('academic_year_id', $yearId)
                ->where('name', $class['name'])
                ->first();

            if ($existing !== null) {
                $classIds[$class['name']] = (int) $existing['id'];
                CLI::write('[SKIP] Kelas sudah ada: ' . $class['name'], 'yellow');
                continue;
            }

            $classIds[$class['name']] = $this->insertOrFail($classModel, [
                'academic_year_id'    => $yearId,
                'name'                => $class['name'],
                'grade'               => $class['grade'],
                'homeroom_teacher_id' => $class['name'] === 'VII A' ? $teacherId : null,
            ]);
            CLI::write('[OK]   Kelas dibuat: ' . $class['name'], 'green');
        }

        // 5. Profil siswa
        $student = $studentModel->where('user_id', $siswaUser['id'])->first();

        if ($student === null) {
            $studentId = $this->insertOrFail($studentModel, [
                'user_id'  => $siswaUser['id'],
                'nis'      => '2026001',
                'nisn'     => '0012345678',
                'class_id' => $classIds['VII A'],
                'phone'    => '081298765432',
                'address'  => 'Wewewa Timur, Sumba Barat Daya, Nusa Tenggara Timur',
            ]);
            CLI::write('[OK]   Profil siswa dibuat: siswa.demo', 'green');
        } else {
            $studentId = (int) $student['id'];
            CLI::write('[SKIP] Profil siswa sudah ada: siswa.demo', 'yellow');
        }

        // 6. Course
        $courseTitle = 'Matematika VII A';

        $course = $courseModel
            ->where('subject_id', $subjectIds['MTK'])
            ->where('teacher_id', $teacherId)
            ->where('class_id', $classIds['VII A'])
            ->where('academic_year_id', $yearId)
            ->where('title', $courseTitle)
            ->first();

        if ($course === null) {
            $courseId = $this->insertOrFail($courseModel, [
                'subject_id'       => $subjectIds['MTK'],
                'teacher_id'       => $teacherId,
                'class_id'         => $classIds['VII A'],
                'academic_year_id' => $yearId,
                'title'            => $courseTitle,
                'description'      => 'Kelas pembelajaran digital Matematika untuk siswa kelas VII A tahun ajaran 2026/2027.',
                'status'           => 'active',
            ]);
            CLI::write('[OK]   Course dibuat: ' . $courseTitle, 'green');
        } else {
            $courseId = (int) $course['id'];
            CLI::write('[SKIP] Course sudah ada: ' . $courseTitle, 'yellow');
        }

        // 7. Relasi course_students
        if ($courseStudentMdl->isEnrolled($courseId, $studentId)) {
            CLI::write('[SKIP] Siswa sudah terdaftar di course.', 'yellow');
        } elseif ($courseStudentMdl->enroll($courseId, $studentId)) {
            CLI::write('[OK]   siswa.demo didaftarkan ke course: ' . $courseTitle, 'green');
        } else {
            throw new \RuntimeException(
                'Gagal mendaftarkan siswa ke course: ' . implode('; ', $courseStudentMdl->errors())
            );
        }
    }

    /**
     * Insert melalui Model (validasi aktif) dan hentikan seeder jika gagal.
     */
    private function insertOrFail(Model $model, array $data): int
    {
        $id = $model->insert($data);

        if ($id === false) {
            throw new \RuntimeException(
                get_class($model) . ': ' . implode('; ', $model->errors())
            );
        }

        return (int) $id;
    }
}

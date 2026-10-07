<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Helper function untuk melakukan Idempotent Upsert (Update or Insert)
     * berdasarkan criteria unik agar aman dijalankan berulang kali.
     */
    protected function upsert(string $table, array $matchCriteria, array $data)
    {
        $builder = $this->db->table($table);
        $row = $builder->where($matchCriteria)->get()->getRow();

        $now = date('Y-m-d H:i:s');

        $payload = array_merge($matchCriteria, $data);
        $payload['updated_at'] = $now;

        if ($row) {
            $builder->where('id', $row->id)->update($payload);
            return $row->id;
        }

        $payload['created_at'] = $now;
        $builder->insert($payload);
        return $this->db->insertID();
    }

    public function run()
    {
        // Matikan foreign key check sementara agar proses seed lebih lancar (opsional, tapi aman karena kita menjaga integritas)
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        // ==========================================
        // A. TAHUN AKADEMIK
        // ==========================================
        $academicYearId = $this->upsert('academic_years', ['name' => '2026/2027'], [
            'start_date' => '2026-07-13',
            'end_date'   => '2027-06-26',
            'is_active'  => 1
        ]);

        // ==========================================
        // B. USERS
        // ==========================================
        $defaultPassword = password_hash('password', PASSWORD_BCRYPT);

        $users = [
            'admin' => ['name' => 'Administrator LENTERA', 'email' => 'admin@lentera.test', 'role' => 'admin'],
            'guru_mtk' => ['name' => 'Budi Santoso', 'username' => 'guru.matematika', 'email' => 'guru.matematika@lentera.test', 'role' => 'guru'],
            'guru_bhs' => ['name' => 'Maria Yuliana', 'username' => 'guru.bahasa', 'email' => 'guru.bahasa@lentera.test', 'role' => 'guru'],
            'siswa_yordani' => ['name' => 'Yordani Lodowyk Malo', 'username' => 'siswa.yordani', 'email' => 'siswa.yordani@lentera.test', 'role' => 'siswa'],
            'siswa_andreas' => ['name' => 'Andreas Kabu', 'username' => 'siswa.andreas', 'email' => 'siswa.andreas@lentera.test', 'role' => 'siswa'],
            'siswa_maria' => ['name' => 'Maria Wulandari', 'username' => 'siswa.maria', 'email' => 'siswa.maria@lentera.test', 'role' => 'siswa'],
            'siswa_yohanes' => ['name' => 'Yohanes Bulu', 'username' => 'siswa.yohanes', 'email' => 'siswa.yohanes@lentera.test', 'role' => 'siswa'],
            'siswa_rina' => ['name' => 'Rina Lende', 'username' => 'siswa.rina', 'email' => 'siswa.rina@lentera.test', 'role' => 'siswa'],
        ];

        $userIds = [];
        foreach ($users as $key => $u) {
            $username = $u['username'] ?? 'admin';
            $userIds[$key] = $this->upsert('users', ['username' => $username], [
                'name'      => $u['name'],
                'email'     => $u['email'],
                'password'  => $defaultPassword,
                'role'      => $u['role'],
                'is_active' => 1
            ]);
        }

        // ==========================================
        // C. TEACHERS
        // ==========================================
        $teacherIds = [];
        $teacherIds['guru_mtk'] = $this->upsert('teachers', ['user_id' => $userIds['guru_mtk']], [
            'nip'     => '198001012005011001',
            'phone'   => '081234567890',
            'address' => 'Wewewa Timur, Sumba Barat Daya'
        ]);
        $teacherIds['guru_bhs'] = $this->upsert('teachers', ['user_id' => $userIds['guru_bhs']], [
            'nip'     => '198506152010012002',
            'phone'   => '081298765431',
            'address' => 'Tambolaka, Sumba Barat Daya'
        ]);

        // ==========================================
        // D. CLASSES
        // ==========================================
        $classes = [
            'VII_A'  => ['name' => 'VII A', 'grade' => 7, 'homeroom_teacher_id' => $teacherIds['guru_mtk']],
            'VII_B'  => ['name' => 'VII B', 'grade' => 7, 'homeroom_teacher_id' => $teacherIds['guru_bhs']],
            'VIII_A' => ['name' => 'VIII A', 'grade' => 8, 'homeroom_teacher_id' => null],
            'VIII_B' => ['name' => 'VIII B', 'grade' => 8, 'homeroom_teacher_id' => null],
            'IX_A'   => ['name' => 'IX A', 'grade' => 9, 'homeroom_teacher_id' => null],
        ];

        $classIds = [];
        foreach ($classes as $key => $c) {
            $classIds[$key] = $this->upsert('classes', [
                'academic_year_id' => $academicYearId,
                'name' => $c['name']
            ], [
                'grade' => $c['grade'],
                'homeroom_teacher_id' => $c['homeroom_teacher_id']
            ]);
        }

        // ==========================================
        // E. STUDENTS
        // ==========================================
        $students = [
            'siswa_yordani' => ['nis' => '2026001', 'nisn' => '0012345601', 'class_id' => $classIds['VII_A']],
            'siswa_andreas' => ['nis' => '2026002', 'nisn' => '0012345602', 'class_id' => $classIds['VII_A']],
            'siswa_maria'   => ['nis' => '2026003', 'nisn' => '0012345603', 'class_id' => $classIds['VII_A']],
            'siswa_yohanes' => ['nis' => '2026004', 'nisn' => '0012345604', 'class_id' => $classIds['VII_B']],
            'siswa_rina'    => ['nis' => '2026005', 'nisn' => '0012345605', 'class_id' => $classIds['VII_B']],
        ];

        $studentIds = [];
        foreach ($students as $key => $s) {
            $studentIds[$key] = $this->upsert('students', ['user_id' => $userIds[$key]], [
                'nis'      => $s['nis'],
                'nisn'     => $s['nisn'],
                'class_id' => $s['class_id'],
                'phone'    => '08220000000' . substr($s['nis'], -1),
                'address'  => 'Alamat ' . $users[$key]['name']
            ]);
        }

        // ==========================================
        // F. SUBJECTS
        // ==========================================
        $subjectsData = [
            'MTK'  => 'Matematika',
            'BIN' => 'Bahasa Indonesia',
            'BIG' => 'Bahasa Inggris',
            'IPA'  => 'Ilmu Pengetahuan Alam',
            'IPS' => 'Ilmu Pengetahuan Sosial',
            'PPKN' => 'Pendidikan Pancasila dan Kewarganegaraan',
            'INF' => 'Informatika'
        ];

        $subjectIds = [];
        foreach ($subjectsData as $code => $name) {
            $subjectIds[$code] = $this->upsert('subjects', ['code' => $code], [
                'name'        => $name,
                'description' => 'Mata pelajaran ' . $name,
                'is_active'   => 1
            ]);
        }

        // ==========================================
        // G. COURSES
        // ==========================================
        $coursesData = [
            'MTK_VIIA' => [
                'subject_id' => $subjectIds['MTK'],
                'teacher_id' => $teacherIds['guru_mtk'],
                'class_id' => $classIds['VII_A'],
                'title' => 'Matematika Kelas VII A',
                'status' => 'active'
            ],
            'BIN_VIIA' => [
                'subject_id' => $subjectIds['BIN'],
                'teacher_id' => $teacherIds['guru_bhs'],
                'class_id' => $classIds['VII_A'],
                'title' => 'Bahasa Indonesia Kelas VII A',
                'status' => 'active'
            ],
            'INF_VIIA' => [
                'subject_id' => $subjectIds['INF'],
                'teacher_id' => $teacherIds['guru_mtk'],
                'class_id' => $classIds['VII_A'],
                'title' => 'Informatika Kelas VII A',
                'status' => 'active'
            ],
            'IPA_VIIB' => [
                'subject_id' => $subjectIds['IPA'],
                'teacher_id' => $teacherIds['guru_bhs'],
                'class_id' => $classIds['VII_B'],
                'title' => 'IPA Kelas VII B',
                'status' => 'active'
            ],
        ];

        $courseIds = [];
        foreach ($coursesData as $key => $c) {
            $courseIds[$key] = $this->upsert('courses', [
                'subject_id' => $c['subject_id'],
                'class_id' => $c['class_id'],
                'academic_year_id' => $academicYearId
            ], [
                'teacher_id'  => $c['teacher_id'],
                'title'       => $c['title'],
                'description' => 'Course ' . $c['title'],
                'status'      => $c['status']
            ]);
        }

        // ==========================================
        // H. COURSE STUDENTS (Enrollments)
        // ==========================================
        $enrollments = [
            'MTK_VIIA' => ['siswa_yordani', 'siswa_andreas', 'siswa_maria'],
            'BIN_VIIA' => ['siswa_yordani', 'siswa_andreas', 'siswa_maria'],
            'INF_VIIA' => ['siswa_yordani', 'siswa_andreas', 'siswa_maria'],
            'IPA_VIIB' => ['siswa_yohanes', 'siswa_rina'],
        ];

        foreach ($enrollments as $courseKey => $studentsEnrolled) {
            foreach ($studentsEnrolled as $sKey) {
                $this->upsert('course_students', [
                    'course_id'  => $courseIds[$courseKey],
                    'student_id' => $studentIds[$sKey]
                ], [
                    'enrolled_at' => date('Y-m-d H:i:s', strtotime('-1 month'))
                ]);
            }
        }

        // ==========================================
        // I & J. MATERIALS & MATERIAL VIEWS
        // ==========================================
        $materialsData = [
            'MTK_VIIA' => [
                ['title' => 'Pengantar Bilangan Bulat', 'is_published' => 1],
                ['title' => 'Operasi Bilangan Bulat', 'is_published' => 1],
                ['title' => 'Latihan Bilangan Bulat', 'is_published' => 0] // draft
            ],
            'BIN_VIIA' => [
                ['title' => 'Pengertian Teks Deskripsi', 'is_published' => 1],
                ['title' => 'Struktur Teks Deskripsi', 'is_published' => 1],
            ],
            'INF_VIIA' => [
                ['title' => 'Pengenalan Teknologi Informasi', 'is_published' => 1],
                ['title' => 'Dasar Algoritma', 'is_published' => 1],
            ],
            'IPA_VIIB' => [
                ['title' => 'Pengukuran dan Satuan', 'is_published' => 1],
                ['title' => 'Makhluk Hidup', 'is_published' => 1],
            ]
        ];

        $matIds = [];
        foreach ($materialsData as $courseKey => $mats) {
            foreach ($mats as $idx => $m) {
                $matId = $this->upsert('materials', [
                    'course_id' => $courseIds[$courseKey],
                    'title'     => $m['title']
                ], [
                    'description'  => 'Deskripsi materi ' . $m['title'],
                    'content'      => '<p>Ini adalah konten lengkap untuk materi ' . $m['title'] . '.</p>',
                    'is_published' => $m['is_published'],
                    'published_at' => $m['is_published'] ? date('Y-m-d H:i:s', strtotime('-10 days')) : null
                ]);
                $matIds[$courseKey][] = $matId;
            }
        }

        // Views variasi
        // Yordani melihat materi 1 dan 2 MTK
        $this->upsert('material_views', ['material_id' => $matIds['MTK_VIIA'][0], 'student_id' => $studentIds['siswa_yordani']], ['viewed_at' => date('Y-m-d H:i:s')]);
        $this->upsert('material_views', ['material_id' => $matIds['MTK_VIIA'][1], 'student_id' => $studentIds['siswa_yordani']], ['viewed_at' => date('Y-m-d H:i:s')]);
        // Andreas melihat materi 1 MTK
        $this->upsert('material_views', ['material_id' => $matIds['MTK_VIIA'][0], 'student_id' => $studentIds['siswa_andreas']], ['viewed_at' => date('Y-m-d H:i:s')]);

        // ==========================================
        // K & L. ASSIGNMENTS & SUBMISSIONS
        // ==========================================
        $assignmentsData = [
            'MTK_VIIA' => [
                ['title' => 'Latihan Operasi Bilangan Bulat', 'is_published' => 1, 'due_offset' => '+7 days'],
                ['title' => 'Tugas Pemecahan Masalah Bilangan', 'is_published' => 1, 'due_offset' => '-2 days'], // Past due
            ]
        ];

        $asgIds = [];
        foreach ($assignmentsData as $courseKey => $asgs) {
            foreach ($asgs as $a) {
                $asgIds[] = $this->upsert('assignments', [
                    'course_id' => $courseIds[$courseKey],
                    'title'     => $a['title']
                ], [
                    'description'  => 'Kerjakan soal berikut dengan saksama.',
                    'is_published' => $a['is_published'],
                    'published_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                    'due_at'       => date('Y-m-d H:i:s', strtotime($a['due_offset']))
                ]);
            }
        }

        // Submissions MTK Assignment 1
        $mtkAsg1 = $asgIds[0];

        // Yordani (Submit & Dinilai)
        $this->upsert('submissions', ['assignment_id' => $mtkAsg1, 'student_id' => $studentIds['siswa_yordani']], [
            'answer_text'  => 'Ini jawaban Yordani. Semua soal telah diselesaikan.',
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-1 days')),
            'score'        => 90.00,
            'feedback'     => 'Jawaban sudah sangat baik.',
            'graded_at'    => date('Y-m-d H:i:s')
        ]);

        // Andreas (Submit & Dinilai)
        $this->upsert('submissions', ['assignment_id' => $mtkAsg1, 'student_id' => $studentIds['siswa_andreas']], [
            'answer_text'  => 'Ini jawaban Andreas.',
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-1 days')),
            'score'        => 82.00,
            'feedback'     => 'Jawaban baik, perlu teliti di nomor 3.',
            'graded_at'    => date('Y-m-d H:i:s')
        ]);

        // Maria (Submit & Belum Dinilai)
        $this->upsert('submissions', ['assignment_id' => $mtkAsg1, 'student_id' => $studentIds['siswa_maria']], [
            'answer_text'  => 'Ini jawaban Maria, mohon diperiksa.',
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-1 hours')),
            'score'        => null,
            'feedback'     => null,
            'graded_at'    => null
        ]);

        // ==========================================
        // M, N, O. QUIZZES, QUESTIONS, OPTIONS
        // ==========================================
        $quizzesData = [
            ['course_id' => $courseIds['MTK_VIIA'], 'title' => 'Quiz Bilangan Bulat', 'is_pub' => 1, 'dur' => 30],
            ['course_id' => $courseIds['BIN_VIIA'], 'title' => 'Quiz Teks Deskripsi', 'is_pub' => 1, 'dur' => 45],
            ['course_id' => $courseIds['INF_VIIA'], 'title' => 'Quiz Dasar Algoritma', 'is_pub' => 0, 'dur' => 30], // Draft
            ['course_id' => $courseIds['IPA_VIIB'], 'title' => 'Quiz Ekosistem', 'is_pub' => 1, 'dur' => 30]
        ];

        $quizIds = [];
        foreach ($quizzesData as $idx => $q) {
            $quizId = $this->upsert('quizzes', [
                'course_id' => $q['course_id'],
                'title'     => $q['title']
            ], [
                'description'      => 'Evaluasi kompetensi dasar untuk ' . $q['title'],
                'duration_minutes' => $q['dur'],
                'start_at'         => date('Y-m-d 08:00:00', strtotime('-1 days')),
                'end_at'           => date('Y-m-d 23:59:59', strtotime('+2 days')),
                'is_published'     => $q['is_pub'],
                'published_at'     => $q['is_pub'] ? date('Y-m-d H:i:s', strtotime('-2 days')) : null
            ]);
            $quizIds[$q['title']] = $quizId;

            // Generate 5 Questions per Quiz
            for ($i = 1; $i <= 5; $i++) {
                $qId = $this->upsert('questions', [
                    'quiz_id'      => $quizId,
                    'order_number' => $i
                ], [
                    'question_text' => 'Soal nomor ' . $i . ' untuk ' . $q['title'] . '. Manakah pernyataan yang benar?',
                    'question_type' => 'multiple_choice',
                    'points'        => 20
                ]);

                // Options (A, B, C, D) -> Jadikan opsi C (index 2) yang benar untuk keseragaman, kecuali nomor tertentu
                $correctOptionKey = ($i % 4 == 0) ? 'D' : (($i % 3 == 0) ? 'C' : 'A');
                $options = ['A', 'B', 'C', 'D'];
                foreach ($options as $optIndex => $optKey) {
                    $this->upsert('quiz_options', [
                        'question_id' => $qId,
                        'option_key'  => $optKey
                    ], [
                        'option_text'  => 'Pilihan jawaban ' . $optKey . ' untuk soal ' . $i,
                        'is_correct'   => ($optKey === $correctOptionKey) ? 1 : 0,
                        'order_number' => $optIndex + 1
                    ]);
                }
            }
        }

        // ==========================================
        // P & Q. QUIZ ATTEMPTS & ANSWERS
        // ==========================================
        $quizMtkId = $quizIds['Quiz Bilangan Bulat'];

        // Yordani: Skor 80 (4 Benar, 1 Salah)
        $attemptYordani = $this->upsert('quiz_attempts', [
            'quiz_id'    => $quizMtkId,
            'student_id' => $studentIds['siswa_yordani']
        ], [
            'started_at'   => date('Y-m-d H:i:s', strtotime('-1 hours')),
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'score'        => 80.00,
            'status'       => 'submitted'
        ]);

        // Andreas: Skor 60 (3 Benar, 2 Salah)
        $attemptAndreas = $this->upsert('quiz_attempts', [
            'quiz_id'    => $quizMtkId,
            'student_id' => $studentIds['siswa_andreas']
        ], [
            'started_at'   => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-90 minutes')),
            'score'        => 60.00,
            'status'       => 'submitted'
        ]);

        // Simulasi Jawaban untuk Yordani & Andreas
        $questions = $this->db->table('questions')->where('quiz_id', $quizMtkId)->orderBy('order_number', 'ASC')->get()->getResult();
        foreach ($questions as $idx => $question) {
            $options = $this->db->table('quiz_options')->where('question_id', $question->id)->get()->getResult();
            $correctOption = null;
            $wrongOption = null;
            foreach ($options as $opt) {
                if ($opt->is_correct) $correctOption = $opt;
                else $wrongOption = $opt;
            }

            // Yordani (salah di soal ke-5)
            $isYordaniCorrect = ($idx < 4);
            $this->upsert('quiz_answers', [
                'attempt_id'  => $attemptYordani,
                'question_id' => $question->id
            ], [
                'option_id' => $isYordaniCorrect ? $correctOption->id : $wrongOption->id
            ]);

            // Andreas (salah di soal ke-4 dan ke-5)
            $isAndreasCorrect = ($idx < 3);
            $this->upsert('quiz_answers', [
                'attempt_id'  => $attemptAndreas,
                'question_id' => $question->id
            ], [
                'option_id' => $isAndreasCorrect ? $correctOption->id : $wrongOption->id
            ]);
        }

        // Restore foreign keys
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }
}

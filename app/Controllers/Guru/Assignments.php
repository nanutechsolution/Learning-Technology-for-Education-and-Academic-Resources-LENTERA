<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\AssignmentModel;
use App\Models\CourseModel;
use App\Models\SubmissionModel;
use App\Models\TeacherModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use DateTimeImmutable;

class Assignments extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url', 'assignment_file']);
    }

    /**
     * Daftar tugas pada satu course milik guru.
     */
    public function index($courseId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $course = $this->findCourse($this->ownedCourses((int) $teacher['id']), (int) $courseId);
        if ($course === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $model  = new AssignmentModel();
        $counts = $model->countByCourse((int) $course['id']);

        return auth_no_cache(
            $this->response->setBody(view('guru/assignments/index', [
                'title'       => 'Tugas: ' . $course['title'],
                'course'      => $course,
                'assignments' => $model->getByCourse((int) $course['id']),
                'counts'      => $counts,
            ]))
        );
    }

    /**
     * Form tambah tugas.
     */
    public function create($courseId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $courses = $this->ownedCourses((int) $teacher['id']);
        $course  = $this->findCourse($courses, (int) $courseId);
        if ($course === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/assignments/create', [
                'title'      => 'Tambah Tugas',
                'courses'    => $courses,
                'courseId'   => (int) $course['id'],
                'assignment' => null,
            ]))
        );
    }

    /**
     * Simpan tugas baru (termasuk lampiran).
     */
    public function store($courseId)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $courses  = $this->ownedCourses((int) $teacher['id']);
        $targetId = (int) ($this->request->getPost('course_id') ?: $courseId);

        if ($this->findCourse($courses, $targetId) === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        [$data, $errors] = $this->collectInput();

        $file = lentera_incoming_file($this->request, 'attachment');
        $info = null;

        if ($file !== null) {
            [$info, $fileErrors] = lentera_inspect_upload($file);
            $errors              = array_merge($errors, $fileErrors);
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', $errors);
        }

        $data['course_id'] = $targetId;

        // Simpan file ke disk lebih dulu, lalu DB.
        $stored = null;
        if ($file !== null) {
            $stored = lentera_store_upload($file, assignment_dir(), $info);
            if ($stored === null) {
                return redirect()->back()->withInput()
                    ->with('error', 'File gagal disimpan di server. Periksa folder writable/uploads/assignments.');
            }

            $data = array_merge($data, $this->attachmentColumns($stored, $info));
        }

        $model = new AssignmentModel();

        if ($model->insert($data) === false) {
            if ($stored !== null) {
                assignment_delete_file($stored);
            }

            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        return redirect()->to('guru/courses/' . $targetId . '/assignments')
            ->with('success', 'Tugas berhasil ditambahkan.');
    }

    /**
     * Detail tugas + statistik + daftar pengumpulan siswa.
     */
    public function show($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $assignment = (new AssignmentModel())->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($assignment === null) {
            throw PageNotFoundException::forPageNotFound('Tugas tidak ditemukan.');
        }

        $submissions = new SubmissionModel();

        return auth_no_cache(
            $this->response->setBody(view('guru/assignments/show', [
                'title'      => $assignment['title'],
                'assignment' => $assignment,
                'isOverdue'  => AssignmentModel::isOverdue($assignment),
                'stats'      => $submissions->getStatistics((int) $assignment['id']),
                'roster'     => $submissions->getRosterByAssignment((int) $assignment['id']),
            ]))
        );
    }

    /**
     * Form edit tugas.
     */
    public function edit($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $assignment = (new AssignmentModel())->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($assignment === null) {
            throw PageNotFoundException::forPageNotFound('Tugas tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/assignments/edit', [
                'title'      => 'Edit Tugas',
                'courses'    => $this->ownedCourses((int) $teacher['id']),
                'courseId'   => (int) $assignment['course_id'],
                'assignment' => $assignment,
            ]))
        );
    }

    /**
     * Simpan perubahan tugas (termasuk ganti atau hapus lampiran).
     */
    public function update($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model      = new AssignmentModel();
        $assignment = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($assignment === null) {
            throw PageNotFoundException::forPageNotFound('Tugas tidak ditemukan.');
        }

        $courses  = $this->ownedCourses((int) $teacher['id']);
        $targetId = (int) ($this->request->getPost('course_id') ?: $assignment['course_id']);

        if ($this->findCourse($courses, $targetId) === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        [$data, $errors] = $this->collectInput();

        $hasOldFile = ! empty($assignment['attachment_path']);
        $file       = lentera_incoming_file($this->request, 'attachment');
        $info       = null;

        if ($file !== null) {
            [$info, $fileErrors] = lentera_inspect_upload($file);
            $errors              = array_merge($errors, $fileErrors);
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', $errors);
        }

        $data['course_id'] = $targetId;

        $stored = null;
        if ($file !== null) {
            $stored = lentera_store_upload($file, assignment_dir(), $info);
            if ($stored === null) {
                return redirect()->back()->withInput()
                    ->with('error', 'File gagal disimpan di server. Periksa folder writable/uploads/assignments.');
            }

            $data = array_merge($data, $this->attachmentColumns($stored, $info));
        } elseif ($hasOldFile && (string) $this->request->getPost('remove_attachment') === '1') {
            $data = array_merge($data, [
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_size' => null,
                'attachment_type' => null,
            ]);
        }

        if ($model->update((int) $assignment['id'], $data) === false) {
            // DB gagal: buang file baru, file lama tetap utuh.
            if ($stored !== null) {
                assignment_delete_file($stored);
            }

            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        // DB sukses: lampiran lama (jika diganti atau dihapus) baru boleh dihapus dari disk.
        $replaced = $stored !== null || array_key_exists('attachment_path', $data) && $data['attachment_path'] === null;
        if ($hasOldFile && $replaced) {
            assignment_delete_file($assignment['attachment_path']);
        }

        return redirect()->to('guru/assignments/' . (int) $assignment['id'])
            ->with('success', 'Tugas berhasil diperbarui.');
    }

    /**
     * Hapus tugas (POST). Submission ikut terhapus oleh CASCADE;
     * lampiran dan file jawaban dibersihkan dari disk setelah record hilang.
     */
    public function delete($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model      = new AssignmentModel();
        $assignment = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($assignment === null) {
            throw PageNotFoundException::forPageNotFound('Tugas tidak ditemukan.');
        }

        // Kumpulkan nama file SEBELUM record dihapus.
        $answerFiles = array_column(
            \Config\Database::connect()
                ->table('submissions')
                ->select('file_path')
                ->where('assignment_id', (int) $assignment['id'])
                ->where('file_path IS NOT NULL', null, false)
                ->get()
                ->getResultArray(),
            'file_path'
        );

        if ($model->delete((int) $assignment['id']) === false) {
            return redirect()->back()->with('error', 'Tugas gagal dihapus.');
        }

        // Record sudah hilang; sekarang bersihkan file di disk.
        assignment_delete_file($assignment['attachment_path'] ?? null);

        foreach ($answerFiles as $name) {
            submission_delete_file($name);
        }

        return redirect()->to('guru/courses/' . (int) $assignment['course_id'] . '/assignments')
            ->with('success', 'Tugas berhasil dihapus beserta pengumpulan siswanya.');
    }

    /**
     * Publish / kembalikan ke draft (POST).
     */
    public function togglePublish($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model      = new AssignmentModel();
        $assignment = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($assignment === null) {
            throw PageNotFoundException::forPageNotFound('Tugas tidak ditemukan.');
        }

        $publish = (int) $assignment['is_published'] !== 1;

        if ($model->update((int) $assignment['id'], ['is_published' => $publish ? 1 : 0]) === false) {
            return redirect()->back()->with('error', array_values($model->errors()));
        }

        return redirect()->back()->with(
            'success',
            $publish ? 'Tugas berhasil dipublikasikan.' : 'Tugas dikembalikan ke draft.'
        );
    }

    /**
     * Download lampiran tugas (hanya guru pemilik).
     */
    public function download($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $assignment = (new AssignmentModel())->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($assignment === null || empty($assignment['attachment_path'])) {
            throw PageNotFoundException::forPageNotFound('Lampiran tidak ditemukan.');
        }

        $path = assignment_file_path($assignment['attachment_path']);
        if ($path === null) {
            throw PageNotFoundException::forPageNotFound('Lampiran tidak ditemukan di server.');
        }

        return $this->response
            ->download($path, null)
            ->setFileName($assignment['attachment_name'] ?: basename($path))
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    // ------------------------------------------------------------------
    // Helper privat
    // ------------------------------------------------------------------

    private function teacher(): ?array
    {
        $teacher = (new TeacherModel())->findByUserId(auth_id());

        return $teacher ? (array) $teacher : null;
    }

    private function noProfile()
    {
        return redirect()->to('guru/dashboard')
            ->with('warning', 'Akun Anda belum terhubung dengan profil guru. Hubungi admin.');
    }

    /**
     * Semua course milik guru (sumber tunggal otorisasi course).
     */
    private function ownedCourses(int $teacherId): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            (new CourseModel())->getByTeacher($teacherId)
        );
    }

    private function findCourse(array $courses, int $courseId): ?array
    {
        foreach ($courses as $course) {
            if ((int) $course['id'] === $courseId) {
                return $course;
            }
        }

        return null;
    }

    /**
     * Kolom attachment_* untuk file yang baru tersimpan.
     */
    private function attachmentColumns(string $storedName, array $info): array
    {
        return [
            'attachment_path' => $storedName,
            'attachment_name' => $info['name'],
            'attachment_size' => $info['size'],
            'attachment_type' => $info['mime'],
        ];
    }

    /**
     * Ambil input form yang sudah dirapikan.
     *
     * @return array{0: array, 1: list<string>} [data, daftar error]
     */
    private function collectInput(): array
    {
        [$dueAt, $dueError] = $this->parseDueAt((string) $this->request->getPost('due_at'));

        $data = [
            'title'        => trim((string) $this->request->getPost('title')),
            'description'  => trim((string) $this->request->getPost('description')),
            'due_at'       => $dueAt,
            'is_published' => (string) $this->request->getPost('is_published') === '1' ? 1 : 0,
        ];

        return [$data, $dueError === null ? [] : [$dueError]];
    }

    /**
     * Ubah input batas waktu (datetime-local atau teks) menjadi 'Y-m-d H:i:s'.
     * Kosong = tanpa batas waktu. Tanggal yang tidak ada di kalender ditolak.
     *
     * @return array{0: ?string, 1: ?string} [nilai, pesan error]
     */
    private function parseDueAt(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [null, null];
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $date   = DateTimeImmutable::createFromFormat('!' . $format, $raw);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return [$date->format('Y-m-d H:i:s'), null];
            }
        }

        return [null, 'Format batas waktu pengumpulan tidak valid.'];
    }
}
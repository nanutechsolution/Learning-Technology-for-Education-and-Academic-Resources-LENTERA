<?php

namespace App\Controllers\Guru;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\MaterialModel;
use App\Models\MaterialViewModel;
use App\Models\TeacherModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\Files\UploadedFile;

class Materials extends BaseController
{
    private const MAX_FILE_SIZE = 10485760; // 10 MB

    /**
     * Whitelist: ekstensi => daftar MIME hasil deteksi isi file (finfo).
     * File OOXML (docx/xlsx/pptx) sering terdeteksi sebagai zip; file Office lama
     * sebagai kontainer OLE. File PHP/teks tidak masuk daftar mana pun.
     */
    private const ALLOWED_FILES = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip-compressed'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/x-zip-compressed'],
    ];

    public function __construct()
    {
        helper(['auth', 'url', 'material_file']);
    }

    /**
     * Daftar materi pada satu course milik guru.
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

        $materialModel = new MaterialModel();
        $counts        = $materialModel->countByCourse((int) $course['id']);

        return auth_no_cache(
            $this->response->setBody(view('guru/materials/index', [
                'title'     => 'Materi: ' . $course['title'],
                'course'    => $course,
                'materials' => $materialModel->getByCourse((int) $course['id']),
                'counts'    => [
                    'total'     => (int) $counts['total'],
                    'published' => (int) $counts['published'],
                    'draft'     => (int) $counts['draft'],
                ],
            ]))
        );
    }

    /**
     * Form tambah materi.
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
            $this->response->setBody(view('guru/materials/create', [
                'title'    => 'Tambah Materi',
                'courses'  => $courses,
                'courseId' => (int) $course['id'],
                'material' => null,
            ]))
        );
    }

    /**
     * Simpan materi baru (termasuk upload file).
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

        $model = new MaterialModel();
        $data  = $this->collectInput();

        $data['course_id'] = $targetId;

        // Validasi file (jika ada) + aturan tambahan.
        $file       = $this->incomingFile();
        $info       = null;
        $fileErrors = [];

        if ($file !== null) {
            [$info, $fileErrors] = $this->inspectFile($file);
        }

        $errors = array_merge($fileErrors, $this->extraErrors($model, $data, $file !== null));
        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', $errors);
        }

        // Simpan file ke disk lebih dulu, lalu DB.
        $stored = null;
        if ($file !== null) {
            $stored = $this->storeFile($file, $info);
            if ($stored === null) {
                return redirect()->back()->withInput()
                    ->with('error', 'File gagal disimpan di server. Periksa folder writable/uploads/materials.');
            }

            $data = array_merge($data, $stored);
        }

        if ($model->insert($data) === false) {
            if ($stored !== null) {
                material_delete_file($stored['file_path']);
            }

            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        return redirect()->to('guru/courses/' . $targetId . '/materials')
            ->with('success', 'Materi berhasil ditambahkan.');
    }

    /**
     * Detail materi + statistik akses siswa.
     */
    public function show($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $material = (new MaterialModel())->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($material === null) {
            throw PageNotFoundException::forPageNotFound('Materi tidak ditemukan.');
        }

        $viewModel = new MaterialViewModel();

        return auth_no_cache(
            $this->response->setBody(view('guru/materials/show', [
                'title'     => $material['title'],
                'material'  => $material,
                'youtubeId' => MaterialModel::youtubeId($material['video_url'] ?? null),
                'stats'     => $viewModel->getStatistics((int) $material['id']),
                'students'  => $viewModel->getStudentStatus((int) $material['id']),
            ]))
        );
    }

    /**
     * Form edit materi.
     */
    public function edit($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $material = (new MaterialModel())->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($material === null) {
            throw PageNotFoundException::forPageNotFound('Materi tidak ditemukan.');
        }

        return auth_no_cache(
            $this->response->setBody(view('guru/materials/edit', [
                'title'    => 'Edit Materi',
                'courses'  => $this->ownedCourses((int) $teacher['id']),
                'courseId' => (int) $material['course_id'],
                'material' => $material,
            ]))
        );
    }

    /**
     * Simpan perubahan materi (termasuk ganti file).
     */
    public function update($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model    = new MaterialModel();
        $material = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($material === null) {
            throw PageNotFoundException::forPageNotFound('Materi tidak ditemukan.');
        }

        $courses  = $this->ownedCourses((int) $teacher['id']);
        $targetId = (int) ($this->request->getPost('course_id') ?: $material['course_id']);

        if ($this->findCourse($courses, $targetId) === null) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $data = $this->collectInput();

        $data['course_id'] = $targetId;

        $hasOldFile = ! empty($material['file_path']);
        $file       = $this->incomingFile();
        $info       = null;
        $fileErrors = [];

        if ($file !== null) {
            [$info, $fileErrors] = $this->inspectFile($file);
        }

        $errors = array_merge($fileErrors, $this->extraErrors($model, $data, $hasOldFile || $file !== null));
        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', $errors);
        }

        $stored = null;
        if ($file !== null) {
            $stored = $this->storeFile($file, $info);
            if ($stored === null) {
                return redirect()->back()->withInput()
                    ->with('error', 'File gagal disimpan di server. Periksa folder writable/uploads/materials.');
            }

            $data = array_merge($data, $stored);
        }

        if ($model->update((int) $material['id'], $data) === false) {
            // DB gagal: buang file baru, file lama tetap utuh.
            if ($stored !== null) {
                material_delete_file($stored['file_path']);
            }

            return redirect()->back()->withInput()->with('error', array_values($model->errors()));
        }

        // DB sukses: file lama (jika diganti) baru boleh dihapus.
        if ($stored !== null && $hasOldFile) {
            material_delete_file($material['file_path']);
        }

        return redirect()->to('guru/materials/' . (int) $material['id'])
            ->with('success', 'Materi berhasil diperbarui.');
    }

    /**
     * Hapus materi (POST) beserta file di disk.
     */
    public function delete($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $model    = new MaterialModel();
        $material = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($material === null) {
            throw PageNotFoundException::forPageNotFound('Materi tidak ditemukan.');
        }

        if ($model->delete((int) $material['id']) === false) {
            return redirect()->back()->with('error', 'Materi gagal dihapus.');
        }

        // Record sudah hilang; sekarang bersihkan file di disk.
        material_delete_file($material['file_path'] ?? null);

        return redirect()->to('guru/courses/' . (int) $material['course_id'] . '/materials')
            ->with('success', 'Materi berhasil dihapus.');
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

        $model    = new MaterialModel();
        $material = $model->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($material === null) {
            throw PageNotFoundException::forPageNotFound('Materi tidak ditemukan.');
        }

        $publish = (int) $material['is_published'] !== 1;

        if ($model->update((int) $material['id'], ['is_published' => $publish ? 1 : 0]) === false) {
            return redirect()->back()->with('error', array_values($model->errors()));
        }

        return redirect()->back()->with(
            'success',
            $publish ? 'Materi berhasil dipublikasikan.' : 'Materi dikembalikan ke draft.'
        );
    }

    /**
     * Download file materi (hanya guru pemilik).
     */
    public function download($id)
    {
        $teacher = $this->teacher();
        if ($teacher === null) {
            return $this->noProfile();
        }

        $material = (new MaterialModel())->findOwnedByTeacher((int) $id, (int) $teacher['id']);
        if ($material === null || empty($material['file_path'])) {
            throw PageNotFoundException::forPageNotFound('File materi tidak ditemukan.');
        }

        $path = material_file_path($material['file_path']);
        if ($path === null) {
            throw PageNotFoundException::forPageNotFound('File materi tidak ditemukan di server.');
        }

        return $this->response
            ->download($path, null)
            ->setFileName($material['file_name'] ?: basename($path))
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
            static fn($row) => (array) $row,
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
     * Ambil input form yang sudah dirapikan.
     */
    private function collectInput(): array
    {
        return [
            'title'        => trim((string) $this->request->getPost('title')),
            'description'  => trim((string) $this->request->getPost('description')),
            'content'      => trim((string) $this->request->getPost('content')),
            'video_url'    => trim((string) $this->request->getPost('video_url')),
            'is_published' => (string) $this->request->getPost('is_published') === '1' ? 1 : 0,
        ];
    }

    /**
     * Aturan tambahan di luar validasi model: video harus YouTube,
     * dan minimal satu dari isi / file / video.
     */
    private function extraErrors(MaterialModel $model, array $data, bool $hasFile): array
    {
        $errors = [];

        if ($data['video_url'] !== '' && MaterialModel::youtubeId($data['video_url']) === null) {
            $errors[] = 'Video URL hanya mendukung tautan YouTube (youtube.com/watch?v=... atau youtu.be/...).';
        }

        if (! $model->hasContent($data, $hasFile)) {
            $errors[] = 'Isi materi, file, atau video harus diisi minimal salah satu.';
        }

        return $errors;
    }

    /**
     * File yang dikirim lewat form, atau NULL jika tidak ada file dipilih.
     */
    private function incomingFile(): ?UploadedFile
    {
        $file = $this->request->getFile('material_file');

        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /**
     * Periksa file: error upload, ekstensi, ukuran, dan MIME hasil deteksi isi.
     *
     * @return array{0: array|null, 1: array} [info|null, daftar error]
     */
    private function inspectFile(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            return [null, [$this->uploadErrorMessage($file->getError())]];
        }

        if (! extension_loaded('fileinfo')) {
            return [null, ['Ekstensi PHP "fileinfo" belum aktif di server, sehingga file tidak dapat diperiksa. Hubungi admin.']];
        }

        $ext = strtolower($file->getClientExtension());

        if (! isset(self::ALLOWED_FILES[$ext])) {
            return [null, ['Tipe file tidak diizinkan. Gunakan PDF, DOC, DOCX, PPT, PPTX, XLS, atau XLSX.']];
        }

        $size = (int) $file->getSize();

        if ($size > self::MAX_FILE_SIZE) {
            return [null, ['Ukuran file melebihi batas maksimal 10 MB.']];
        }

        if ($size < 1) {
            return [null, ['File kosong tidak dapat diunggah.']];
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, self::ALLOWED_FILES[$ext], true)) {
            return [null, ['Isi file tidak sesuai dengan ekstensi .' . $ext . ' (terdeteksi: ' . $mime . ').']];
        }

        return [[
            'ext'  => $ext,
            'mime' => $mime,
            'size' => $size,
            'name' => $this->cleanName($file->getClientName(), $ext),
        ], []];
    }

    /**
     * Pindahkan file ke writable/uploads/materials dengan nama acak.
     *
     * @return array|null kolom file_* untuk disimpan, atau NULL jika gagal
     */
    private function storeFile(UploadedFile $file, array $info): ?array
    {
        $dir = material_dir();

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            log_message('error', 'Tidak dapat membuat folder ' . $dir);

            return null;
        }

        $newName = bin2hex(random_bytes(16)) . '.' . $info['ext'];

        try {
            $file->move($dir, $newName);
        } catch (\Throwable $e) {
            log_message('error', 'Gagal memindahkan file materi: ' . $e->getMessage());

            return null;
        }

        if (! is_file($dir . $newName)) {
            return null;
        }

        return [
            'file_path' => $newName,
            'file_name' => $info['name'],
            'file_size' => $info['size'],
            'file_type' => $info['mime'],
        ];
    }

    /**
     * Nama asli yang aman disimpan/ditampilkan: tanpa path dan karakter kontrol,
     * ekstensi memakai ekstensi yang sudah divalidasi.
     */
    private function cleanName(string $name, string $ext): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME);
        $base = preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]+/u', '_', $base);
        $base = trim((string) $base);

        if ($base === '') {
            $base = 'materi';
        }

        return mb_substr($base, 0, 150) . '.' . $ext;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas maksimal 10 MB.',
            UPLOAD_ERR_PARTIAL                        => 'File hanya terunggah sebagian. Coba unggah ulang.',
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE,
            UPLOAD_ERR_EXTENSION                      => 'Server gagal menyimpan file sementara. Hubungi admin.',
            default                                   => 'File gagal diunggah (kode ' . $code . ').',
        };
    }
}

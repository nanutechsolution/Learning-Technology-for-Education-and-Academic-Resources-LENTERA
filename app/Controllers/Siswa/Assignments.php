<?php

namespace App\Controllers\Siswa;

use App\Controllers\BaseController;
use App\Models\AssignmentModel;
use App\Models\CourseModel;
use App\Models\CourseStudentModel;
use App\Models\StudentModel;
use App\Models\SubmissionModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Assignments extends BaseController
{
    public function __construct()
    {
        helper(['auth', 'url', 'assignment_file']);
    }

    /**
     * Daftar tugas published pada satu course yang diikuti siswa.
     */
    public function index($courseId)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $courseId = (int) $courseId;

        if (! (new CourseStudentModel())->isEnrolled($courseId, $studentId)) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $course = (new CourseModel())->findWithRelations($courseId);
        if (! $course) {
            throw PageNotFoundException::forPageNotFound('Course tidak ditemukan.');
        }

        $assignments = [];

        foreach ((new AssignmentModel())->getPublishedByCourse($courseId, $studentId) as $row) {
            $overdue = AssignmentModel::isOverdue($row);

            if (! empty($row['graded_at'])) {
                $state = 'graded';
            } elseif (! empty($row['submitted_at'])) {
                $state = 'submitted';
            } elseif ($overdue) {
                $state = 'closed';
            } else {
                $state = 'open';
            }

            $row['is_overdue'] = $overdue;
            $row['state']      = $state; // graded | submitted | closed | open

            $assignments[] = $row;
        }

        $course = (array) $course;

        return auth_no_cache(
            $this->response->setBody(view('siswa/assignments/index', [
                'title'       => 'Tugas: ' . $course['title'],
                'course'      => $course,
                'assignments' => $assignments,
            ]))
        );
    }

    /**
     * Detail tugas + jawaban siswa + form pengumpulan.
     */
    public function show($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $assignment = (new AssignmentModel())->findForStudent((int) $id, $studentId);
        if ($assignment === null) {
            throw PageNotFoundException::forPageNotFound('Tugas tidak ditemukan.');
        }

        $submission = (new SubmissionModel())->findByAssignmentAndStudent((int) $assignment['id'], $studentId);
        $overdue    = AssignmentModel::isOverdue($assignment);
        $graded     = $submission !== null && ! empty($submission['graded_at']);

        $closedReason = null;
        if ($graded) {
            $closedReason = 'Tugas sudah dinilai oleh guru, jawaban tidak dapat diubah lagi.';
        } elseif ($overdue) {
            $closedReason = 'Batas waktu pengumpulan telah berakhir.';
        }

        return auth_no_cache(
            $this->response->setBody(view('siswa/assignments/show', [
                'title'        => $assignment['title'],
                'assignment'   => $assignment,
                'submission'   => $submission,
                'isOverdue'    => $overdue,
                'canSubmit'    => $closedReason === null,
                'closedReason' => $closedReason,
            ]))
        );
    }

    /**
     * Kumpulkan atau perbarui jawaban (POST). Satu record per siswa dan tugas.
     * Jawaban boleh berupa teks, file, atau keduanya.
     */
    public function submit($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $assignment = (new AssignmentModel())->findForStudent((int) $id, $studentId);
        if ($assignment === null) {
            throw PageNotFoundException::forPageNotFound('Tugas tidak ditemukan.');
        }

        $back  = 'siswa/assignments/' . (int) $assignment['id'];
        $model = new SubmissionModel();

        $existing = $model->findByAssignmentAndStudent((int) $assignment['id'], $studentId);

        if ($existing !== null && ! empty($existing['graded_at'])) {
            return redirect()->to($back)
                ->with('warning', 'Tugas sudah dinilai oleh guru, jawaban tidak dapat diubah lagi.');
        }

        if (! AssignmentModel::isOpenForSubmission($assignment)) {
            return redirect()->to($back)
                ->with('error', 'Batas waktu pengumpulan telah berakhir.');
        }

        $data       = ['answer_text' => trim((string) $this->request->getPost('answer_text'))];
        $hasOldFile = $existing !== null && ! empty($existing['file_path']);
        $errors     = [];

        $file = lentera_incoming_file($this->request, 'answer_file');
        $info = null;

        if ($file !== null) {
            [$info, $fileErrors] = lentera_inspect_upload($file);
            $errors              = array_merge($errors, $fileErrors);
        }

        if ($errors === [] && ! SubmissionModel::hasContent($data, $hasOldFile || $file !== null)) {
            $errors[] = 'Jawaban teks atau file harus diisi minimal salah satu.';
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', $errors);
        }

        // Simpan file ke disk lebih dulu, lalu DB.
        $stored = null;
        if ($file !== null) {
            $stored = lentera_store_upload($file, submission_dir(), $info);
            if ($stored === null) {
                return redirect()->back()->withInput()
                    ->with('error', 'File gagal disimpan di server. Hubungi admin.');
            }

            $data['file_path'] = $stored;
            $data['file_name'] = $info['name'];
            $data['file_size'] = $info['size'];
            $data['file_type'] = $info['mime'];
        }

        if (! $model->submit((int) $assignment['id'], $studentId, $data)) {
            // DB gagal: buang file baru, file lama tetap utuh.
            if ($stored !== null) {
                submission_delete_file($stored);
            }

            $errors = array_values($model->errors());

            return redirect()->back()->withInput()
                ->with('error', $errors !== [] ? $errors : 'Jawaban gagal disimpan. Coba lagi.');
        }

        // DB sukses: file lama yang diganti baru boleh dihapus dari disk.
        if ($stored !== null && $hasOldFile) {
            submission_delete_file($existing['file_path']);
        }

        return redirect()->to($back)->with(
            'success',
            ($existing !== null && ! empty($existing['submitted_at']))
                ? 'Jawaban berhasil diperbarui.'
                : 'Jawaban berhasil dikumpulkan.'
        );
    }

    /**
     * Download lampiran tugas (siswa terdaftar, tugas published).
     */
    public function download($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $assignment = (new AssignmentModel())->findForStudent((int) $id, $studentId);
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

    /**
     * Download file jawaban milik siswa sendiri.
     */
    public function downloadSubmission($id)
    {
        $studentId = $this->studentId();
        if ($studentId === null) {
            return $this->noProfile();
        }

        $assignment = (new AssignmentModel())->findForStudent((int) $id, $studentId);
        if ($assignment === null) {
            throw PageNotFoundException::forPageNotFound('Tugas tidak ditemukan.');
        }

        $submission = (new SubmissionModel())->findByAssignmentAndStudent((int) $assignment['id'], $studentId);
        if ($submission === null || empty($submission['file_path'])) {
            throw PageNotFoundException::forPageNotFound('File jawaban tidak ditemukan.');
        }

        $path = submission_file_path($submission['file_path']);
        if ($path === null) {
            throw PageNotFoundException::forPageNotFound('File jawaban tidak ditemukan di server.');
        }

        return $this->response
            ->download($path, null)
            ->setFileName($submission['file_name'] ?: basename($path))
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    // ------------------------------------------------------------------
    // Helper privat
    // ------------------------------------------------------------------

    private function studentId(): ?int
    {
        $student = (new StudentModel())->findByUserId(auth_id());

        return $student ? (int) ((array) $student)['id'] : null;
    }

    private function noProfile()
    {
        return redirect()->to('siswa/dashboard')
            ->with('warning', 'Akun Anda belum terhubung dengan profil siswa. Hubungi admin.');
    }
}
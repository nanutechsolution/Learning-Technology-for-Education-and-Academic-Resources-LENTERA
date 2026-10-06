<?php

namespace App\Commands;

use App\Models\AssignmentModel;
use App\Models\SubmissionModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * SEMENTARA: uji model Phase 3. Hapus file ini setelah dipakai.
 * Jalankan: php spark lentera:check-assignment
 * Data uji berjudul "[UJI3] ..." dan selalu dibersihkan di akhir.
 */
class CheckAssignment extends BaseCommand
{
    protected $group       = 'LENTERA';
    protected $name        = 'lentera:check-assignment';
    protected $description = 'SEMENTARA: menguji AssignmentModel dan SubmissionModel.';

    private int $failed = 0;

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        $guruUser  = $db->table('users')->where('username', 'guru.demo')->get()->getRowArray();
        $siswaUser = $db->table('users')->where('username', 'siswa.demo')->get()->getRowArray();

        if (! $guruUser || ! $siswaUser) {
            CLI::error('Akun guru.demo / siswa.demo tidak ditemukan.');

            return;
        }

        $teacher = $db->table('teachers')->where('user_id', $guruUser['id'])->get()->getRowArray();
        $student = $db->table('students')->where('user_id', $siswaUser['id'])->get()->getRowArray();

        if (! $teacher || ! $student) {
            CLI::error('guru.demo / siswa.demo belum punya profil teachers / students.');

            return;
        }

        $course = $db->table('courses c')
            ->select('c.*')
            ->join('course_students cs', 'cs.course_id = c.id')
            ->where('c.teacher_id', $teacher['id'])
            ->where('cs.student_id', $student['id'])
            ->orderBy('c.id', 'ASC')
            ->get(1)
            ->getRowArray();

        if (! $course) {
            CLI::error('Tidak ada course milik guru.demo yang diikuti siswa.demo.');

            return;
        }

        $courseId  = (int) $course['id'];
        $teacherId = (int) $teacher['id'];
        $studentId = (int) $student['id'];

        $otherTeacher = $db->table('teachers')->where('id !=', $teacherId)->get(1)->getRowArray();
        $otherStudent = $db->table('students')
            ->where('id NOT IN (SELECT student_id FROM course_students WHERE course_id = ' . $courseId . ')', null, false)
            ->get(1)
            ->getRowArray();

        $a = new AssignmentModel();
        $s = new SubmissionModel();

        try {
            $this->runChecks($db, $a, $s, $courseId, $teacherId, $studentId, $otherTeacher, $otherStudent);
        } finally {
            $db->table('assignments')->like('title', '[UJI3]', 'after')->delete();
            CLI::write('Data uji [UJI3] dibersihkan.', 'yellow');
        }

        if ($this->failed > 0) {
            CLI::error($this->failed . ' pengecekan GAGAL.');
        } else {
            CLI::write('Semua pengecekan lulus.', 'green');
        }
    }

    private function runChecks($db, AssignmentModel $a, SubmissionModel $s, int $courseId, int $teacherId, int $studentId, ?array $otherTeacher, ?array $otherStudent): void
    {
        $future = date('Y-m-d H:i:s', strtotime('+7 days'));
        $past   = date('Y-m-d H:i:s', strtotime('-1 day'));

        // ---- Validasi dan normalisasi AssignmentModel
        $this->check($a->insert(['course_id' => $courseId, 'title' => '']) === false, 'judul kosong ditolak');
        $this->check($a->insert(['course_id' => $courseId, 'title' => str_repeat('x', 256)]) === false, 'judul 256 karakter ditolak');
        $this->check($a->insert(['course_id' => $courseId, 'title' => '[UJI3] tanggal', 'due_at' => 'bukan tanggal']) === false, 'due_at tidak valid ditolak');
        $this->check($a->insert(['course_id' => 999999999, 'title' => '[UJI3] course']) === false, 'course tidak ada ditolak');

        $id = $a->insert([
            'course_id'    => $courseId,
            'title'        => '[UJI3] Tugas Utama',
            'description'  => '',
            'due_at'       => $future,
            'is_published' => 0,
        ]);
        $this->check($id !== false, 'insert tugas draft berhasil');
        $id = (int) $id;

        $row = $a->find($id);
        $this->check($row !== null && $row['description'] === null, 'description kosong menjadi NULL');
        $this->check($row !== null && (int) $row['is_published'] === 0 && $row['published_at'] === null, 'draft: published_at NULL');

        // ---- Akses draft
        $this->check($a->findForStudent($id, $studentId) === null, 'siswa tidak melihat tugas draft');
        $published = $a->getPublishedByCourse($courseId, $studentId);
        $this->check(! in_array($id, array_map('intval', array_column($published, 'id')), true), 'draft tidak muncul di getPublishedByCourse');

        // ---- Publish
        $this->check($a->update($id, ['is_published' => 1]) !== false, 'publish berhasil');
        $row = $a->find($id);
        $this->check(! empty($row['published_at']), 'published_at terisi saat publish');
        $firstPublished = $row['published_at'] ?? null;

        sleep(1);
        $a->update($id, ['title' => '[UJI3] Tugas Utama (edit)', 'is_published' => 1]);
        $row = $a->find($id);
        $this->check(($row['published_at'] ?? null) === $firstPublished, 'edit tugas published tidak mengubah published_at');

        // ---- Otorisasi
        $this->check($a->findOwnedByTeacher($id, $teacherId) !== null, 'findOwnedByTeacher (pemilik) ada');
        if ($otherTeacher) {
            $this->check($a->findOwnedByTeacher($id, (int) $otherTeacher['id']) === null, 'findOwnedByTeacher (guru lain) NULL');
        } else {
            CLI::write('SKIP  - guru lain tidak ada di database', 'yellow');
        }

        $this->check($a->findForStudent($id, $studentId) !== null, 'findForStudent (siswa terdaftar, published) ada');
        if ($otherStudent) {
            $this->check($a->findForStudent($id, (int) $otherStudent['id']) === null, 'findForStudent (siswa tidak terdaftar) NULL');
        } else {
            CLI::write('SKIP  - siswa tidak terdaftar tidak ada di database', 'yellow');
        }

        $published = $a->getPublishedByCourse($courseId, $studentId);
        $found     = array_values(array_filter($published, static fn($r) => (int) $r['id'] === $id));
        $this->check($found !== [] && array_key_exists('submitted_at', $found[0]) && $found[0]['submitted_at'] === null, 'getPublishedByCourse memuat status siswa (belum mengumpulkan)');

        // ---- Batas waktu
        $this->check(AssignmentModel::isOverdue(['due_at' => $past]) === true, 'isOverdue: batas lewat = true');
        $this->check(AssignmentModel::isOverdue(['due_at' => $future]) === false, 'isOverdue: batas depan = false');
        $this->check(AssignmentModel::isOverdue(['due_at' => null]) === false, 'isOverdue: tanpa batas = false');
        $this->check(AssignmentModel::isOpenForSubmission(['due_at' => $future, 'is_published' => 1]) === true, 'isOpenForSubmission: published dan belum lewat = true');
        $this->check(AssignmentModel::isOpenForSubmission(['due_at' => $past, 'is_published' => 1]) === false, 'isOpenForSubmission: lewat batas = false');
        $this->check(AssignmentModel::isOpenForSubmission(['due_at' => $future, 'is_published' => 0]) === false, 'isOpenForSubmission: draft = false');

        // ---- Pengumpulan
        $count = static fn() => $db->table('submissions')->where('assignment_id', $id)->where('student_id', $studentId)->countAllResults();

        $this->check($s->submit($id, $studentId, ['answer_text' => 'Jawaban pertama']), 'submit pertama berhasil');
        $sub = $s->findByAssignmentAndStudent($id, $studentId);
        $this->check($count() === 1 && $sub !== null && ! empty($sub['submitted_at']), 'submit pertama: satu record dengan submitted_at');

        $this->check($s->submit($id, $studentId, ['answer_text' => 'Jawaban kedua']), 'submit kedua (resubmit) berhasil');
        $sub = $s->findByAssignmentAndStudent($id, $studentId);
        $this->check($count() === 1 && $sub['answer_text'] === 'Jawaban kedua', 'resubmit memperbarui record yang sama (tidak ada duplikat)');

        $s->submit($id, $studentId, [
            'answer_text' => 'Dengan file',
            'file_path'   => 'abc123.pdf',
            'file_name'   => 'jawaban.pdf',
            'file_size'   => 100,
            'file_type'   => 'application/pdf',
        ]);
        $s->submit($id, $studentId, ['answer_text' => 'Tanpa file baru']);
        $sub = $s->findByAssignmentAndStudent($id, $studentId);
        $this->check($sub['file_path'] === 'abc123.pdf' && $sub['file_name'] === 'jawaban.pdf', 'resubmit tanpa file baru mempertahankan file lama');

        $dupBlocked = false;
        try {
            $now = date('Y-m-d H:i:s');
            $r   = $db->table('submissions')->insert([
                'assignment_id' => $id,
                'student_id' => $studentId,
                'answer_text' => 'duplikat',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $dupBlocked = ($r === false);
        } catch (\Throwable $e) {
            $dupBlocked = true;
        }
        $this->check($dupBlocked && $count() === 1, 'UNIQUE assignment_id+student_id menolak insert duplikat');

        $this->check(SubmissionModel::hasContent(['answer_text' => '  ']) === false, 'hasContent: kosong = false');
        $this->check(SubmissionModel::hasContent(['answer_text' => 'x']) === true, 'hasContent: teks = true');
        $this->check(SubmissionModel::hasContent([], true) === true, 'hasContent: file lama = true');

        // ---- Penilaian
        $subId = (int) $sub['id'];

        $this->check($s->grade($subId, '101', 'x') === false, 'nilai 101 ditolak');
        $this->check($s->grade($subId, '-1', 'x') === false, 'nilai -1 ditolak');
        $this->check($s->grade($subId, 'abc', 'x') === false, 'nilai bukan angka ditolak');
        $this->check($s->grade($subId, '', 'x') === false, 'nilai kosong ditolak');
        $this->check($s->grade(999999999, '80', 'x') === false, 'menilai submission yang tidak ada = false');

        $sub = $s->find($subId);
        $this->check($sub['score'] === null && $sub['graded_at'] === null, 'penilaian gagal tidak mengubah data');

        $this->check($s->grade($subId, '85,5', 'Bagus') === true, 'nilai "85,5" diterima');
        $sub = $s->find($subId);
        $this->check(abs((float) $sub['score'] - 85.5) < 0.001 && $sub['feedback'] === 'Bagus' && ! empty($sub['graded_at']), 'nilai 85.5, feedback, dan graded_at tersimpan');

        $this->check($s->grade($subId, 0, 'Nol') === true && abs((float) $s->find($subId)['score']) < 0.001, 'nilai 0 diterima');
        $s->grade($subId, '85,5', 'Bagus');

        // ---- Otorisasi penilaian
        $ft = $s->findForTeacher($subId, $teacherId);
        $this->check($ft !== null && ! empty($ft['student_name']) && $ft['assignment_title'] !== '', 'findForTeacher (pemilik) memuat nama siswa dan judul tugas');
        if ($otherTeacher) {
            $this->check($s->findForTeacher($subId, (int) $otherTeacher['id']) === null, 'findForTeacher (guru lain) NULL');
        }

        // ---- Rekap
        $stats = $s->getStatistics($id);
        $this->check($stats['submitted'] === 1 && $stats['graded'] === 1 && $stats['total'] >= 1, 'statistik: submitted 1, graded 1, total >= 1');
        $this->check($stats['average'] !== null && abs($stats['average'] - 85.5) < 0.001, 'statistik: rata-rata 85.5');
        $this->check($stats['late'] === 0, 'statistik: belum ada keterlambatan');

        $a->update($id, ['due_at' => $past]);
        $stats = $s->getStatistics($id);
        $this->check($stats['late'] === 1, 'statistik: submitted setelah batas dihitung terlambat');
        $a->update($id, ['due_at' => $future]);

        $roster = $s->getRosterByAssignment($id);
        $mine   = array_values(array_filter($roster, static fn($r) => (int) $r['student_id'] === $studentId));
        $this->check($mine !== [] && ! empty($mine[0]['submitted_at']) && abs((float) $mine[0]['score'] - 85.5) < 0.001, 'roster memuat siswa uji dengan status dan nilai');

        $progress = $s->getProgressByStudent($studentId);
        $this->check($progress['total'] >= 1 && $progress['submitted'] >= 1 && $progress['graded'] >= 1, 'progres siswa: total, submitted, graded >= 1');

        $list = $a->getByCourse($courseId);
        $item = array_values(array_filter($list, static fn($r) => (int) $r['id'] === $id));
        $this->check($item !== [] && (int) $item[0]['submitted_total'] === 1 && (int) $item[0]['graded_total'] === 1 && (int) $item[0]['student_total'] >= 1, 'getByCourse memuat ringkasan pengumpulan');

        $c1 = $a->countByCourse($courseId);
        $c2 = $a->countByTeacher($teacherId);
        $this->check($c1['published'] >= 1 && $c1['total'] === $c1['published'] + $c1['draft'], 'countByCourse konsisten');
        $this->check($c2['published'] >= 1 && $c2['total'] === $c2['published'] + $c2['draft'], 'countByTeacher konsisten');

        // ---- Unpublish
        $a->update($id, ['is_published' => 0]);
        $row = $a->find($id);
        $this->check($row['published_at'] === null, 'unpublish: published_at NULL');
        $this->check($a->findForStudent($id, $studentId) === null, 'setelah unpublish siswa tidak melihat tugas');
        $a->update($id, ['is_published' => 1]);

        // ---- CASCADE
        $a->delete($id);
        $left = $db->table('submissions')->where('assignment_id', $id)->countAllResults();
        $this->check($left === 0, 'hapus tugas ikut menghapus submissions (CASCADE)');
    }

    private function check(bool $ok, string $text): void
    {
        if (! $ok) {
            $this->failed++;
        }

        CLI::write(($ok ? 'OK    - ' : 'GAGAL - ') . $text, $ok ? 'green' : 'red');
    }
}

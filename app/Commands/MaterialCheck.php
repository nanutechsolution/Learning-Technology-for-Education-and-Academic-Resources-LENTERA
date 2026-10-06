<?php

namespace App\Commands;

use App\Models\MaterialModel;
use App\Models\MaterialViewModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MaterialCheck extends BaseCommand
{
    protected $group       = 'Lentera';
    protected $name        = 'lentera:check-material';
    protected $description = 'Uji cepat MaterialModel dan MaterialViewModel. Sementara, hapus file ini setelah dipakai.';

    public function run(array $params)
    {
        $db   = \Config\Database::connect();
        $pair = $db->table('course_students')->get(1)->getRowArray();

        if (! $pair) {
            CLI::error('Butuh minimal 1 siswa terdaftar di course. Jalankan: php spark db:seed DatabaseSeeder');

            return;
        }

        $courseId  = (int) $pair['course_id'];
        $studentId = (int) $pair['student_id'];
        $course    = $db->table('courses')->where('id', $courseId)->get()->getRowArray();
        $teacherId = (int) $course['teacher_id'];

        $materials = new MaterialModel();
        $views     = new MaterialViewModel();

        $id = $materials->insert([
            'course_id'    => $courseId,
            'title'        => '[TEST] Materi uji',
            'description'  => '',
            'content'      => 'Isi uji',
            'video_url'    => '',
            'is_published' => 1,
        ]);

        if (! $id) {
            CLI::error('Insert gagal: ' . json_encode($materials->errors()));

            return;
        }

        try {
            $row = $materials->find($id);
            $this->check('published_at terisi saat publish', ! empty($row['published_at']));
            $this->check('description kosong menjadi NULL', $row['description'] === null);

            $views->record((int) $id, $studentId);
            $views->record((int) $id, $studentId);
            $count = $db->table('material_views')->where('material_id', $id)->countAllResults();
            $this->check('dua kali buka = satu record material_views', $count === 1);
            $this->check('hasViewed true', $views->hasViewed((int) $id, $studentId));
            $this->check('countViewers = 1', $views->countViewers((int) $id) === 1);

            $stats = $views->getStatistics((int) $id);
            $this->check('statistik: viewed 1, total >= 1, persen > 0', $stats['viewed'] === 1 && $stats['total'] >= 1 && $stats['percent'] > 0);

            $list = $materials->getPublishedByCourse($courseId, $studentId);
            $found = array_values(array_filter($list, static fn($m) => (int) $m['id'] === (int) $id));
            $this->check('getPublishedByCourse memuat viewed_at', $found !== [] && ! empty($found[0]['viewed_at']));

            $this->check('findForStudent (siswa terdaftar) ada', $materials->findForStudent((int) $id, $studentId) !== null);
            $this->check('findOwnedByTeacher (pemilik) ada', $materials->findOwnedByTeacher((int) $id, $teacherId) !== null);
            $this->check('findOwnedByTeacher (guru lain) NULL', $materials->findOwnedByTeacher((int) $id, $teacherId + 999999) === null);
            $this->check('findForStudent (siswa lain) NULL', $materials->findForStudent((int) $id, $studentId + 999999) === null);

            $progress = $views->getProgressByStudent($studentId);
            $this->check('progres siswa: total >= 1, viewed >= 1', $progress['total'] >= 1 && $progress['viewed'] >= 1);

            $counts = $materials->countByTeacher($teacherId);
            $this->check('countByTeacher: published >= 1', $counts['published'] >= 1);

            $first = $materials->find($id)['published_at'];
            $materials->update($id, ['title' => '[TEST] Materi uji (edit)', 'is_published' => 1]);
            $this->check('edit materi published tidak mengubah published_at', $materials->find($id)['published_at'] === $first);

            $materials->update($id, ['is_published' => 0]);
            $this->check('unpublish: published_at NULL', $materials->find($id)['published_at'] === null);
            $this->check('draft tidak terlihat siswa', $materials->findForStudent((int) $id, $studentId) === null);

            $this->check('youtubeId watch', MaterialModel::youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
            $this->check('youtubeId youtu.be', MaterialModel::youtubeId('https://youtu.be/dQw4w9WgXcQ') === 'dQw4w9WgXcQ');
            $this->check('youtubeId menolak non-YouTube', MaterialModel::youtubeId('https://contoh.com/watch?v=dQw4w9WgXcQ') === null);
        } finally {
            $materials->delete($id);
        }

        $left = $db->table('material_views')->where('material_id', $id)->countAllResults();
        $this->check('hapus materi ikut menghapus material_views (CASCADE)', $left === 0);
    }

    private function check(string $label, bool $ok): void
    {
        CLI::write(($ok ? 'OK    ' : 'GAGAL ') . '- ' . $label, $ok ? 'green' : 'red');
    }
}

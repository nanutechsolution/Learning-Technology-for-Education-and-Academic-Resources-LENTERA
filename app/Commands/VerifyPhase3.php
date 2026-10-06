<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class VerifyPhase3 extends BaseCommand
{
    protected $group       = 'Lentera';
    protected $name        = 'lentera:verify3';
    protected $description = 'Memeriksa konsistensi data dan file Phase 3 (tugas, pengumpulan, file upload). Hanya membaca.';

    private int $problems = 0;

    public function run(array $params)
    {
        helper('assignment_file');

        $db = \Config\Database::connect();

        CLI::write('LENTERA - verifikasi Phase 3', 'yellow');
        CLI::newLine();

        // 1. Lingkungan PHP
        $this->section('Lingkungan PHP');
        $this->check(extension_loaded('fileinfo'), 'Ekstensi fileinfo aktif', 'Ekstensi fileinfo TIDAK aktif; upload akan ditolak.');

        $upload = $this->toBytes((string) ini_get('upload_max_filesize'));
        $post   = $this->toBytes((string) ini_get('post_max_size'));
        $this->check($upload >= 10485760, 'upload_max_filesize >= 10M (' . ini_get('upload_max_filesize') . ')', 'upload_max_filesize kurang dari 10M (' . ini_get('upload_max_filesize') . ').');
        $this->check($post >= 12582912, 'post_max_size >= 12M (' . ini_get('post_max_size') . ')', 'post_max_size kurang dari 12M (' . ini_get('post_max_size') . ').');

        // 2. Folder upload
        $this->section('Folder upload');
        $dirs = ['assignments' => assignment_dir(), 'submissions' => submission_dir()];

        foreach ($dirs as $label => $dir) {
            $exists = is_dir($dir);
            $this->check($exists, "Folder {$label} ada", "Folder {$label} tidak ada: {$dir}");

            if ($exists) {
                $this->check(is_writable($dir), "Folder {$label} dapat ditulis", "Folder {$label} tidak dapat ditulis.");

                $real = realpath($dir);
                $pub  = realpath(FCPATH);
                $this->check(
                    $real !== false && $pub !== false && ! str_starts_with($real, $pub),
                    "Folder {$label} berada di luar public/",
                    "Folder {$label} berada di dalam public/ sehingga file bisa diakses langsung!"
                );
            }
        }

        // 3. File di disk vs rujukan di DB
        $this->section('File di disk vs database');

        $attachments = array_column(
            $db->table('assignments')->select('attachment_path')->where('attachment_path IS NOT NULL', null, false)->get()->getResultArray(),
            'attachment_path'
        );
        $answers = array_column(
            $db->table('submissions')->select('file_path')->where('file_path IS NOT NULL', null, false)->get()->getResultArray(),
            'file_path'
        );

        $this->compareDir('assignments', assignment_dir(), $attachments);
        $this->compareDir('submissions', submission_dir(), $answers);

        $unsafe = [];
        foreach (array_merge($attachments, $answers) as $name) {
            if (! preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/', (string) $name)) {
                $unsafe[] = (string) $name;
            }
        }
        $this->check($unsafe === [], 'Semua nama file di DB berformat acak yang aman', 'Nama file tidak aman/tidak sesuai format: ' . implode(', ', array_slice($unsafe, 0, 5)));

        $partial = $db->table('assignments')
            ->where('attachment_path IS NOT NULL', null, false)
            ->groupStart()->where('attachment_name IS NULL', null, false)->orWhere('attachment_size IS NULL', null, false)->groupEnd()
            ->countAllResults();
        $this->check($partial === 0, 'Kolom attachment_* konsisten', "{$partial} tugas punya attachment_path tetapi nama/ukuran kosong.");

        // 4. Konsistensi data pengumpulan
        $this->section('Konsistensi pengumpulan');

        $dups = $db->table('submissions')
            ->select('assignment_id, student_id, COUNT(*) AS c')
            ->groupBy('assignment_id, student_id')
            ->having('COUNT(*) >', 1)
            ->get()->getResultArray();
        $this->check($dups === [], 'Tidak ada pengumpulan ganda per siswa dan tugas', count($dups) . ' pasangan (tugas, siswa) punya lebih dari satu pengumpulan.');

        $badScore = $db->table('submissions')
            ->where('score IS NOT NULL', null, false)
            ->groupStart()->where('score <', 0)->orWhere('score >', 100)->groupEnd()
            ->countAllResults();
        $this->check($badScore === 0, 'Semua nilai berada di rentang 0-100', "{$badScore} pengumpulan bernilai di luar 0-100.");

        $gradedNoSubmit = $db->table('submissions')
            ->where('graded_at IS NOT NULL', null, false)
            ->where('submitted_at IS NULL', null, false)
            ->countAllResults();
        $this->check($gradedNoSubmit === 0, 'Tidak ada nilai pada pengumpulan yang belum dikirim', "{$gradedNoSubmit} pengumpulan dinilai padahal belum dikirim.");

        $gradedNoScore = $db->table('submissions')
            ->where('graded_at IS NOT NULL', null, false)
            ->where('score IS NULL', null, false)
            ->countAllResults();
        $this->check($gradedNoScore === 0, 'Semua pengumpulan yang dinilai punya nilai', "{$gradedNoScore} pengumpulan bertanda dinilai tetapi nilainya kosong.");

        // Informasi saja: siswa yang dikeluarkan dari course setelah mengumpulkan.
        $notEnrolled = $db->table('submissions sub')
            ->join('assignments a', 'a.id = sub.assignment_id')
            ->join('course_students cs', 'cs.course_id = a.course_id AND cs.student_id = sub.student_id', 'left')
            ->where('cs.student_id IS NULL', null, false)
            ->where('sub.submitted_at IS NOT NULL', null, false)
            ->countAllResults();
        CLI::write("  [info] {$notEnrolled} pengumpulan berasal dari siswa yang kini tidak terdaftar di course (wajar jika siswa pernah dikeluarkan).", 'light_gray');

        CLI::newLine();

        if ($this->problems === 0) {
            CLI::write('Semua pemeriksaan lolos.', 'green');

            return EXIT_SUCCESS;
        }

        CLI::write($this->problems . ' masalah ditemukan.', 'red');

        return EXIT_ERROR;
    }

    private function section(string $title): void
    {
        CLI::newLine();
        CLI::write($title, 'cyan');
    }

    private function check(bool $ok, string $passMsg, string $failMsg): void
    {
        if ($ok) {
            CLI::write('  [OK]    ' . $passMsg, 'green');

            return;
        }

        $this->problems++;
        CLI::write('  [GAGAL] ' . $failMsg, 'red');
    }

    /**
     * Bandingkan isi folder dengan daftar nama file yang dirujuk DB.
     */
    private function compareDir(string $label, string $dir, array $referenced): void
    {
        $onDisk = [];

        if (is_dir($dir)) {
            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..' || $f === '.gitkeep' || $f === 'index.html' || is_dir($dir . $f)) {
                    continue;
                }
                $onDisk[] = $f;
            }
        }

        $orphans = array_values(array_diff($onDisk, $referenced));
        $missing = array_values(array_diff($referenced, $onDisk));

        $this->check(
            $orphans === [],
            "Tidak ada file yatim di {$label} (" . count($onDisk) . ' file di disk)',
            count($orphans) . " file yatim di {$label} (ada di disk, tidak dirujuk DB): " . implode(', ', array_slice($orphans, 0, 5)) . (count($orphans) > 5 ? ', ...' : '')
        );

        $this->check(
            $missing === [],
            "Semua rujukan DB di {$label} punya file di disk (" . count($referenced) . ' rujukan)',
            count($missing) . " rujukan DB di {$label} tanpa file di disk: " . implode(', ', array_slice($missing, 0, 5)) . (count($missing) > 5 ? ', ...' : '')
        );
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $num  = (int) $value;

        return match ($unit) {
            'g'     => $num * 1073741824,
            'm'     => $num * 1048576,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}
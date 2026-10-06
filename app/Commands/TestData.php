<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;

/**
 * SEMENTARA: data uji Phase 2. Hapus file ini setelah STEP 2G selesai.
 *
 * php spark lentera:test-data setup
 * php spark lentera:test-data verify
 * php spark lentera:test-data cleanup
 * php spark lentera:test-data id "<judul materi>"
 * php spark lentera:test-data info <id materi>
 * php spark lentera:test-data exists <nama file>
 */
class TestData extends BaseCommand
{
    protected $group       = 'LENTERA';
    protected $name        = 'lentera:test-data';
    protected $description = 'SEMENTARA: data uji Phase 2 (setup, verify, cleanup, id, info, exists).';
    protected $usage       = 'lentera:test-data <setup|verify|cleanup|id|info|exists> [argumen]';
    protected $arguments   = [
        'aksi'     => 'setup | verify | cleanup | id | info | exists',
        'argumen'  => 'Judul materi (id), ID materi (info), atau nama file (exists)',
    ];

    private const TEST_PASSWORD = 'Lentera@123';

    public function run(array $params)
    {
        helper('material_file');

        $action = $params[0] ?? '';
        $arg    = $params[1] ?? '';

        switch ($action) {
            case 'setup':
                $this->setup();
                break;
            case 'verify':
                $this->verify();
                break;
            case 'cleanup':
                $this->cleanup();
                break;
            case 'id':
                $this->findId($arg);
                break;
            case 'info':
                $this->info((int) $arg);
                break;
            case 'exists':
                CLI::write('EXISTS=' . (($arg !== '' && material_file_path($arg) !== null) ? '1' : '0'));
                break;
            default:
                CLI::error('Aksi tidak dikenal. Gunakan: setup | verify | cleanup | id | info | exists');
        }
    }

    // ------------------------------------------------------------------

    private function setup(): void
    {
        $db = \Config\Database::connect();

        $guruDemo  = $db->table('users')->where('username', 'guru.demo')->get()->getRowArray();
        $siswaDemo = $db->table('users')->where('username', 'siswa.demo')->get()->getRowArray();

        if (! $guruDemo || ! $siswaDemo) {
            CLI::error('Akun guru.demo / siswa.demo tidak ditemukan. Jalankan: php spark db:seed DatabaseSeeder');

            return;
        }

        $teacherDemo = $db->table('teachers')->where('user_id', $guruDemo['id'])->get()->getRowArray();
        $studentDemo = $db->table('students')->where('user_id', $siswaDemo['id'])->get()->getRowArray();

        if (! $teacherDemo || ! $studentDemo) {
            CLI::error('guru.demo / siswa.demo belum punya profil teachers / students.');

            return;
        }

        $courseA = $db->table('courses')
            ->where('teacher_id', $teacherDemo['id'])
            ->where('title', 'Matematika VII A')
            ->get()->getRowArray();

        if (! $courseA) {
            $courseA = $db->table('courses')->where('teacher_id', $teacherDemo['id'])->orderBy('id', 'ASC')->get()->getRowArray();
        }

        if (! $courseA) {
            CLI::error('guru.demo belum punya course.');

            return;
        }

        $enrolled = $db->table('course_students')
            ->where('course_id', $courseA['id'])
            ->where('student_id', $studentDemo['id'])
            ->countAllResults();

        if ($enrolled < 1) {
            CLI::error('siswa.demo belum terdaftar di course "' . $courseA['title'] . '" (id ' . $courseA['id'] . '). Daftarkan lewat admin/courses/' . $courseA['id'] . '/students.');

            return;
        }

        // Guru B dan siswa B
        $guruBUser  = $this->ensureUser($db, 'guru.b', 'Guru Uji B', 'guru');
        $siswaBUser = $this->ensureUser($db, 'siswa.b', 'Siswa Uji B', 'siswa');
        $now        = date('Y-m-d H:i:s');

        $teacherB = $db->table('teachers')->where('user_id', $guruBUser)->get()->getRowArray();
        if (! $teacherB) {
            $db->table('teachers')->insert([
                'user_id' => $guruBUser,
                'nip' => null,
                'phone' => '-',
                'address' => '-',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $teacherBId = (int) $db->insertID();
        } else {
            $teacherBId = (int) $teacherB['id'];
        }

        $studentB = $db->table('students')->where('user_id', $siswaBUser)->get()->getRowArray();
        if (! $studentB) {
            $db->table('students')->insert([
                'user_id' => $siswaBUser,
                'nis' => 'UJI-B-001',
                'nisn' => null,
                'class_id' => $courseA['class_id'],
                'phone' => '-',
                'address' => '-',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $studentBId = (int) $db->insertID();
        } else {
            $studentBId = (int) $studentB['id'];
        }

        // Course uji
        $courseB   = $this->ensureCourse($db, '[UJI] Course Guru B', $teacherBId, $courseA);
        $courseDel = $this->ensureCourse($db, '[UJI] Course Hapus', (int) $teacherDemo['id'], $courseA);

        $already = $db->table('course_students')->where('course_id', $courseB)->where('student_id', $studentBId)->countAllResults();
        if ($already < 1) {
            $db->table('course_students')->insert([
                'course_id' => $courseB,
                'student_id' => $studentBId,
                'enrolled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Materi uji
        $ids = [
            'courseA'   => (int) $courseA['id'],
            'courseB'   => $courseB,
            'courseDel' => $courseDel,
            'matAPub'   => $this->ensureMaterial($db, '[UJI] A Publish (file)', (int) $courseA['id'], ['is_published' => 1], true),
            'matADraft' => $this->ensureMaterial($db, '[UJI] A Draft', (int) $courseA['id'], ['is_published' => 0], false),
            'matBPub'   => $this->ensureMaterial($db, '[UJI] B Publish (file)', $courseB, ['is_published' => 1], true),
            'matDel'    => $this->ensureMaterial($db, '[UJI] Hapus Course (file)', $courseDel, ['is_published' => 1], true),
            'matXss'    => $this->ensureMaterial($db, '[UJI] <script>alert(1)</script>', (int) $courseA['id'], [
                'is_published' => 1,
                'description'  => '<i>desc</i>',
                'content'      => "<img src=x onerror=alert(1)>\n<b>tebal</b>",
            ], false),
        ];

        file_put_contents(WRITEPATH . 'test-ids.json', json_encode($ids, JSON_PRETTY_PRINT));

        CLI::write('Data uji siap. ID tersimpan di writable/test-ids.json', 'green');
        foreach ($ids as $k => $v) {
            CLI::write(str_pad($k, 10) . ' = ' . $v);
        }
        CLI::write('Akun tambahan: guru.b dan siswa.b, password ' . self::TEST_PASSWORD);
    }

    private function ensureUser(BaseConnection $db, string $username, string $name, string $role): int
    {
        $row = $db->table('users')->where('username', $username)->get()->getRowArray();

        if ($row) {
            return (int) $row['id'];
        }

        $now = date('Y-m-d H:i:s');

        $db->table('users')->insert([
            'name'       => $name,
            'username'   => $username,
            'email'      => null,
            'password'   => password_hash(self::TEST_PASSWORD, PASSWORD_DEFAULT),
            'role'       => $role,
            'is_active'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->insertID();
    }

    private function ensureCourse(BaseConnection $db, string $title, int $teacherId, array $template): int
    {
        $row = $db->table('courses')->where('title', $title)->get()->getRowArray();

        if ($row) {
            return (int) $row['id'];
        }

        $now = date('Y-m-d H:i:s');

        $db->table('courses')->insert([
            'subject_id'       => $template['subject_id'],
            'teacher_id'       => $teacherId,
            'class_id'         => $template['class_id'],
            'academic_year_id' => $template['academic_year_id'],
            'title'            => $title,
            'description'      => 'Data uji sementara.',
            'status'           => 'active',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) $db->insertID();
    }

    private function ensureMaterial(BaseConnection $db, string $title, int $courseId, array $extra, bool $withFile): int
    {
        $row = $db->table('materials')->where('title', $title)->where('course_id', $courseId)->get()->getRowArray();

        if ($row) {
            if ($withFile && (empty($row['file_path']) || material_file_path($row['file_path']) === null)) {
                $name = $this->makeFile();
                $db->table('materials')->where('id', $row['id'])->update([
                    'file_path' => $name,
                    'file_name' => 'uji.pdf',
                    'file_size' => 40,
                    'file_type' => 'application/pdf',
                ]);
            }

            return (int) $row['id'];
        }

        $now       = date('Y-m-d H:i:s');
        $published = (int) ($extra['is_published'] ?? 0) === 1;

        $data = array_merge([
            'course_id'    => $courseId,
            'title'        => $title,
            'description'  => null,
            'content'      => 'Isi materi uji.',
            'file_path'    => null,
            'file_name'    => null,
            'file_size'    => null,
            'file_type'    => null,
            'video_url'    => null,
            'published_at' => $published ? $now : null,
            'is_published' => 0,
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $extra);

        if ($withFile) {
            $data['file_path'] = $this->makeFile();
            $data['file_name'] = 'uji.pdf';
            $data['file_size'] = 40;
            $data['file_type'] = 'application/pdf';
        }

        $db->table('materials')->insert($data);

        return (int) $db->insertID();
    }

    private function makeFile(): string
    {
        $dir = material_dir();

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = bin2hex(random_bytes(16)) . '.pdf';
        file_put_contents($dir . $name, "%PDF-1.4\n% LENTERA UJI\n%%EOF\n");

        return $name;
    }

    // ------------------------------------------------------------------

    private function verify(): void
    {
        $db  = \Config\Database::connect();
        $dir = material_dir();

        $dup = (int) $db->query(
            'SELECT COUNT(*) AS n FROM (SELECT 1 FROM material_views GROUP BY material_id, student_id HAVING COUNT(*) > 1) t'
        )->getRowArray()['n'];
        $this->line($dup === 0, 'material_views tanpa duplikat (siswa + materi)');

        $files = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if (! in_array($f, ['.', '..', '.gitkeep', 'index.html'], true) && is_file($dir . $f)) {
                    $files[] = $f;
                }
            }
        }

        $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
        $bad     = array_filter($files, static fn($f) => ! in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), $allowed, true));
        $this->line($bad === [], 'tidak ada file berekstensi terlarang di writable/uploads/materials' . ($bad ? ' -> ' . implode(', ', $bad) : ''));

        $rows     = $db->table('materials')->select('file_path')->where('file_path IS NOT NULL', null, false)->get()->getResultArray();
        $inDb     = array_column($rows, 'file_path');
        $orphans  = array_diff($files, $inDb);
        $this->line($orphans === [], 'tidak ada file yatim di disk' . ($orphans ? ' -> ' . implode(', ', $orphans) : ''));

        $missing = array_filter($inDb, static fn($f) => ! is_file($dir . basename((string) $f)));
        $this->line($missing === [], 'semua file_path di DB ada di disk' . ($missing ? ' -> ' . implode(', ', $missing) : ''));

        $names = array_filter($inDb, static fn($f) => ! preg_match('/^[0-9a-f]{32}\.[a-z0-9]+$/', (string) $f));
        $this->line($names === [], 'semua file_path berupa nama acak (bukan nama asli)' . ($names ? ' -> ' . implode(', ', $names) : ''));
    }

    private function line(bool $ok, string $text): void
    {
        CLI::write(($ok ? 'OK    - ' : 'GAGAL - ') . $text, $ok ? 'green' : 'red');
    }

    // ------------------------------------------------------------------

    private function cleanup(): void
    {
        $db = \Config\Database::connect();

        $ujiCourses = array_map('intval', array_column(
            $db->table('courses')->select('id')->like('title', '[UJI]', 'after')->get()->getResultArray(),
            'id'
        ));

        $files = array_column(
            $db->table('materials')->select('file_path')->like('title', '[UJI]', 'after')
                ->where('file_path IS NOT NULL', null, false)->get()->getResultArray(),
            'file_path'
        );

        if ($ujiCourses !== []) {
            $files = array_merge($files, array_column(
                $db->table('materials')->select('file_path')->whereIn('course_id', $ujiCourses)
                    ->where('file_path IS NOT NULL', null, false)->get()->getResultArray(),
                'file_path'
            ));
        }

        $db->table('materials')->like('title', '[UJI]', 'after')->delete();
        $db->table('courses')->like('title', '[UJI]', 'after')->delete();
        $db->table('users')->whereIn('username', ['guru.b', 'siswa.b'])->delete();

        foreach (array_unique($files) as $name) {
            material_delete_file($name);
        }

        $ids = WRITEPATH . 'test-ids.json';
        if (is_file($ids)) {
            unlink($ids);
        }

        CLI::write('Data uji dibersihkan (materi, course, guru.b, siswa.b, file, test-ids.json).', 'green');
    }

    private function findId(string $title): void
    {
        $row = \Config\Database::connect()->table('materials')->select('id')
            ->where('title', $title)->orderBy('id', 'DESC')->get(1)->getRowArray();

        CLI::write('ID=' . ($row ? $row['id'] : 'NONE'));
    }

    private function info(int $id): void
    {
        $row = \Config\Database::connect()->table('materials')->select('file_path')->where('id', $id)->get()->getRowArray();
        $name = $row['file_path'] ?? null;

        CLI::write('FILE=' . ($name ?: 'NONE'));
        CLI::write('EXISTS=' . (($name && material_file_path($name) !== null) ? '1' : '0'));
    }
}

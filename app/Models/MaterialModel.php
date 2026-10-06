<?php

namespace App\Models;

use CodeIgniter\Model;

class MaterialModel extends Model
{
    protected $table            = 'materials';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'course_id',
        'title',
        'description',
        'content',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'video_url',
        'published_at',
        'is_published',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'course_id'    => 'required|is_natural_no_zero|is_not_unique[courses.id]',
        'title'        => 'required|max_length[255]',
        'video_url'    => 'permit_empty|max_length[500]|valid_url_strict[http,https]',
        'is_published' => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'course_id' => [
            'required'          => 'Course wajib dipilih.',
            'is_natural_no_zero' => 'Course tidak valid.',
            'is_not_unique'     => 'Course tidak ditemukan.',
        ],
        'title' => [
            'required'   => 'Judul materi wajib diisi.',
            'max_length' => 'Judul materi maksimal 255 karakter.',
        ],
        'video_url' => [
            'max_length'       => 'Video URL maksimal 500 karakter.',
            'valid_url_strict' => 'Video URL harus berupa URL yang valid.',
        ],
        'is_published' => [
            'in_list' => 'Status materi tidak valid.',
        ],
    ];

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['prepare'];
    protected $beforeUpdate   = ['prepare'];

    /**
     * Kolom opsional kosong menjadi NULL dan atur published_at:
     * - dipublish pertama kali: published_at = sekarang
     * - sudah pernah dipublish dan tetap published: published_at tidak diubah
     * - di-unpublish: published_at = NULL
     */
    protected function prepare(array $eventData): array
    {
        $data = $eventData['data'];

        foreach (['description', 'content', 'video_url', 'file_path', 'file_name', 'file_type'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field]) && trim($data[$field]) === '') {
                $data[$field] = null;
            }
        }

        if (array_key_exists('file_size', $data) && ($data['file_size'] === '' || $data['file_size'] === null)) {
            $data['file_size'] = null;
        }

        if (array_key_exists('is_published', $data)) {
            $data['is_published'] = (int) $data['is_published'] === 1 ? 1 : 0;

            if ($data['is_published'] === 0) {
                $data['published_at'] = null;
            } else {
                $id = $eventData['id'] ?? null;

                if (is_array($id)) {
                    $id = count($id) === 1 ? reset($id) : null;
                }

                $existing = null;

                if ($id !== null) {
                    $existing = $this->db->table($this->table)
                        ->select('published_at')
                        ->where('id', (int) $id)
                        ->get()
                        ->getRowArray();
                }

                if ($existing !== null && ! empty($existing['published_at'])) {
                    unset($data['published_at']);
                } else {
                    $data['published_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        $eventData['data'] = $data;

        return $eventData;
    }

    /**
     * Minimal salah satu dari isi materi, file, atau video harus ada.
     * $hasFile = true jika ada file baru diunggah atau file lama dipertahankan.
     */
    public function hasContent(array $data, bool $hasFile = false): bool
    {
        foreach (['content', 'video_url'] as $field) {
            if (isset($data[$field]) && trim((string) $data[$field]) !== '') {
                return true;
            }
        }

        return $hasFile;
    }

    /**
     * Ambil ID video YouTube (11 karakter) dari URL watch, youtu.be, embed, shorts, atau live.
     * Return null jika bukan URL YouTube yang valid.
     */
    public static function youtubeId(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) preg_replace('/^(www\.|m\.)/i', '', $parts['host']));
        $path = $parts['path'] ?? '';
        $id   = null;

        if ($host === 'youtu.be') {
            $segments = explode('/', trim($path, '/'));
            $id       = $segments[0] ?? null;
        } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('#^/(?:embed|shorts|live)/([^/?]+)#', $path, $m) === 1) {
                $id = $m[1];
            }
        }

        return ($id !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1) ? $id : null;
    }

    /**
     * Daftar materi satu course untuk guru (terbaru di atas).
     * view_count = siswa terdaftar yang sudah membuka, student_total = siswa terdaftar di course.
     */
    public function getByCourse(int $courseId): array
    {
        return $this->select(
            'materials.*,'
                . ' (SELECT COUNT(*) FROM material_views mv'
                . '   INNER JOIN course_students cs ON cs.student_id = mv.student_id AND cs.course_id = materials.course_id'
                . '   WHERE mv.material_id = materials.id) AS view_count,'
                . ' (SELECT COUNT(*) FROM course_students cs2 WHERE cs2.course_id = materials.course_id) AS student_total',
            false
        )
            ->where('materials.course_id', $courseId)
            ->orderBy('materials.id', 'DESC')
            ->findAll();
    }

    /**
     * Daftar materi published satu course (urut sesuai pembuatan).
     * Jika $studentId diisi, tiap baris memuat viewed_at (NULL = belum dibuka).
     */
    public function getPublishedByCourse(int $courseId, ?int $studentId = null): array
    {
        $builder = $this->select('materials.id, materials.course_id, materials.title, materials.description, materials.published_at, materials.file_name, materials.video_url')
            ->where('materials.course_id', $courseId)
            ->where('materials.is_published', 1)
            ->orderBy('materials.published_at', 'ASC')
            ->orderBy('materials.id', 'ASC');

        if ($studentId !== null) {
            $builder->select('mv.viewed_at AS viewed_at')
                ->join('material_views mv', 'mv.material_id = materials.id AND mv.student_id = ' . (int) $studentId, 'left');
        }

        return $builder->findAll();
    }

    /**
     * Materi beserta course dan mapel, tanpa pembatasan pemilik (untuk admin).
     */
    public function findWithCourse(int $id): ?array
    {
        $row = $this->select('materials.*, courses.title AS course_title, courses.teacher_id AS course_teacher_id, subjects.name AS subject_name')
            ->join('courses', 'courses.id = materials.course_id')
            ->join('subjects', 'subjects.id = courses.subject_id')
            ->where('materials.id', $id)
            ->first();

        return $row ?: null;
    }

    /**
     * Materi milik guru (lewat courses.teacher_id). NULL jika bukan miliknya atau tidak ada.
     */
    public function findOwnedByTeacher(int $id, int $teacherId): ?array
    {
        $row = $this->select('materials.*, courses.title AS course_title, courses.teacher_id AS course_teacher_id, subjects.name AS subject_name')
            ->join('courses', 'courses.id = materials.course_id')
            ->join('subjects', 'subjects.id = courses.subject_id')
            ->where('materials.id', $id)
            ->where('courses.teacher_id', $teacherId)
            ->first();

        return $row ?: null;
    }

    /**
     * Materi published pada course yang diikuti siswa. NULL jika draft, tidak ada, atau siswa tidak terdaftar.
     */
    public function findForStudent(int $id, int $studentId): ?array
    {
        $row = $this->select('materials.*, courses.title AS course_title, subjects.name AS subject_name')
            ->join('courses', 'courses.id = materials.course_id')
            ->join('subjects', 'subjects.id = courses.subject_id')
            ->join('course_students cs', 'cs.course_id = materials.course_id')
            ->where('materials.id', $id)
            ->where('materials.is_published', 1)
            ->where('cs.student_id', $studentId)
            ->first();

        return $row ?: null;
    }

    /**
     * Jumlah materi satu course: total, published, draft.
     */
    public function countByCourse(int $courseId): array
    {
        $row = $this->builder()
            ->select('COUNT(materials.id) AS total, COALESCE(SUM(materials.is_published = 1), 0) AS published', false)
            ->where('materials.course_id', $courseId)
            ->get()
            ->getRowArray();

        return $this->formatCounts($row);
    }

    /**
     * Jumlah materi seluruh course milik guru: total, published, draft.
     */
    public function countByTeacher(int $teacherId): array
    {
        $row = $this->builder()
            ->select('COUNT(materials.id) AS total, COALESCE(SUM(materials.is_published = 1), 0) AS published', false)
            ->join('courses', 'courses.id = materials.course_id')
            ->where('courses.teacher_id', $teacherId)
            ->get()
            ->getRowArray();

        return $this->formatCounts($row);
    }

    private function formatCounts(?array $row): array
    {
        $total     = (int) ($row['total'] ?? 0);
        $published = (int) ($row['published'] ?? 0);

        return [
            'total'     => $total,
            'published' => $published,
            'draft'     => $total - $published,
        ];
    }
}

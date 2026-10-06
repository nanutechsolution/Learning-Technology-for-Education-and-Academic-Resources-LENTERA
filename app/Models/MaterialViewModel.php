<?php

namespace App\Models;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

class MaterialViewModel extends Model
{
    protected $table            = 'material_views';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['material_id', 'student_id', 'viewed_at'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Data diisi internal oleh sistem, bukan input pengguna.
    protected $skipValidation = true;

    /**
     * Catat siswa membuka materi.
     * Satu siswa + satu materi = satu record; jika sudah ada, hanya viewed_at yang diperbarui.
     */
    public function record(int $materialId, int $studentId): bool
    {
        $now = date('Y-m-d H:i:s');

        $existing = $this->where('material_id', $materialId)->where('student_id', $studentId)->first();

        if ($existing) {
            return (bool) $this->update((int) $existing['id'], ['viewed_at' => $now]);
        }

        try {
            return (bool) $this->insert([
                'material_id' => $materialId,
                'student_id'  => $studentId,
                'viewed_at'   => $now,
            ]);
        } catch (DatabaseException $e) {
            // Balapan dua request bersamaan: unique key menolak, jadi cukup perbarui.
            $existing = $this->where('material_id', $materialId)->where('student_id', $studentId)->first();

            return $existing
                ? (bool) $this->update((int) $existing['id'], ['viewed_at' => $now])
                : false;
        }
    }

    public function hasViewed(int $materialId, int $studentId): bool
    {
        return $this->builder()
            ->where('material_id', $materialId)
            ->where('student_id', $studentId)
            ->countAllResults() > 0;
    }

    /**
     * Jumlah siswa yang sudah membuka materi (hanya siswa yang masih terdaftar di course).
     */
    public function countViewers(int $materialId): int
    {
        return (int) $this->db->table('material_views mv')
            ->join('materials m', 'm.id = mv.material_id')
            ->join('course_students cs', 'cs.course_id = m.course_id AND cs.student_id = mv.student_id')
            ->where('mv.material_id', $materialId)
            ->countAllResults();
    }

    /**
     * Statistik akses satu materi: viewed, total siswa course, percent (2 desimal).
     */
    public function getStatistics(int $materialId): array
    {
        $total = (int) $this->db->table('course_students cs')
            ->join('materials m', 'm.course_id = cs.course_id')
            ->where('m.id', $materialId)
            ->countAllResults();

        $viewed  = $this->countViewers($materialId);
        $percent = $total > 0 ? round($viewed / $total * 100, 2) : 0.0;

        return [
            'viewed'  => $viewed,
            'total'   => $total,
            'percent' => $percent,
        ];
    }

    /**
     * Semua siswa terdaftar di course materi ini beserta waktu buka (viewed_at NULL = belum dibuka).
     */
    public function getStudentStatus(int $materialId): array
    {
        return $this->db->table('course_students cs')
            ->select('s.id AS student_id, s.nis, u.name, c.name AS class_name, mv.viewed_at')
            ->join('materials m', 'm.course_id = cs.course_id')
            ->join('students s', 's.id = cs.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('classes c', 'c.id = s.class_id', 'left')
            ->join('material_views mv', 'mv.material_id = m.id AND mv.student_id = cs.student_id', 'left')
            ->where('m.id', $materialId)
            ->orderBy('u.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Progres materi siswa: hanya materi published dari course yang diikuti.
     */
    public function getProgressByStudent(int $studentId): array
    {
        $total = (int) $this->db->table('materials m')
            ->join('course_students cs', 'cs.course_id = m.course_id')
            ->where('cs.student_id', $studentId)
            ->where('m.is_published', 1)
            ->countAllResults();

        $viewed = (int) $this->db->table('material_views mv')
            ->join('materials m', 'm.id = mv.material_id')
            ->join('course_students cs', 'cs.course_id = m.course_id AND cs.student_id = mv.student_id')
            ->where('mv.student_id', $studentId)
            ->where('m.is_published', 1)
            ->countAllResults();

        return [
            'total'   => $total,
            'viewed'  => $viewed,
            'percent' => $total > 0 ? round($viewed / $total * 100, 2) : 0.0,
        ];
    }
}

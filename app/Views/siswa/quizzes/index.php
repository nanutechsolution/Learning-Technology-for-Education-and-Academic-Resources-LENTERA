<?php
$fmt = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));

$stateLabel = [
    'submitted'   => ['class' => 'success',           'text' => 'Selesai'],
    'in_progress' => ['class' => 'warning text-dark', 'text' => 'Sedang dikerjakan'],
    'open'        => ['class' => 'primary',           'text' => 'Dapat dikerjakan'],
    'upcoming'    => ['class' => 'info',              'text' => 'Belum dibuka'],
    'closed'      => ['class' => 'dark',              'text' => 'Ditutup'],
];

$buttonLabel = [
    'submitted'   => 'Lihat Hasil',
    'in_progress' => 'Lanjutkan',
    'open'        => 'Buka',
    'upcoming'    => 'Detail',
    'closed'      => 'Detail',
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<a href="<?= site_url('siswa/courses/' . (int) $course['id']) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke Course
</a>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<p class="text-muted">
    <?= esc($course['subject_name'] ?? '-') ?> &middot; <?= esc($course['teacher_name'] ?? '-') ?>
    &middot; Kelas <?= esc($course['class_name'] ?? '-') ?>
</p>

<?php if (empty($quizzes)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">Belum ada quiz pada course ini.</div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Quiz</th>
                        <th>Jadwal</th>
                        <th class="text-center">Durasi</th>
                        <th class="text-center">Soal</th>
                        <th>Status</th>
                        <th class="text-center">Nilai</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizzes as $q): ?>
                        <?php $st = $stateLabel[$q['state']] ?? $stateLabel['open']; ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('siswa/quizzes/' . (int) $q['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($q['title']) ?>
                                </a>
                            </td>
                            <td class="small text-nowrap">
                                <?php if (empty($q['start_at']) && empty($q['end_at'])): ?>
                                    <span class="text-muted">Tanpa jadwal</span>
                                <?php else: ?>
                                    <div>Mulai: <?= esc($fmt($q['start_at'])) ?></div>
                                    <div>Berakhir: <?= esc($fmt($q['end_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <?= empty($q['duration_minutes']) ? '<span class="text-muted">-</span>' : (int) $q['duration_minutes'] . ' menit' ?>
                            </td>
                            <td class="text-center text-nowrap">
                                <?= (int) $q['question_count'] ?> soal
                                <div class="small text-muted"><?= (int) $q['total_points'] ?> poin</div>
                            </td>
                            <td><span class="badge text-bg-<?= $st['class'] ?>"><?= esc($st['text']) ?></span></td>
                            <td class="text-center">
                                <?= ($q['state'] === 'submitted' && $q['attempt_score'] !== null) ? esc(number_format((float) $q['attempt_score'], 2, ',', '.')) : '-' ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('siswa/quizzes/' . (int) $q['id']) ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-1"></i><?= esc($buttonLabel[$q['state']] ?? 'Buka') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
<?php
$fmt = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));

$stateLabel = [
    'graded'    => ['class' => 'success',            'text' => 'Sudah dinilai'],
    'submitted' => ['class' => 'info',               'text' => 'Sudah dikumpulkan'],
    'closed'    => ['class' => 'danger',             'text' => 'Ditutup (lewat batas)'],
    'open'      => ['class' => 'warning text-dark',  'text' => 'Belum dikumpulkan'],
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

<?php if (empty($assignments)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">Belum ada tugas pada course ini.</div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tugas</th>
                        <th>Batas Waktu</th>
                        <th>Status</th>
                        <th class="text-center">Nilai</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $a): ?>
                        <?php $st = $stateLabel[$a['state']] ?? $stateLabel['open']; ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('siswa/assignments/' . (int) $a['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($a['title']) ?>
                                </a>
                            </td>
                            <td class="text-nowrap">
                                <?php if (empty($a['due_at'])): ?>
                                    <span class="text-muted">Tanpa batas</span>
                                <?php else: ?>
                                    <?= esc($fmt($a['due_at'])) ?>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-<?= $st['class'] ?>"><?= esc($st['text']) ?></span></td>
                            <td class="text-center">
                                <?= (! empty($a['graded_at']) && $a['score'] !== null) ? esc(number_format((float) $a['score'], 2, ',', '.')) : '-' ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('siswa/assignments/' . (int) $a['id']) ?>" class="btn btn-sm btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Buka
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
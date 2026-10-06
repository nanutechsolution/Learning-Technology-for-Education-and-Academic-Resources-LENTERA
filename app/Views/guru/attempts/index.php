<?php
$fmt = static fn ($d): string => empty($d) ? '-' : date('d/m/Y H:i', strtotime((string) $d));
$num = static fn ($v): string => $v === null ? '-' : number_format((float) $v, 2, ',', '.');

$cards = [
    ['label' => 'Siswa Terdaftar',   'value' => (string) (int) $stats['total'],       'color' => 'primary',   'icon' => 'bi-people'],
    ['label' => 'Sudah Selesai',     'value' => (string) (int) $stats['submitted'],   'color' => 'success',   'icon' => 'bi-check2-circle'],
    ['label' => 'Sedang Mengerjakan', 'value' => (string) (int) $stats['in_progress'], 'color' => 'warning',   'icon' => 'bi-hourglass-split'],
    ['label' => 'Belum Mulai',       'value' => (string) (int) $stats['not_started'], 'color' => 'secondary', 'icon' => 'bi-dash-circle'],
    ['label' => 'Rata-rata Nilai',   'value' => $num($stats['average']),              'color' => 'info',      'icon' => 'bi-bar-chart'],
    ['label' => 'Tertinggi / Terendah', 'value' => $num($stats['highest']) . ' / ' . $num($stats['lowest']), 'color' => 'danger', 'icon' => 'bi-arrow-down-up'],
];

$duration = static function ($start, $end): string {
    if (empty($start) || empty($end)) {
        return '-';
    }

    $seconds = max(strtotime((string) $end) - strtotime((string) $start), 0);
    $minutes = intdiv($seconds, 60);

    return $minutes . ' mnt ' . ($seconds % 60) . ' dtk';
};
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<div class="d-flex flex-wrap gap-1">
    <a href="<?= site_url('guru/courses/' . (int) $quiz['course_id'] . '/quizzes') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Daftar Quiz
    </a>
    <a href="<?= site_url('guru/quizzes/' . (int) $quiz['id']) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-patch-question me-1"></i>Detail Quiz
    </a>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<p class="text-muted">
    <?= esc($quiz['course_title'] ?? '-') ?> &middot; <?= esc($quiz['subject_name'] ?? '-') ?>
    &middot; <?= (int) $quiz['question_count'] ?> soal &middot; <?= (int) $quiz['total_points'] ?> poin
</p>

<div class="row g-3 mb-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded bg-<?= $card['color'] ?> bg-opacity-10 text-<?= $card['color'] ?> d-flex align-items-center justify-content-center flex-shrink-0"
                        style="width:56px;height:56px">
                        <i class="bi <?= $card['icon'] ?> fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small"><?= esc($card['label']) ?></div>
                        <div class="fs-4 fw-semibold"><?= esc($card['value']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="small text-muted mb-2">
    Rata-rata, nilai tertinggi, dan terendah dihitung dari siswa yang sudah selesai dan masih terdaftar di course ini.
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Daftar Siswa</div>
    <?php if (empty($roster)): ?>
        <div class="card-body text-center text-muted">Belum ada siswa yang terdaftar pada course ini.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th>NIS</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Mulai</th>
                        <th>Dikumpulkan</th>
                        <th class="text-center">Lama</th>
                        <th class="text-center">Nilai</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roster as $r): ?>
                        <?php
                        $done    = ($r['status'] ?? null) === 'submitted';
                        $running = ($r['status'] ?? null) === 'in_progress';
                        ?>
                        <tr>
                            <td><?= esc($r['name']) ?></td>
                            <td><?= esc($r['nis'] ?? '-') ?></td>
                            <td><?= esc($r['class_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($done): ?>
                                    <span class="badge text-bg-success">Selesai</span>
                                <?php elseif ($running): ?>
                                    <span class="badge text-bg-warning text-dark">Sedang mengerjakan</span>
                                <?php else: ?>
                                    <span class="badge text-bg-light border text-muted">Belum mulai</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= esc($fmt($r['started_at'] ?? null)) ?></td>
                            <td class="text-nowrap"><?= $done ? esc($fmt($r['submitted_at'])) : '-' ?></td>
                            <td class="text-center text-nowrap"><?= $done ? esc($duration($r['started_at'], $r['submitted_at'])) : '-' ?></td>
                            <td class="text-center fw-semibold">
                                <?= ($done && $r['score'] !== null) ? esc($num($r['score'])) : '-' ?>
                            </td>
                            <td class="text-end">
                                <?php if ($done): ?>
                                    <a href="<?= site_url('guru/attempts/' . (int) $r['attempt_id']) ?>" class="btn btn-sm btn-outline-primary">Lihat Jawaban</a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
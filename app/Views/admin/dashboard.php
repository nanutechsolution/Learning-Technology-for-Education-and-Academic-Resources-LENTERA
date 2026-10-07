<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<p class="text-muted">
    Selamat datang, <strong><?= esc(auth_name()) ?></strong>. Berikut ringkasan data LENTERA.
</p>

<?php
$cards = [
    ['label' => 'Guru',          'key' => 'teachers', 'icon' => 'bi-person-workspace', 'color' => 'primary', 'url' => 'admin/teachers'],
    ['label' => 'Siswa',         'key' => 'students', 'icon' => 'bi-people',           'color' => 'success', 'url' => 'admin/students'],
    ['label' => 'Kelas',         'key' => 'classes',  'icon' => 'bi-door-open',        'color' => 'warning', 'url' => 'admin/classes'],
    ['label' => 'Mata Pelajaran', 'key' => 'subjects', 'icon' => 'bi-journal-bookmark', 'color' => 'info',    'url' => 'admin/subjects'],
    ['label' => 'Course',        'key' => 'courses',  'icon' => 'bi-collection',       'color' => 'danger',  'url' => 'admin/courses'],
];
?>

<div class="row g-3">
    <?php foreach ($cards as $c): ?>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded bg-<?= esc($c['color'], 'attr') ?> bg-opacity-10 text-<?= esc($c['color'], 'attr') ?> d-flex align-items-center justify-content-center me-3"
                        style="width:56px;height:56px;">
                        <i class="bi <?= esc($c['icon'], 'attr') ?> fs-3"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="text-muted small"><?= esc($c['label']) ?></div>
                        <div class="fs-3 fw-semibold"><?= (int) $stats[$c['key']] ?></div>
                    </div>
                    <a href="<?= base_url($c['url']) ?>" class="stretched-link" aria-label="Kelola <?= esc($c['label'], 'attr') ?>"></a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="mt-3">
    <?= view('announcements/_widget', [
        'items'        => $announcements ?? [],
        'detailPrefix' => 'admin/announcements',
        'allUrl'       => 'admin/announcements',
    ]) ?>
</div>

<?= $this->endSection() ?>
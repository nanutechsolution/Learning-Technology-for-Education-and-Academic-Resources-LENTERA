<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (! $hasProfile): ?>
    <div class="alert alert-warning mb-0">
        <i class="bi bi-exclamation-circle me-1"></i>
        Akun Anda belum terhubung dengan profil siswa. Hubungi admin.
    </div>
<?php elseif (empty($courses)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">Anda belum terdaftar pada course apa pun.</div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Course</th>
                        <th>Guru</th>
                        <th>Kelas</th>
                        <th style="min-width:180px">Progres Materi</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                        <?php
                        $total   = (int) $c['material_total'];
                        $viewed  = (int) $c['material_viewed'];
                        $percent = $total > 0 ? round($viewed / $total * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc($c['title']) ?></div>
                                <div class="small text-muted"><?= esc($c['subject_name'] ?? '-') ?></div>
                            </td>
                            <td><?= esc($c['teacher_name'] ?? '-') ?></td>
                            <td><?= esc($c['class_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($total === 0): ?>
                                    <span class="text-muted small">Belum ada materi</span>
                                <?php else: ?>
                                    <div class="small mb-1"><?= $viewed ?> / <?= $total ?> materi dibuka</div>
                                    <div class="progress" style="height:6px" role="progressbar" aria-valuenow="<?= (int) $percent ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bg-success" style="width: <?= (int) $percent ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('siswa/courses/' . (int) $c['id']) ?>" class="btn btn-sm btn-primary">
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
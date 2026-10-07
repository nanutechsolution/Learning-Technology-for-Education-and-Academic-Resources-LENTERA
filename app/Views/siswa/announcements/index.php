<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php if (empty($announcements)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center text-muted py-5">Belum ada pengumuman.</div>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="list-group list-group-flush">
            <?php foreach ($announcements as $a): ?>
                <?php $isSchool = $a['course_id'] === null; ?>
                <a href="<?= site_url('siswa/announcements/' . (int) $a['id']) ?>" class="list-group-item list-group-item-action">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                        <span class="badge text-bg-<?= $isSchool ? 'primary' : 'info' ?>"><?= $isSchool ? 'Sekolah' : 'Course' ?></span>
                        <span class="fw-semibold text-break"><?= esc($a['title']) ?></span>
                    </div>
                    <div class="small text-muted">
                        <?= $isSchool ? 'Seluruh sekolah' : esc($a['course_title'] ?? '-') ?>
                        &middot; <?= esc($a['author_name'] ?? '-') ?>
                        &middot; <?= ! empty($a['published_at']) ? esc(date('d/m/Y H:i', strtotime($a['published_at']))) : '-' ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

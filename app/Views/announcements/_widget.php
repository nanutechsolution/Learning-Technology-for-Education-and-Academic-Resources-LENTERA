<?php
// Variabel: $items (list), $detailPrefix (mis. 'siswa/announcements'), $allUrl
$items = $items ?? [];
?>
<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-megaphone me-1"></i>Pengumuman Terbaru</span>
        <a href="<?= site_url($allUrl) ?>" class="small text-decoration-none">Lihat semua</a>
    </div>

    <?php if (empty($items)): ?>
        <div class="card-body text-center text-muted py-4">Belum ada pengumuman.</div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($items as $a): ?>
                <?php $when = ! empty($a['published_at']) ? $a['published_at'] : $a['created_at']; ?>
                <a href="<?= site_url($detailPrefix . '/' . (int) $a['id']) ?>" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="fw-semibold text-break"><?= esc($a['title']) ?></div>
                        <?php if ((int) $a['is_published'] !== 1): ?>
                            <span class="badge text-bg-secondary flex-shrink-0">Draft</span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted">
                        <?= $a['course_id'] === null ? 'Sekolah' : esc($a['course_title'] ?? 'Course') ?>
                        &middot; <?= esc(date('d/m/Y H:i', strtotime((string) $when))) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

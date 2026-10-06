<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<div class="d-flex flex-wrap gap-1">
    <a href="<?= site_url('siswa/courses') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Kelas Saya
    </a>
    <a href="<?= site_url('siswa/courses/' . (int) $course['id'] . '/assignments') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-clipboard-check me-1"></i>Tugas
    </a>
    <a href="<?= site_url('siswa/courses/' . (int) $course['id'] . '/quizzes') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-patch-question me-1"></i>Quiz
    </a>
</div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 small">
            <div class="col-12 col-md-3"><span class="text-muted d-block">Mata Pelajaran</span><?= esc($course['subject_name'] ?? '-') ?></div>
            <div class="col-12 col-md-3"><span class="text-muted d-block">Guru</span><?= esc($course['teacher_name'] ?? '-') ?></div>
            <div class="col-12 col-md-3"><span class="text-muted d-block">Kelas</span><?= esc($course['class_name'] ?? '-') ?></div>
            <div class="col-12 col-md-3"><span class="text-muted d-block">Tahun Akademik</span><?= esc($course['academic_year_name'] ?? '-') ?></div>
        </div>
        <?php if (! empty($course['description'])): ?>
            <hr>
            <div><?= nl2br(esc($course['description'])) ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Materi Pembelajaran</div>

    <?php if (empty($materials)): ?>
        <div class="card-body text-center text-muted py-4">Belum ada materi pembelajaran.</div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($materials as $m): ?>
                <?php $opened = ! empty($m['viewed_at']); ?>
                <a href="<?= site_url('siswa/materials/' . (int) $m['id']) ?>"
                    class="list-group-item list-group-item-action d-flex align-items-start gap-3">
                    <i class="bi <?= $opened ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?> fs-5"
                        title="<?= $opened ? 'Sudah dibuka' : 'Belum dibuka' ?>"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= esc($m['title']) ?></div>
                        <?php if (! empty($m['description'])): ?>
                            <div class="small text-muted"><?= esc($m['description']) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted mt-1">
                            <?php if (! empty($m['published_at'])): ?>
                                <?= esc(date('d/m/Y', strtotime($m['published_at']))) ?>
                            <?php endif; ?>
                            <?php if (! empty($m['file_path'])): ?>
                                <i class="bi bi-paperclip ms-2"></i> File
                            <?php endif; ?>
                            <?php if (! empty($m['video_url'])): ?>
                                <i class="bi bi-youtube text-danger ms-2"></i> Video
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge text-bg-<?= $opened ? 'success' : 'light border text-muted' ?>">
                        <?= $opened ? 'Sudah dibuka' : 'Belum dibuka' ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
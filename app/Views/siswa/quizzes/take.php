<?php
$hasTimer = $remaining !== null;
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('page_actions') ?>
<?php if ($hasTimer): ?>
    <span class="badge text-bg-warning text-dark fs-6" id="quizTimer" role="timer" aria-live="off">
        <i class="bi bi-stopwatch me-1"></i><span id="quizTimerText">--:--</span>
    </span>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if (! empty($quiz['description'])): ?>
    <div class="alert alert-light border small">
        <?= nl2br(esc($quiz['description'])) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= site_url('siswa/quizzes/' . (int) $quiz['id'] . '/submit') ?>" id="quizForm" novalidate>
    <?= csrf_field() ?>

    <?php foreach ($questions as $qs): ?>
        <?php $selected = $answers[(int) $qs['id']] ?? null; ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div class="fw-semibold"><?= (int) $qs['order_number'] ?>. <?= nl2br(esc($qs['question_text'])) ?></div>
                    <span class="badge text-bg-light border"><?= (int) $qs['points'] ?> poin</span>
                </div>

                <?php foreach ($qs['options'] as $opt): ?>
                    <?php $inputId = 'q' . (int) $qs['id'] . '_o' . (int) $opt['id']; ?>
                    <div class="form-check py-1">
                        <input class="form-check-input" type="radio"
                            name="answers[<?= (int) $qs['id'] ?>]" id="<?= $inputId ?>" value="<?= (int) $opt['id'] ?>"
                            <?= $selected === (int) $opt['id'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= $inputId ?>">
                            <span class="fw-semibold me-1"><?= esc($opt['option_key']) ?>.</span><?= nl2br(esc($opt['option_text'])) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <button type="submit" class="btn btn-outline-primary"
            formaction="<?= site_url('siswa/quizzes/' . (int) $quiz['id'] . '/save') ?>">
            <i class="bi bi-save me-1"></i>Simpan Sementara
        </button>
        <button type="submit" class="btn btn-primary" id="quizSubmitBtn">
            <i class="bi bi-send me-1"></i>Kumpulkan Quiz
        </button>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    (function () {
        var form = document.getElementById('quizForm');
        var submitBtn = document.getElementById('quizSubmitBtn');
        var autoSubmitting = false;

        if (submitBtn) {
            submitBtn.addEventListener('click', function (e) {
                if (!autoSubmitting && !window.confirm('Kumpulkan quiz sekarang? Setelah dikumpulkan, jawaban tidak dapat diubah dan quiz tidak dapat diulang.')) {
                    e.preventDefault();
                }
            });
        }

        <?php if ($hasTimer): ?>
        var label = document.getElementById('quizTimerText');
        var deadline = Date.now() + <?= (int) $remaining ?> * 1000;

        function pad(n) { return n < 10 ? '0' + n : String(n); }

        function tick() {
            var left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
            var h = Math.floor(left / 3600);
            var m = Math.floor((left % 3600) / 60);
            var s = left % 60;

            label.textContent = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);

            if (left <= 0) {
                clearInterval(timer);
                autoSubmitting = true;
                form.action = <?= json_encode(site_url('siswa/quizzes/' . (int) $quiz['id'] . '/submit')) ?>;
                form.submit();
            }
        }

        var timer = setInterval(tick, 1000);
        tick();
        <?php endif; ?>
    })();
</script>
<?= $this->endSection() ?>
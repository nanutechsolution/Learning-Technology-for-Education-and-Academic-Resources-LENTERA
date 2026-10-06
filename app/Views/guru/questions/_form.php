<?php
// Variabel: $formAction, $cancelUrl, $submitLabel, $quiz, $question (array|null), $options (array), $nextOrder (int)
$question  = $question ?? null;
$options   = $options ?? [];
$nextOrder = (int) ($nextOrder ?? 1);

$hasOld = old('question_text', null, false) !== null;

$questionText = (string) old('question_text', $question['question_text'] ?? '', false);
$points       = (string) old('points', $question['points'] ?? 10, false);
$orderValue   = (string) old('order_number', $question !== null ? (string) $question['order_number'] : '', false);

$defaultCorrect = '';
foreach (array_values($options) as $idx => $opt) {
    if ((int) ($opt['is_correct'] ?? 0) === 1) {
        $defaultCorrect = (string) $idx;
        break;
    }
}
$correctValue = $hasOld ? (string) old('correct', '', false) : $defaultCorrect;

$optionValues = [];
$stored       = array_values($options);
for ($i = 0; $i < \App\Models\QuestionModel::MAX_OPTIONS; $i++) {
    $optionValues[$i] = (string) old('options.' . $i, $stored[$i]['option_text'] ?? '', false);
}

$keys = \App\Models\QuestionModel::OPTION_KEYS;
?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= esc($formAction, 'attr') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Quiz</label>
                    <input type="text" class="form-control" value="<?= esc((string) $quiz['title'], 'attr') ?>" disabled>
                </div>

                <div class="col-12">
                    <label for="question_text" class="form-label">Teks Pertanyaan <span class="text-danger">*</span></label>
                    <textarea name="question_text" id="question_text" rows="4" class="form-control" required><?= esc($questionText) ?></textarea>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label for="points" class="form-label">Poin <span class="text-danger">*</span></label>
                    <input type="number" name="points" id="points" class="form-control" min="1" max="1000" step="1" required
                        value="<?= esc($points, 'attr') ?>">
                    <div class="form-text">Bilangan bulat 1 sampai 1000. Jawaban salah atau kosong bernilai 0.</div>
                </div>

                <div class="col-12 col-md-6 col-lg-3">
                    <label for="order_number" class="form-label">Nomor Urut</label>
                    <input type="number" name="order_number" id="order_number" class="form-control" min="1" step="1"
                        placeholder="Otomatis (<?= $nextOrder ?>)" value="<?= esc($orderValue, 'attr') ?>">
                    <div class="form-text">Kosongkan untuk otomatis.</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Opsi Jawaban <span class="text-danger">*</span></label>
                    <div class="form-text mb-2">
                        Isi minimal <?= (int) \App\Models\QuestionModel::MIN_OPTIONS ?> dan maksimal <?= (int) \App\Models\QuestionModel::MAX_OPTIONS ?> opsi.
                        Opsi yang dikosongkan diabaikan. Pilih satu radio sebagai jawaban yang benar.
                    </div>

                    <?php for ($i = 0; $i < \App\Models\QuestionModel::MAX_OPTIONS; $i++): ?>
                        <div class="input-group mb-2">
                            <div class="input-group-text gap-2">
                                <input class="form-check-input mt-0" type="radio" name="correct" id="correct_<?= $i ?>" value="<?= $i ?>"
                                    aria-label="Jawaban benar opsi <?= esc($keys[$i], 'attr') ?>"
                                    <?= $correctValue === (string) $i ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold mb-0" for="correct_<?= $i ?>"><?= esc($keys[$i]) ?></label>
                            </div>
                            <input type="text" name="options[<?= $i ?>]" class="form-control" maxlength="2000"
                                placeholder="Teks opsi <?= esc($keys[$i], 'attr') ?>"
                                value="<?= esc($optionValues[$i], 'attr') ?>">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i><?= esc($submitLabel) ?>
                </button>
                <a href="<?= esc($cancelUrl, 'attr') ?>" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('guru/questions/_form', [
    'formAction'  => site_url('guru/questions/' . (int) $question['id'] . '/update'),
    'cancelUrl'   => site_url('guru/quizzes/' . (int) $quiz['id']),
    'submitLabel' => 'Simpan Perubahan',
    'quiz'        => $quiz,
    'question'    => $question,
    'options'     => $options,
    'nextOrder'   => $nextOrder,
]) ?>
<?= $this->endSection() ?>
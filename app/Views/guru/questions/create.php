<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('guru/questions/_form', [
    'formAction'  => site_url('guru/quizzes/' . (int) $quiz['id'] . '/questions'),
    'cancelUrl'   => site_url('guru/quizzes/' . (int) $quiz['id']),
    'submitLabel' => 'Simpan Pertanyaan',
    'quiz'        => $quiz,
    'question'    => null,
    'options'     => [],
    'nextOrder'   => $nextOrder,
]) ?>
<?= $this->endSection() ?>
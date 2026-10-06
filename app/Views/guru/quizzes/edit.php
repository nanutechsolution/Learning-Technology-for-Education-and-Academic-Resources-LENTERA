<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('guru/quizzes/_form', [
    'formAction'  => site_url('guru/quizzes/' . (int) $quiz['id'] . '/update'),
    'cancelUrl'   => site_url('guru/courses/' . (int) $quiz['course_id'] . '/quizzes'),
    'submitLabel' => 'Simpan Perubahan',
    'course'      => $course,
    'quiz'        => $quiz,
    'locked'      => $locked,
]) ?>
<?= $this->endSection() ?>
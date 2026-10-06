<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('guru/materials/_form', [
    'formAction'  => site_url('guru/materials/' . (int) $material['id'] . '/update'),
    'cancelUrl'   => site_url('guru/materials/' . (int) $material['id']),
    'submitLabel' => 'Simpan Perubahan',
    'courses'     => $courses,
    'courseId'    => $courseId,
    'material'    => $material,
]) ?>
<?= $this->endSection() ?>
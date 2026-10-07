<?php
helper(['url', 'school']);

$error       = session()->getFlashdata('error');
$success     = session()->getFlashdata('success');
$oldUsername = (string) session()->getFlashdata('old_username');

$schoolLabel = school_display_name();
$logoUrl     = school_logo_url();

$assetVersion = static fn(string $path): int => is_file(FCPATH . $path) ? (int) filemtime(FCPATH . $path) : 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(school_page_title('Masuk')) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/lentera.css') ?>?v=<?= $assetVersion('assets/css/lentera.css') ?>">
</head>

<body class="login-page">

    <main class="login-box">
        <div class="text-center mb-4">
            <div class="brand-mark mb-3<?= $logoUrl !== null ? ' has-logo' : '' ?>">
                <?php if ($logoUrl !== null): ?>
                    <img src="<?= esc($logoUrl, 'attr') ?>" alt="Logo <?= esc($schoolLabel, 'attr') ?>">
                <?php else: ?>
                    <i class="bi bi-lightbulb-fill"></i>
                <?php endif; ?>
            </div>
            <h1 class="login-title">LENTERA</h1>
            <p class="login-subtitle mb-1">Learning Technology for Education and Academic Resources</p>
            <p class="login-school mb-0"><?= esc($schoolLabel) ?></p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Masuk ke Akun</h2>

                <?php if (! empty($error)): ?>
                    <div class="alert alert-danger py-2" role="alert"><?= esc((string) $error) ?></div>
                <?php endif; ?>

                <?php if (! empty($success)): ?>
                    <div class="alert alert-success py-2" role="alert"><?= esc((string) $success) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('login') ?>" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username"
                                value="<?= esc($oldUsername) ?>" maxlength="50"
                                autocomplete="username" required <?= $oldUsername === '' ? 'autofocus' : '' ?>>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Kata Sandi</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password"
                                maxlength="72" autocomplete="current-password" required
                                <?= $oldUsername !== '' ? 'autofocus' : '' ?>>
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password"
                                aria-label="Tampilkan kata sandi">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center login-footer mt-4 mb-0">
            &copy; <?= date('Y') ?> LENTERA &middot; <?= esc($schoolLabel) ?>
        </p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('assets/js/lentera.js') ?>?v=<?= $assetVersion('assets/js/lentera.js') ?>"></script>
</body>

</html>
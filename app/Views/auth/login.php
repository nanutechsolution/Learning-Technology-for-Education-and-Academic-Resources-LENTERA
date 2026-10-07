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
        <div class="login-card">
            <div class="text-center mb-4">
                <div class="login-logo">
                    <?php if ($logoUrl !== null): ?>
                        <img src="<?= esc($logoUrl, 'attr') ?>" alt="Logo <?= esc($schoolLabel, 'attr') ?>">
                    <?php else: ?>
                        <span class="login-logo-fallback"><i class="bi bi-lightbulb-fill"></i></span>
                    <?php endif; ?>
                </div>
                <h1 class="login-title">LENTERA</h1>
                <p class="login-school"><?= esc($schoolLabel) ?></p>
                <p class="login-subtitle mb-0">Sistem Pembelajaran Berbasis Digital</p>
            </div>

            <?php if (! empty($error)): ?>
                <div class="alert alert-danger py-2" role="alert"><?= esc((string) $error) ?></div>
            <?php endif; ?>

            <?php if (! empty($success)): ?>
                <div class="alert alert-success py-2" role="alert"><?= esc((string) $success) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= site_url('login') ?>" novalidate>
                <?= csrf_field() ?>

                <div class="login-field mb-3">
                    <i class="bi bi-person field-icon" aria-hidden="true"></i>
                    <input type="text" class="form-control" id="username" name="username"
                        placeholder="Username" aria-label="Username"
                        value="<?= esc($oldUsername) ?>" maxlength="50"
                        autocomplete="username" required <?= $oldUsername === '' ? 'autofocus' : '' ?>>
                </div>

                <div class="login-field mb-4">
                    <i class="bi bi-lock field-icon" aria-hidden="true"></i>
                    <div class="password-wrap">
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Password" aria-label="Password"
                            maxlength="72" autocomplete="current-password" required
                            <?= $oldUsername !== '' ? 'autofocus' : '' ?>>
                        <button type="button" class="toggle-password" data-toggle-password="#password"
                            aria-label="Tampilkan kata sandi">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-login">LOGIN</button>
            </form>

            <p class="login-help mb-0">Lupa password? Hubungi administrator sekolah.</p>
        </div>

        <p class="text-center login-footer mt-4 mb-0">
            &copy; <?= date('Y') ?> LENTERA &middot; <?= esc($schoolLabel) ?>
        </p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('assets/js/lentera.js') ?>?v=<?= $assetVersion('assets/js/lentera.js') ?>"></script>
</body>

</html>
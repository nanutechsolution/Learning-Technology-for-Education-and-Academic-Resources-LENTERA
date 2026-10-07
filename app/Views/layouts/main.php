<?php
helper(['auth', 'url', 'school']);

$authUser  = auth_user();
$role      = auth_role();
$pageTitle = $title ?? 'Dashboard';

$schoolLabel = school_display_name();
$brandSub    = school_brand_sub();
$footerPlace = school_footer_place();
$logoUrl     = school_logo_url();

$roleLabels = [
    'admin' => 'Administrator',
    'guru'  => 'Guru',
    'siswa' => 'Siswa',
];

$menus = [
    'admin' => [
        ['label' => 'Dashboard',      'icon' => 'bi-speedometer2',  'url' => 'admin/dashboard',      'match' => 'admin/dashboard'],
        ['heading' => 'Master Data'],
        ['label' => 'Tahun Akademik', 'icon' => 'bi-calendar3',     'url' => 'admin/academic-years', 'match' => 'admin/academic-years*'],
        ['label' => 'Guru',           'icon' => 'bi-person-workspace', 'url' => 'admin/teachers',    'match' => 'admin/teachers*'],
        ['label' => 'Siswa',          'icon' => 'bi-people',        'url' => 'admin/students',       'match' => 'admin/students*'],
        ['label' => 'Kelas',          'icon' => 'bi-door-open',     'url' => 'admin/classes',        'match' => 'admin/classes*'],
        ['label' => 'Mata Pelajaran', 'icon' => 'bi-book',          'url' => 'admin/subjects',       'match' => 'admin/subjects*'],
        ['label' => 'Course',         'icon' => 'bi-mortarboard',   'url' => 'admin/courses',        'match' => 'admin/courses*'],
        ['heading' => 'Komunikasi'],
        ['label' => 'Pengumuman',     'icon' => 'bi-megaphone',     'url' => 'admin/announcements',  'match' => 'admin/announcements*'],
        ['heading' => 'Pengaturan'],
        ['label' => 'Pengaturan Sekolah', 'icon' => 'bi-gear',      'url' => 'admin/school-settings', 'match' => 'admin/school-settings*'],
    ],
    'guru' => [
        ['label' => 'Dashboard',      'icon' => 'bi-speedometer2',  'url' => 'guru/dashboard',       'match' => 'guru/dashboard'],
        ['heading' => 'Pembelajaran'],
        ['label' => 'Materi',         'icon' => 'bi-journal-text',  'url' => 'guru/courses',         'match' => ['guru/courses', 'guru/courses/*/materials*', 'guru/materials*']],
        ['label' => 'Tugas',          'icon' => 'bi-clipboard-check', 'url' => 'guru/courses',       'match' => ['guru/courses/*/assignments*', 'guru/assignments*', 'guru/submissions*']],
        ['label' => 'Quiz',           'icon' => 'bi-patch-question', 'url' => 'guru/courses',        'match' => ['guru/courses/*/quizzes*', 'guru/quizzes*', 'guru/questions*', 'guru/attempts*']],
        ['heading' => 'Komunikasi'],
        ['label' => 'Pengumuman',     'icon' => 'bi-megaphone',     'url' => 'guru/announcements',   'match' => ['guru/announcements*', 'guru/courses/*/announcements*']],
    ],
    'siswa' => [
        ['label' => 'Dashboard',      'icon' => 'bi-speedometer2',  'url' => 'siswa/dashboard',      'match' => 'siswa/dashboard'],
        ['label' => 'Kelas Saya',     'icon' => 'bi-journal-bookmark', 'url' => 'siswa/courses',     'match' => ['siswa/courses*', 'siswa/materials*', 'siswa/assignments*', 'siswa/quizzes*']],
        ['label' => 'Pengumuman',     'icon' => 'bi-megaphone',     'url' => 'siswa/announcements',  'match' => 'siswa/announcements*'],
    ],
];

$menu = $menus[$role] ?? [];

$flashTypes = [
    'success' => ['class' => 'success', 'icon' => 'bi-check-circle'],
    'error'   => ['class' => 'danger',  'icon' => 'bi-exclamation-triangle'],
    'warning' => ['class' => 'warning', 'icon' => 'bi-exclamation-circle'],
    'info'    => ['class' => 'info',    'icon' => 'bi-info-circle'],
];

$assetVersion = static fn(string $path): int => is_file(FCPATH . $path) ? (int) filemtime(FCPATH . $path) : 0;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(school_page_title((string) $pageTitle)) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/lentera.css') ?>?v=<?= $assetVersion('assets/css/lentera.css') ?>">
    <?= $this->renderSection('styles') ?>
</head>

<body>

    <aside class="lentera-sidebar" id="sidebar" aria-label="Menu utama">
        <a href="<?= auth_dashboard_url() ?>" class="sidebar-brand">
            <span class="brand-mark<?= $logoUrl !== null ? ' has-logo' : '' ?>">
                <?php if ($logoUrl !== null): ?>
                    <img src="<?= esc($logoUrl, 'attr') ?>" alt="Logo <?= esc($schoolLabel, 'attr') ?>">
                <?php else: ?>
                    <i class="bi bi-lightbulb-fill"></i>
                <?php endif; ?>
            </span>
            <span class="sidebar-brand-text">
                <span class="sidebar-brand-title d-block">LENTERA</span>
                <span class="sidebar-brand-sub d-block" title="<?= esc($brandSub, 'attr') ?>"><?= esc($brandSub) ?></span>
            </span>
        </a>

        <nav class="sidebar-nav">
            <?php foreach ($menu as $item): ?>
                <?php if (isset($item['heading'])): ?>
                    <div class="sidebar-heading"><?= esc($item['heading']) ?></div>
                <?php else: ?>
                    <?php
                    $isActive = false;
                    foreach ((array) $item['match'] as $pattern) {
                        if (url_is($pattern)) {
                            $isActive = true;
                            break;
                        }
                    }
                    ?>
                    <a href="<?= site_url($item['url']) ?>"
                        class="sidebar-link<?= $isActive ? ' active' : '' ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= esc($item['icon']) ?>"></i>
                        <span><?= esc($item['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            Learning Technology for Education and Academic Resources
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="content-wrapper">

        <header class="top-navbar d-flex align-items-center justify-content-between">
            <button type="button" class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle"
                aria-label="Buka menu" aria-controls="sidebar">
                <i class="bi bi-list"></i>
            </button>

            <div class="d-none d-lg-block text-muted small"><?= esc($schoolLabel) ?></div>

            <div class="dropdown ms-auto">
                <button class="btn btn-light border d-flex align-items-center gap-2" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle fs-5"></i>
                    <span class="d-none d-sm-inline text-start lh-sm">
                        <span class="d-block fw-semibold small"><?= esc($authUser['name'] ?? '') ?></span>
                        <span class="d-block text-muted nav-role"><?= esc($roleLabels[$role] ?? '') ?></span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2 d-sm-none">
                        <div class="fw-semibold small"><?= esc($authUser['name'] ?? '') ?></div>
                        <div class="text-muted nav-role"><?= esc($roleLabels[$role] ?? '') ?></div>
                    </li>
                    <li class="d-sm-none">
                        <hr class="dropdown-divider">
                    </li>
                    <li class="px-3 py-1 text-muted small">@<?= esc($authUser['username'] ?? '') ?></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <form method="post" action="<?= site_url('logout') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Keluar
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content-main">

            <?php foreach ($flashTypes as $key => $meta): ?>
                <?php $flash = session()->getFlashdata($key); ?>
                <?php if (! empty($flash)): ?>
                    <?php foreach ((array) $flash as $message): ?>
                        <div class="alert alert-<?= $meta['class'] ?> alert-dismissible fade show" role="alert">
                            <i class="bi <?= $meta['icon'] ?> me-1"></i> <?= esc((string) $message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h1 class="h4 mb-0"><?= esc($pageTitle) ?></h1>
                <div><?= $this->renderSection('page_actions') ?></div>
            </div>

            <?= $this->renderSection('content') ?>
        </main>

        <footer class="content-footer">
            &copy; <?= date('Y') ?> <strong>LENTERA</strong> &middot; <?= esc($footerPlace) ?>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('assets/js/lentera.js') ?>?v=<?= $assetVersion('assets/js/lentera.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>

</html>
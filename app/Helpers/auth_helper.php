<?php

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Helper autentikasi LENTERA.
 * Pemakaian: helper('auth');
 * Data login disimpan di session pada key 'auth_user'.
 */

if (! function_exists('auth_user')) {
    /**
     * Data user yang sedang login (id, name, username, role) atau null.
     */
    function auth_user(): ?array
    {
        $user = session()->get('auth_user');

        return (is_array($user) && isset($user['id'], $user['role'])) ? $user : null;
    }
}

if (! function_exists('auth_check')) {
    function auth_check(): bool
    {
        return auth_user() !== null;
    }
}

if (! function_exists('auth_id')) {
    function auth_id(): ?int
    {
        $user = auth_user();

        return $user !== null ? (int) $user['id'] : null;
    }
}

if (! function_exists('auth_name')) {
    function auth_name(): string
    {
        $user = auth_user();

        return $user !== null ? (string) ($user['name'] ?? '') : '';
    }
}

if (! function_exists('auth_role')) {
    function auth_role(): ?string
    {
        $user = auth_user();

        return $user !== null ? (string) $user['role'] : null;
    }
}

if (! function_exists('auth_is')) {
    /**
     * Cek apakah role user saat ini termasuk salah satu role yang diberikan.
     */
    function auth_is(string ...$roles): bool
    {
        $role = auth_role();

        return $role !== null && in_array($role, $roles, true);
    }
}

if (! function_exists('auth_dashboard_url')) {
    /**
     * URL dashboard sesuai role. Role tidak dikenal diarahkan ke halaman login.
     */
    function auth_dashboard_url(?string $role = null): string
    {
        $role ??= auth_role();

        return match ($role) {
            'admin' => site_url('admin/dashboard'),
            'guru'  => site_url('guru/dashboard'),
            'siswa' => site_url('siswa/dashboard'),
            default => site_url('login'),
        };
    }
}

if (! function_exists('auth_login')) {
    /**
     * Simpan data login ke session. Session ID diregenerasi untuk
     * mencegah session fixation.
     *
     * @param array $user Baris dari tabel users (id, name, username, role)
     */
    function auth_login(array $user): void
    {
        session()->regenerate(true);

        session()->set('auth_user', [
            'id'       => (int) $user['id'],
            'name'     => (string) $user['name'],
            'username' => (string) $user['username'],
            'role'     => (string) $user['role'],
        ]);
    }
}

if (! function_exists('auth_logout')) {
    /**
     * Hapus data login dan regenerasi session ID.
     * Session tetap aktif sehingga flash message masih bisa dipakai.
     */
    function auth_logout(): void
    {
        session()->remove('auth_user');
        session()->regenerate(true);
    }
}

if (! function_exists('auth_guard')) {
    /**
     * Pemeriksaan akses server-side. Dipakai oleh semua filter.
     *
     * Mengembalikan RedirectResponse jika akses ditolak, atau null jika boleh lanjut.
     *
     * @param string|null $role Role yang diizinkan. Null berarti cukup sudah login.
     */
    function auth_guard(?string $role = null): ?RedirectResponse
    {
        if (! auth_check()) {
            return redirect()->to(site_url('login'))
                ->with('error', 'Silakan login terlebih dahulu.');
        }

        $user = model(UserModel::class)
            ->select('id, name, username, role, is_active')
            ->find(auth_id());

        if ($user === null || (int) $user['is_active'] !== 1) {
            auth_logout();

            return redirect()->to(site_url('login'))
                ->with('error', 'Akun Anda tidak aktif atau tidak ditemukan. Silakan hubungi administrator.');
        }

        // Sinkronkan session dengan data terbaru dari database.
        session()->set('auth_user', [
            'id'       => (int) $user['id'],
            'name'     => (string) $user['name'],
            'username' => (string) $user['username'],
            'role'     => (string) $user['role'],
        ]);

        if ($role !== null && $user['role'] !== $role) {
            return redirect()->to(auth_dashboard_url((string) $user['role']))
                ->with('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        }

        return null;
    }
}

if (! function_exists('auth_no_cache')) {
    /**
     * Cegah browser menyimpan halaman terproteksi di cache.
     */
    function auth_no_cache(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache');
    }
}

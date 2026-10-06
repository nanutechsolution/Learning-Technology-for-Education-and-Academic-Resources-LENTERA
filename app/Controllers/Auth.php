<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class Auth extends BaseController
{
    private const MAX_ATTEMPTS_PER_USER = 5;
    private const MAX_ATTEMPTS_PER_IP   = 20;
    private const WINDOW_SECONDS        = 60;

    private const VALID_ROLES = ['admin', 'guru', 'siswa'];

    public function __construct()
    {
        helper(['auth', 'url']);
    }

    /**
     * Route "/" : arahkan ke dashboard (jika sudah login) atau halaman login.
     */
    public function index(): RedirectResponse
    {
        if (auth_is(...self::VALID_ROLES)) {
            return redirect()->to(auth_dashboard_url());
        }

        return redirect()->to(site_url('login'));
    }

    /**
     * GET /login
     */
    public function login(): RedirectResponse|string
    {
        if (auth_is(...self::VALID_ROLES)) {
            return redirect()->to(auth_dashboard_url());
        }

        return view('auth/login');
    }

    /**
     * POST /login
     */
    public function attempt(): RedirectResponse
    {
        $rules = [
            'username' => [
                'label' => 'Username',
                'rules' => 'required|max_length[50]',
            ],
            'password' => [
                'label' => 'Kata sandi',
                'rules' => 'required|max_length[72]',
            ],
        ];

        if (! $this->validate($rules)) {
            return redirect()->to(site_url('login'))
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $wait = $this->throttleWait($username);

        if ($wait !== null) {
            return $this->failed(
                'Terlalu banyak percobaan login. Silakan coba lagi dalam ' . $wait . ' detik.',
                $username
            );
        }

        $userModel = new UserModel();
        $user      = $userModel->findByUsername($username);

        // password_verify tetap dijalankan walau user tidak ada,
        // agar waktu respons tidak membocorkan keberadaan username.
        $hash       = $user['password'] ?? password_hash('lentera-dummy-password', PASSWORD_DEFAULT);
        $passwordOk = password_verify($password, $hash);

        if ($user === null || ! $passwordOk) {
            return $this->failed('Username atau kata sandi salah.', $username);
        }

        if ((int) $user['is_active'] !== 1) {
            return $this->failed('Akun Anda tidak aktif. Silakan hubungi administrator.', $username);
        }

        if (! in_array($user['role'], self::VALID_ROLES, true)) {
            return $this->failed('Role akun tidak valid. Silakan hubungi administrator.', $username);
        }

        // Perbarui hash jika algoritma/cost PHP berubah.
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $userModel->skipValidation(true)->update((int) $user['id'], ['password' => $password]);
        }

        auth_login($user);

        return redirect()->to(auth_dashboard_url((string) $user['role']))
            ->with('success', 'Selamat datang, ' . $user['name'] . '.');
    }

    /**
     * POST /logout
     */
    public function logout(): RedirectResponse
    {
        auth_logout();

        return redirect()->to(site_url('login'))
            ->with('success', 'Anda telah berhasil keluar.');
    }

    /**
     * Redirect kembali ke form login dengan pesan error.
     * Hanya username yang dikembalikan; password tidak pernah disimpan.
     */
    private function failed(string $message, string $username): RedirectResponse
    {
        return redirect()->to(site_url('login'))
            ->with('error', $message)
            ->with('old_username', $username);
    }

    /**
     * Pembatasan percobaan login. Mengembalikan sisa detik tunggu,
     * atau null jika masih diizinkan.
     */
    private function throttleWait(string $username): ?int
    {
        $throttler = service('throttler');
        $ip        = $this->request->getIPAddress();

        $limits = [
            ['login_user_' . md5($ip . '|' . strtolower($username)), self::MAX_ATTEMPTS_PER_USER],
            ['login_ip_' . md5($ip), self::MAX_ATTEMPTS_PER_IP],
        ];

        foreach ($limits as [$key, $capacity]) {
            if ($throttler->check($key, $capacity, self::WINDOW_SECONDS) === false) {
                return max(1, (int) $throttler->getTokenTime());
            }
        }

        return null;
    }
}

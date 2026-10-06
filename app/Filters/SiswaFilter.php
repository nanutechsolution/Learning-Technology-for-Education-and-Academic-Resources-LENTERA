<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filter 'siswa': hanya role siswa yang boleh melanjutkan.
 */
class SiswaFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');

        return auth_guard('siswa');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        helper('auth');

        return auth_no_cache($response);
    }
}

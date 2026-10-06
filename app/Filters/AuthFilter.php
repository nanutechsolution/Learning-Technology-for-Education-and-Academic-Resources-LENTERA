<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filter 'auth': hanya mensyaratkan user sudah login dan akunnya aktif.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');

        return auth_guard();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        helper('auth');

        return auth_no_cache($response);
    }
}

<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filter 'guru': hanya role guru yang boleh melanjutkan.
 */
class GuruFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');

        return auth_guard('guru');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        helper('auth');

        return auth_no_cache($response);
    }
}

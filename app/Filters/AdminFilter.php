<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filter 'admin': hanya role admin yang boleh melanjutkan.
 */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('auth');

        return auth_guard('admin');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        helper('auth');

        return auth_no_cache($response);
    }
}
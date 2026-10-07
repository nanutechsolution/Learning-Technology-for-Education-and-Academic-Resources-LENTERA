<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

class Branding extends BaseController
{
    public function __construct()
    {
        helper(['branding', 'school']);
    }

    /**
     * GET /school-logo (publik). Hanya membaca satu file dari nama yang tercatat di DB.
     */
    public function logo()
    {
        $name = school_settings()['logo_path'] ?? null;
        $path = branding_logo_path($name);
        $mime = branding_logo_mime($name);

        if ($path === null || $mime === null) {
            throw PageNotFoundException::forPageNotFound('Logo tidak ditemukan.');
        }

        $body = file_get_contents($path);

        if ($body === false) {
            throw PageNotFoundException::forPageNotFound('Logo tidak ditemukan.');
        }

        return $this->response
            ->setStatusCode(200)
            ->setContentType($mime, '')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('Cache-Control', 'public, max-age=3600')
            ->setBody($body);
    }
}

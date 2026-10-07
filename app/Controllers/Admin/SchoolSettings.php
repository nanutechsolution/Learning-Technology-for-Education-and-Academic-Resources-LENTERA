<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SchoolSettingModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class SchoolSettings extends BaseController
{
    private SchoolSettingModel $settings;

    public function __construct()
    {
        helper(['auth', 'url', 'branding']);
        $this->settings = new SchoolSettingModel();
    }

    public function edit()
    {
        return auth_no_cache(
            $this->response->setBody(view('admin/school_settings/form', [
                'title'   => 'Pengaturan Sekolah',
                'setting' => $this->settings->current() ?? [],
                'action'  => base_url('admin/school-settings/update'),
                'logoMax' => number_format(branding_logo_max_size() / 1048576, 1, ',', '.') . ' MB',
            ]))
        );
    }

    public function update()
    {
        $data    = $this->collect();
        $current = $this->settings->current();
        $oldLogo = $current['logo_path'] ?? null;

        // 1. Periksa semua input sebelum ada yang disimpan.
        $errors = [];
        $info   = null;
        $file   = $this->request->getFile('logo');
        $upload = $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;

        if ($upload) {
            [$info, $logoErrors] = branding_inspect_logo($file);
            $errors              = $logoErrors;
        }

        if (! $this->settings->validate($data)) {
            $errors = array_merge(array_values($this->settings->errors()), $errors);
        }

        if ($errors !== []) {
            return redirect()->back()->withInput()->with('error', $errors);
        }

        // 2. Simpan file baru lebih dulu.
        $newName = null;

        if ($info !== null) {
            $newName = branding_store_logo($file, $info);

            if ($newName === null) {
                return redirect()->back()->withInput()->with('error', 'Logo gagal disimpan di server. Coba lagi atau hubungi admin.');
            }

            $data['logo_path'] = $newName;
        } elseif ($oldLogo !== null && $this->request->getPost('remove_logo')) {
            $data['logo_path'] = null;
        }

        // 3. Simpan DB; jika gagal, hapus file baru dan biarkan logo lama.
        try {
            $saved = $this->settings->saveSettings($data);
        } catch (\Throwable $e) {
            log_message('error', 'Gagal menyimpan pengaturan sekolah: ' . $e->getMessage());
            $saved = false;
        }

        if (! $saved) {
            if ($newName !== null) {
                branding_delete_logo($newName);
            }

            $messages = array_values($this->settings->errors());

            return redirect()->back()->withInput()->with('error', $messages !== [] ? $messages : ['Pengaturan gagal disimpan. Coba lagi.']);
        }

        // 4. Baru hapus logo lama.
        if (array_key_exists('logo_path', $data) && $oldLogo !== null && $oldLogo !== $data['logo_path']) {
            branding_delete_logo($oldLogo);
        }

        return redirect()->to('admin/school-settings')->with('success', 'Pengaturan sekolah berhasil disimpan.');
    }

    /**
     * Pratinjau logo untuk form admin (rute di group admin, bukan rute publik).
     */
    public function logo()
    {
        $row  = $this->settings->current();
        $name = $row['logo_path'] ?? null;
        $path = branding_logo_path($name);
        $mime = branding_logo_mime($name);

        if ($path === null || $mime === null) {
            throw PageNotFoundException::forPageNotFound('Logo tidak ditemukan.');
        }

        return $this->response
            ->setStatusCode(200)
            ->setContentType($mime, '')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('Cache-Control', 'private, no-cache')
            ->setBody((string) file_get_contents($path));
    }

    private function collect(): array
    {
        $fields = [
            'school_name', 'npsn', 'address', 'village', 'district', 'regency', 'province',
            'email', 'phone', 'website', 'principal_name', 'principal_nip',
        ];

        $data = [];

        foreach ($fields as $field) {
            $data[$field] = trim((string) $this->request->getPost($field));
        }

        return $data;
    }
}
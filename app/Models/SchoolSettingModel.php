<?php

namespace App\Models;

use CodeIgniter\Model;

class SchoolSettingModel extends Model
{
    protected $table            = 'school_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'school_name',
        'npsn',
        'address',
        'village',
        'district',
        'regency',
        'province',
        'email',
        'phone',
        'website',
        'logo_path',
        'principal_name',
        'principal_nip',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'school_name' => [
            'label' => 'Nama sekolah',
            'rules' => 'required|min_length[3]|max_length[150]',
        ],
        'npsn' => [
            'label' => 'NPSN',
            'rules' => 'permit_empty|regex_match[/^[0-9]{8}$/]',
        ],
        'address' => [
            'label' => 'Alamat',
            'rules' => 'permit_empty|max_length[255]',
        ],
        'village' => [
            'label' => 'Desa/Kelurahan',
            'rules' => 'permit_empty|max_length[100]',
        ],
        'district' => [
            'label' => 'Kecamatan',
            'rules' => 'permit_empty|max_length[100]',
        ],
        'regency' => [
            'label' => 'Kabupaten',
            'rules' => 'permit_empty|max_length[100]',
        ],
        'province' => [
            'label' => 'Provinsi',
            'rules' => 'permit_empty|max_length[100]',
        ],
        'email' => [
            'label' => 'Email',
            'rules' => 'permit_empty|max_length[150]|valid_email',
        ],
        'phone' => [
            'label' => 'Nomor telepon',
            'rules' => 'permit_empty|max_length[30]|regex_match[/^[0-9+()\-\s.]+$/]',
        ],
        'website' => [
            'label' => 'Website',
            'rules' => 'permit_empty|max_length[255]|valid_url_strict[http,https]',
        ],
        'logo_path' => [
            'label' => 'Logo',
            'rules' => 'permit_empty|max_length[64]|regex_match[/^[a-f0-9]{32}\.(png|jpg|webp)$/]',
        ],
        'principal_name' => [
            'label' => 'Nama kepala sekolah',
            'rules' => 'permit_empty|max_length[150]',
        ],
        'principal_nip' => [
            'label' => 'NIP kepala sekolah',
            'rules' => 'permit_empty|max_length[30]|regex_match[/^[0-9\s]+$/]',
        ],
    ];

    protected $validationMessages = [
        'school_name' => [
            'required'   => '{field} wajib diisi.',
            'min_length' => '{field} minimal {param} karakter.',
            'max_length' => '{field} maksimal {param} karakter.',
        ],
        'npsn' => [
            'regex_match' => '{field} harus berupa 8 digit angka.',
        ],
        'address' => [
            'max_length' => '{field} maksimal {param} karakter.',
        ],
        'village' => [
            'max_length' => '{field} maksimal {param} karakter.',
        ],
        'district' => [
            'max_length' => '{field} maksimal {param} karakter.',
        ],
        'regency' => [
            'max_length' => '{field} maksimal {param} karakter.',
        ],
        'province' => [
            'max_length' => '{field} maksimal {param} karakter.',
        ],
        'email' => [
            'max_length'  => '{field} maksimal {param} karakter.',
            'valid_email' => '{field} tidak valid.',
        ],
        'phone' => [
            'max_length'  => '{field} maksimal {param} karakter.',
            'regex_match' => '{field} hanya boleh berisi angka, spasi, dan karakter + ( ) - .',
        ],
        'website' => [
            'max_length'       => '{field} maksimal {param} karakter.',
            'valid_url_strict' => '{field} harus berupa URL yang valid diawali http:// atau https://.',
        ],
        'logo_path' => [
            'max_length'  => '{field} tidak valid.',
            'regex_match' => '{field} tidak valid.',
        ],
        'principal_name' => [
            'max_length' => '{field} maksimal {param} karakter.',
        ],
        'principal_nip' => [
            'max_length'  => '{field} maksimal {param} karakter.',
            'regex_match' => '{field} hanya boleh berisi angka dan spasi.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $beforeInsert = ['prepare'];
    protected $beforeUpdate = ['prepare'];

    /**
     * Baris pengaturan tunggal, atau null jika belum ada.
     */
    public function current(): ?array
    {
        $row = $this->orderBy('id', 'ASC')->first();

        return $row ?: null;
    }

    /**
     * Simpan pengaturan: perbarui baris tunggal jika sudah ada, jika belum ada buat baris pertama.
     */
    public function saveSettings(array $data): bool
    {
        $row = $this->current();

        if ($row === null) {
            return $this->insert($data) !== false;
        }

        return $this->update((int) $row['id'], $data) !== false;
    }

    /**
     * Tolak insert kedua: hanya boleh ada satu baris.
     */
    public function insert($row = null, bool $returnID = true)
    {
        if ($this->db->table($this->table)->countAllResults() > 0) {
            $this->validation->setError('school_name', 'Pengaturan sekolah sudah ada; gunakan pembaruan, bukan data baru.');

            return false;
        }

        return parent::insert($row, $returnID);
    }

    /**
     * Rapikan input: trim, string kosong menjadi NULL.
     */
    protected function prepare(array $data): array
    {
        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        foreach ($data['data'] as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);

                $data['data'][$key] = $value === '' ? null : $value;
            }
        }

        return $data;
    }
}

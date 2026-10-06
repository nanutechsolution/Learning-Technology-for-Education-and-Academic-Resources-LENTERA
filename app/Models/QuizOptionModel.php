<?php

namespace App\Models;

use CodeIgniter\Model;

class QuizOptionModel extends Model
{
    protected $table            = 'quiz_options';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'question_id',
        'option_text',
        'option_key',
        'is_correct',
        'order_number',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'question_id'  => 'required|is_natural_no_zero|is_not_unique[questions.id]',
        'option_text'  => 'required|max_length[2000]',
        'option_key'   => 'required|in_list[A,B,C,D,E]',
        'is_correct'   => 'permit_empty|in_list[0,1]',
        'order_number' => 'required|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'question_id' => [
            'required'           => 'Pertanyaan wajib dipilih.',
            'is_natural_no_zero' => 'Pertanyaan tidak valid.',
            'is_not_unique'      => 'Pertanyaan tidak ditemukan.',
        ],
        'option_text' => [
            'required'   => 'Teks opsi wajib diisi.',
            'max_length' => 'Teks opsi maksimal 2000 karakter.',
        ],
        'option_key' => [
            'required' => 'Kunci opsi wajib diisi.',
            'in_list'  => 'Kunci opsi harus A sampai E.',
        ],
        'is_correct' => [
            'in_list' => 'Penanda jawaban benar tidak valid.',
        ],
        'order_number' => [
            'required'           => 'Nomor urut opsi wajib diisi.',
            'is_natural_no_zero' => 'Nomor urut opsi tidak valid.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Opsi satu pertanyaan, termasuk is_correct (untuk guru), berurutan.
     */
    public function getByQuestion(int $questionId): array
    {
        return $this->where('question_id', $questionId)
            ->orderBy('order_number', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }
}
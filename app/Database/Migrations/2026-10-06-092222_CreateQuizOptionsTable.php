<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuizOptionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'question_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'option_text' => [
                'type' => 'TEXT',
            ],
            'option_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 5,
            ],
            'is_correct' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'order_number' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);

        // Kunci opsi (A, B, C, ...) tidak boleh kembar dalam satu pertanyaan.
        // Unique ini juga melayani foreign key question_id.
        $this->forge->addUniqueKey(['question_id', 'option_key'], 'uq_quiz_options_question_key');

        // Opsi adalah data turunan pertanyaan: ikut terhapus bersama pertanyaan (CASCADE).
        $this->forge->addForeignKey('question_id', 'questions', 'id', 'CASCADE', 'CASCADE', 'fk_quiz_options_question');

        $this->forge->createTable('quiz_options', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('quiz_options', true);
    }
}
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuizAnswersTable extends Migration
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
            'attempt_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'question_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'option_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
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

        // Satu attempt hanya punya satu jawaban per pertanyaan.
        // Unique ini juga melayani foreign key attempt_id.
        $this->forge->addUniqueKey(['attempt_id', 'question_id'], 'uq_quiz_answers_attempt_question');
        $this->forge->addKey('question_id', false, false, 'idx_quiz_answers_question');
        $this->forge->addKey('option_id', false, false, 'idx_quiz_answers_option');

        // Jawaban ikut terhapus bersama attempt atau pertanyaan (CASCADE).
        // Jika opsi yang dipilih dihapus, jawaban tetap ada tetapi option_id menjadi NULL (SET NULL),
        // mengikuti pola students.class_id.
        $this->forge->addForeignKey('attempt_id', 'quiz_attempts', 'id', 'CASCADE', 'CASCADE', 'fk_quiz_answers_attempt');
        $this->forge->addForeignKey('question_id', 'questions', 'id', 'CASCADE', 'CASCADE', 'fk_quiz_answers_question');
        $this->forge->addForeignKey('option_id', 'quiz_options', 'id', 'CASCADE', 'SET NULL', 'fk_quiz_answers_option');

        $this->forge->createTable('quiz_answers', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('quiz_answers', true);
    }
}
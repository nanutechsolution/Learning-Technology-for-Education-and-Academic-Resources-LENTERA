<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuizAttemptsTable extends Migration
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
            'quiz_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'student_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
            ],
            'submitted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['in_progress', 'submitted'],
                'default'    => 'in_progress',
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

        // Satu siswa hanya punya satu attempt per quiz (tanpa retake).
        // Unique ini juga melayani foreign key quiz_id.
        $this->forge->addUniqueKey(['quiz_id', 'student_id'], 'uq_quiz_attempts_quiz_student');
        $this->forge->addKey('student_id', false, false, 'idx_quiz_attempts_student');

        // Data turunan: ikut terhapus bersama quiz atau siswa (CASCADE).
        $this->forge->addForeignKey('quiz_id', 'quizzes', 'id', 'CASCADE', 'CASCADE', 'fk_quiz_attempts_quiz');
        $this->forge->addForeignKey('student_id', 'students', 'id', 'CASCADE', 'CASCADE', 'fk_quiz_attempts_student');

        $this->forge->createTable('quiz_attempts', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('quiz_attempts', true);
    }
}
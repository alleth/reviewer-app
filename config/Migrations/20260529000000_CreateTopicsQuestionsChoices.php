<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateTopicsQuestionsChoices extends AbstractMigration
{
    public function change(): void
    {
        $this->table('topics', ['id' => false, 'primary_key' => ['topic_id']])
            ->addColumn('topic_id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->create();

        $this->table('questions', ['id' => false, 'primary_key' => ['question_id']])
            ->addColumn('question_id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('topic_id', 'integer', ['signed' => false])
            ->addColumn('question_text', 'text', [])
            ->addColumn('difficulty', 'tinyinteger', ['signed' => false, 'default' => 1])
            ->addColumn('explanation', 'text', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['topic_id'])
            ->addForeignKey('topic_id', 'topics', 'topic_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();

        $this->table('choices', ['id' => false, 'primary_key' => ['choice_id']])
            ->addColumn('choice_id', 'integer', ['identity' => true, 'signed' => false])
            ->addColumn('question_id', 'integer', ['signed' => false])
            ->addColumn('choice_text', 'text', [])
            ->addColumn('is_correct', 'boolean', ['default' => false])
            ->addColumn('sort_order', 'tinyinteger', ['signed' => false, 'default' => 0])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('modified', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['question_id'])
            ->addForeignKey('question_id', 'questions', 'question_id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}

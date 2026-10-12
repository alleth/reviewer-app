<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * One question under topic 1 (choices in ChoicesFixture).
 */
class QuestionsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'question_id' => 1,
                'topic_id' => 1,
                'question_text' => 'What is 12 x 12?',
                'difficulty' => 1,
                'explanation' => '12 x 12 = 144.',
                'created' => '2026-09-01 00:00:00',
                'modified' => '2026-09-01 00:00:00',
            ],
        ];
        parent::init();
    }
}

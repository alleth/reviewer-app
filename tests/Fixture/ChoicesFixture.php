<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Two choices for question 1; choice 1 is correct.
 */
class ChoicesFixture extends TestFixture
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
                'choice_id' => 1,
                'question_id' => 1,
                'choice_text' => '144',
                'is_correct' => true,
                'sort_order' => 0,
                'created' => '2026-09-01 00:00:00',
                'modified' => '2026-09-01 00:00:00',
            ],
            [
                'choice_id' => 2,
                'question_id' => 1,
                'choice_text' => '124',
                'is_correct' => false,
                'sort_order' => 1,
                'created' => '2026-09-01 00:00:00',
                'modified' => '2026-09-01 00:00:00',
            ],
        ];
        parent::init();
    }
}

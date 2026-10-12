<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * The study write-up for topic 1.
 */
class TopicReviewsFixture extends TestFixture
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
                'topic_review_id' => 1,
                'topic_id' => 1,
                'author_id' => null,
                'content' => "Multiply the tens first.\n\nThen add the remainder.",
                'created' => '2026-09-01 00:00:00',
                'modified' => '2026-09-01 00:00:00',
            ],
        ];
        parent::init();
    }
}

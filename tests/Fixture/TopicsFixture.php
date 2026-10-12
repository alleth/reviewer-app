<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * One topic, so content endpoints have something to return.
 */
class TopicsFixture extends TestFixture
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
                'topic_id' => 1,
                'name' => 'Numerical Reasoning',
                'description' => 'Arithmetic, ratios and number series.',
                'created' => '2026-09-01 00:00:00',
                'modified' => '2026-09-01 00:00:00',
            ],
        ];
        parent::init();
    }
}

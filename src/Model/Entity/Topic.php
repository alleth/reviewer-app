<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $topic_id
 * @property string $name
 * @property string|null $description
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property \App\Model\Entity\Question[] $questions
 */
class Topic extends Entity
{
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'questions' => true,
    ];
}

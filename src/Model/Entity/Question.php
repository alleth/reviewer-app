<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $question_id
 * @property int $topic_id
 * @property string $question_text
 * @property int $difficulty
 * @property string|null $explanation
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property \App\Model\Entity\Topic $topic
 * @property \App\Model\Entity\Choice[] $choices
 */
class Question extends Entity
{
    protected array $_accessible = [
        'topic_id' => true,
        'question_text' => true,
        'difficulty' => true,
        'explanation' => true,
        'topic' => true,
        'choices' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $choice_id
 * @property int $question_id
 * @property string $choice_text
 * @property bool $is_correct
 * @property int $sort_order
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property \App\Model\Entity\Question $question
 */
class Choice extends Entity
{
    protected array $_accessible = [
        'question_id' => true,
        'choice_text' => true,
        'is_correct' => true,
        'sort_order' => true,
        'question' => true,
    ];
}

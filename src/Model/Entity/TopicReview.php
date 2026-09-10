<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * A topic's study/explainer write-up — distinct from its practice questions.
 * `author_id` is nullable: null means site-authored (v1); later this can
 * point at the professional who wrote the module.
 *
 * @property int $topic_review_id
 * @property int $topic_id
 * @property int|null $author_id
 * @property string $content
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property \App\Model\Entity\Topic $topic
 * @property \App\Model\Entity\User|null $author
 */
class TopicReview extends Entity
{
    protected array $_accessible = [
        'topic_id' => true,
        'author_id' => true,
        'content' => true,
        'topic' => true,
        'author' => true,
    ];
}

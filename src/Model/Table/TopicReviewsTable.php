<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TopicReviewsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('topic_reviews');
        $this->setPrimaryKey('topic_review_id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Topics', [
            'foreignKey' => 'topic_id',
            'joinType' => 'INNER',
        ]);

        // Nullable for now (v1 content is site-authored); will point at the
        // professional who wrote the module once multi-author content ships.
        $this->belongsTo('Authors', [
            'className' => 'Users',
            'foreignKey' => 'author_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('topic_id')
            ->notEmptyString('topic_id');

        $validator
            ->integer('author_id')
            ->allowEmptyString('author_id');

        $validator
            ->scalar('content')
            ->requirePresence('content', 'create')
            ->notEmptyString('content');

        return $validator;
    }
}

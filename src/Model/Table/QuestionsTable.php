<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class QuestionsTable extends Table
{
    // difficulty: 1 = easy, 2 = medium, 3 = hard
    public const DIFFICULTY_EASY = 1;
    public const DIFFICULTY_MEDIUM = 2;
    public const DIFFICULTY_HARD = 3;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('questions');
        $this->setDisplayField('question_text');
        $this->setPrimaryKey('question_id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Topics', [
            'foreignKey' => 'topic_id',
            'joinType' => 'INNER',
        ]);

        $this->hasMany('Choices', [
            'foreignKey' => 'question_id',
            'sort' => ['Choices.sort_order' => 'ASC'],
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('topic_id')
            ->notEmptyString('topic_id');

        $validator
            ->scalar('question_text')
            ->requirePresence('question_text', 'create')
            ->notEmptyString('question_text');

        $validator
            ->integer('difficulty')
            ->inList('difficulty', [self::DIFFICULTY_EASY, self::DIFFICULTY_MEDIUM, self::DIFFICULTY_HARD])
            ->notEmptyString('difficulty');

        $validator
            ->scalar('explanation')
            ->allowEmptyString('explanation');

        return $validator;
    }
}

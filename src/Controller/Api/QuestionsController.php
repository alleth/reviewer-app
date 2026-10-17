<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Entity\Question;
use App\Model\Table\QuestionsTable;
use Cake\Http\Response;

/**
 * The question bank, reachable only as practice sets and one-at-a-time answer
 * checks (H2). There is deliberately no index/view: the whole bank used to be
 * one GET away, answer key included.
 *
 * - practice(): random questions with shuffled choices, **without** the
 *   answer key (`is_correct`) or the explanation — both are hidden on the
 *   entities. The served question ids are remembered in the session.
 * - answer(): checks one served question's answer on the server and only
 *   then returns the correct choice and the explanation.
 *
 * Access (session + active pass) is enforced by Api\AppController.
 *
 * The server keeps the answer for everything it serves, and every attempt
 * passes through answer(): that is the seam for later per-attempt analytics
 * and for generated (not stored) questions.
 */
class QuestionsController extends AppController
{
    /** Session key holding the question ids this session was served. */
    private const SERVED_KEY = 'Practice.served';

    /** How many recently served ids are kept (oldest drop off). */
    private const SERVED_LIMIT = 200;

    protected QuestionsTable $Questions;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        /** @var \App\Model\Table\QuestionsTable $questions */
        $questions = $this->fetchTable('Questions');
        $this->Questions = $questions;
    }

    /**
     * GET /api/questions/practice?topic_id=&limit= — up to `limit` (1–50,
     * default 20) random questions, choices shuffled, no answer key.
     *
     * @return \Cake\Http\Response
     */
    public function practice(): Response
    {
        $topicId = $this->request->getQuery('topic_id');
        $limit = min(max((int)($this->request->getQuery('limit') ?? 20), 1), 50);

        $query = $this->Questions->find()
            ->contain(['Topics', 'Choices'])
            ->limit($limit);
        $query->orderBy($query->func()->rand());

        if ($topicId !== null) {
            $query->where(['Questions.topic_id' => (int)$topicId]);
        }

        $questions = [];
        foreach ($query->all() as $question) {
            /** @var \App\Model\Entity\Question $question */
            $choices = $question->choices;
            shuffle($choices);
            $question->choices = $choices;
            $questions[] = $question;
        }

        $this->rememberServed(array_map(fn(Question $q): int => (int)$q->question_id, $questions));

        return $this->json(['success' => true, 'data' => $questions]);
    }

    /**
     * POST /api/questions/:id/answer — body `{ choice_id }`. Only for a
     * question this session was served by practice(); answers whether the
     * choice is right, which choice is, and the explanation. Writes nothing.
     *
     * A question that wasn't served gets 403 `not_served` whether or not it
     * exists, so this can't be used to probe ids or walk the bank's key.
     *
     * @param string $id Question id from the route.
     * @return \Cake\Http\Response
     */
    public function answer(string $id): Response
    {
        $this->request->allowMethod(['post']);
        $blocked = $this->requireAjaxHeader();
        if ($blocked !== null) {
            return $blocked;
        }

        $questionId = (int)$id;
        if (!in_array($questionId, $this->served(), true)) {
            return $this->json([
                'success' => false,
                'reason' => 'not_served',
                'message' => 'Answer questions from a practice set.',
            ], 403);
        }

        /** @var \App\Model\Entity\Question|null $question */
        $question = $this->Questions->find()
            ->contain(['Choices'])
            ->where(['Questions.question_id' => $questionId])
            ->first();
        if ($question === null) {
            return $this->json(['success' => false, 'message' => 'Question not found.'], 404);
        }

        $choiceId = filter_var($this->request->getData('choice_id'), FILTER_VALIDATE_INT);
        $chosen = null;
        $correctChoiceId = null;
        foreach ($question->choices as $choice) {
            if ((int)$choice->choice_id === $choiceId) {
                $chosen = $choice;
            }
            if ($choice->is_correct) {
                $correctChoiceId = (int)$choice->choice_id;
            }
        }
        if ($chosen === null) {
            return $this->json(['success' => false, 'message' => 'That choice is not part of this question.'], 422);
        }

        return $this->json([
            'success' => true,
            'question_id' => $questionId,
            'correct' => (bool)$chosen->is_correct,
            'correct_choice_id' => $correctChoiceId,
            'explanation' => $question->explanation,
        ]);
    }

    /**
     * Question ids this session was served, most recent last.
     *
     * @return list<int>
     */
    private function served(): array
    {
        $served = $this->request->getSession()->read(self::SERVED_KEY);

        return is_array($served) ? array_values(array_map('intval', $served)) : [];
    }

    /**
     * Adds ids to the served list, keeping only the most recent SERVED_LIMIT.
     *
     * @param list<int> $ids Question ids just served.
     * @return void
     */
    private function rememberServed(array $ids): void
    {
        $served = array_values(array_diff($this->served(), $ids));
        $served = array_slice([...$served, ...$ids], -self::SERVED_LIMIT);
        $this->request->getSession()->write(self::SERVED_KEY, $served);
    }
}

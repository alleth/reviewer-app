<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;

class QuestionsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Questions = $this->fetchTable('Questions');
    }

    public function index(): void
    {
        $query = $this->Questions->find()->contain(['Topics', 'Choices']);

        $topicId = $this->request->getQuery('topic_id');
        if ($topicId !== null) {
            $query->where(['Questions.topic_id' => (int)$topicId]);
        }

        $questions = $query->orderBy(['Questions.question_id' => 'ASC'])->all();

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'data' => $questions]));
        $this->autoRender = false;
    }

    public function view(int $id): void
    {
        $question = $this->Questions->get($id, contain: ['Topics', 'Choices']);

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'data' => $question]));
        $this->autoRender = false;
    }

    public function add(): void
    {
        $this->request->allowMethod(['post']);

        $question = $this->Questions->newEmptyEntity();
        $question = $this->Questions->patchEntity(
            $question,
            $this->request->getData(),
            ['associated' => ['Choices']]
        );

        if ($this->Questions->save($question, ['associated' => ['Choices']])) {
            $question = $this->Questions->get($question->question_id, contain: ['Topics', 'Choices']);
            $this->response = $this->response
                ->withStatus(201)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'data' => $question]));
        } else {
            $this->response = $this->response
                ->withStatus(422)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'errors' => $question->getErrors()]));
        }

        $this->autoRender = false;
    }

    public function edit(int $id): void
    {
        $this->request->allowMethod(['put', 'patch']);

        $question = $this->Questions->get($id, contain: ['Choices']);
        $question = $this->Questions->patchEntity(
            $question,
            $this->request->getData(),
            ['associated' => ['Choices']]
        );

        if ($this->Questions->save($question, ['associated' => ['Choices']])) {
            $question = $this->Questions->get($id, contain: ['Topics', 'Choices']);
            $this->response = $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'data' => $question]));
        } else {
            $this->response = $this->response
                ->withStatus(422)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'errors' => $question->getErrors()]));
        }

        $this->autoRender = false;
    }

    public function delete(int $id): void
    {
        $this->request->allowMethod(['delete']);

        $question = $this->Questions->get($id);

        if ($this->Questions->delete($question)) {
            $this->response = $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true]));
        } else {
            $this->response = $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Could not delete question']));
        }

        $this->autoRender = false;
    }

    public function practice(): void
    {
        $topicId = $this->request->getQuery('topic_id');
        $limit = min(max((int)($this->request->getQuery('limit') ?? 20), 1), 50);

        $query = $this->Questions->find()
            ->contain(['Topics', 'Choices'])
            ->orderByRand()
            ->limit($limit);

        if ($topicId !== null) {
            $query->where(['Questions.topic_id' => (int)$topicId]);
        }

        $questions = $query->all()->map(function ($question) {
            shuffle($question->choices);
            return $question;
        })->toArray();

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'data' => array_values($questions)]));
        $this->autoRender = false;
    }
}

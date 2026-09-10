<?php
declare(strict_types=1);

namespace App\Controller\Api;

class TopicsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Topics = $this->fetchTable('Topics');
    }

    public function index(): void
    {
        $topics = $this->Topics->find()->orderBy(['Topics.name' => 'ASC'])->all();

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'data' => $topics]));
        $this->autoRender = false;
    }

    public function view(int $id): void
    {
        $topic = $this->Topics->get($id, contain: ['Questions', 'TopicReviews']);

        $this->response = $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true, 'data' => $topic]));
        $this->autoRender = false;
    }

    public function add(): void
    {
        $this->request->allowMethod(['post']);

        $topic = $this->Topics->newEmptyEntity();
        $topic = $this->Topics->patchEntity($topic, $this->request->getData());

        if ($this->Topics->save($topic)) {
            $this->response = $this->response
                ->withStatus(201)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'data' => $topic]));
        } else {
            $this->response = $this->response
                ->withStatus(422)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'errors' => $topic->getErrors()]));
        }

        $this->autoRender = false;
    }

    public function edit(int $id): void
    {
        $this->request->allowMethod(['put', 'patch']);

        $topic = $this->Topics->get($id);
        $topic = $this->Topics->patchEntity($topic, $this->request->getData());

        if ($this->Topics->save($topic)) {
            $this->response = $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'data' => $topic]));
        } else {
            $this->response = $this->response
                ->withStatus(422)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'errors' => $topic->getErrors()]));
        }

        $this->autoRender = false;
    }

    public function delete(int $id): void
    {
        $this->request->allowMethod(['delete']);

        $topic = $this->Topics->get($id);

        if ($this->Topics->delete($topic)) {
            $this->response = $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true]));
        } else {
            $this->response = $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Could not delete topic']));
        }

        $this->autoRender = false;
    }
}

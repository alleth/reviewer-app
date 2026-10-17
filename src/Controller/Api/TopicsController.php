<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Table\TopicsTable;
use Cake\Http\Response;

/**
 * Reviewer topics. Reads need a session and an active pass, and the write
 * actions are disabled (403) — both enforced by Api\AppController.
 */
class TopicsController extends AppController
{
    protected TopicsTable $Topics;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        /** @var \App\Model\Table\TopicsTable $topics */
        $topics = $this->fetchTable('Topics');
        $this->Topics = $topics;
    }

    /**
     * GET /api/topics — every topic, by name.
     *
     * @return \Cake\Http\Response
     */
    public function index(): Response
    {
        $topics = $this->Topics->find()->orderBy(['Topics.name' => 'ASC'])->all();

        return $this->json(['success' => true, 'data' => $topics]);
    }

    /**
     * GET /api/topics/:id — one topic with its study write-up. Its questions
     * are deliberately not included (H2): the page doesn't use them, and they
     * used to carry every explanation in the topic.
     *
     * @param string $id Topic id from the route.
     * @return \Cake\Http\Response
     */
    public function view(string $id): Response
    {
        $topic = $this->Topics->get((int)$id, contain: ['TopicReviews']);

        return $this->json(['success' => true, 'data' => $topic]);
    }

    /**
     * Disabled by Api\AppController (403); kept only for the resource route.
     *
     * @return \Cake\Http\Response
     */
    public function add(): Response
    {
        $this->request->allowMethod(['post']);
        $topic = $this->Topics->patchEntity($this->Topics->newEmptyEntity(), $this->request->getData());
        if (!$this->Topics->save($topic)) {
            return $this->json(['success' => false, 'errors' => $topic->getErrors()], 422);
        }

        return $this->json(['success' => true, 'data' => $topic], 201);
    }

    /**
     * Disabled by Api\AppController (403); kept only for the resource route.
     *
     * @param string $id Topic id from the route.
     * @return \Cake\Http\Response
     */
    public function edit(string $id): Response
    {
        $this->request->allowMethod(['put', 'patch']);
        $topic = $this->Topics->patchEntity($this->Topics->get((int)$id), $this->request->getData());
        if (!$this->Topics->save($topic)) {
            return $this->json(['success' => false, 'errors' => $topic->getErrors()], 422);
        }

        return $this->json(['success' => true, 'data' => $topic]);
    }

    /**
     * Disabled by Api\AppController (403); kept only for the resource route.
     *
     * @param string $id Topic id from the route.
     * @return \Cake\Http\Response
     */
    public function delete(string $id): Response
    {
        $this->request->allowMethod(['delete']);
        if (!$this->Topics->delete($this->Topics->get((int)$id))) {
            return $this->json(['success' => false, 'message' => 'Could not delete topic'], 500);
        }

        return $this->json(['success' => true]);
    }
}

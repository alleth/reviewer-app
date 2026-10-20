<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Table\TopicsTable;
use Cake\Http\Response;

/**
 * Reviewer topics, read-only. Reads need a session and an active pass
 * (enforced by Api\AppController); there are no write routes or actions (H6).
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
}

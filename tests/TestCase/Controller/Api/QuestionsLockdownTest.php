<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Test\TestSuite\ApiTestTrait;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * H2: the question bank can't be downloaded. There is no index/view, practice
 * sets carry no answer key or explanations, and answers are checked one
 * question at a time on the server — only for questions this session was
 * served.
 *
 * Fixtures: question 1 ("What is 12 x 12?", topic 1) with choices 1 (correct,
 * "144") and 2 (wrong), explanation "12 x 12 = 144.".
 *
 * IntegrationTestTrait doesn't carry the session from one request to the
 * next, so servePractice() copies the served-ids list back in the way a
 * browser's session cookie would. It reads it straight from `$_SESSION`: on
 * the CLI a request that completes closes the session, and reading it back
 * through `$this->_requestSession` restarts it, which empties `$_SESSION`.
 *
 * @uses \App\Controller\Api\QuestionsController
 * @uses \App\Controller\Api\TopicsController
 */
class QuestionsLockdownTest extends TestCase
{
    use ApiTestTrait;
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Passes',
        'app.Topics',
        'app.Questions',
        'app.Choices',
        'app.TopicReviews',
    ];

    private function givePass(): void
    {
        $passes = $this->getTableLocator()->get('Passes');
        $passes->saveOrFail($passes->newEntity([
            'user_id' => 1,
            'reviewer' => 'civil-service',
            'plan_id' => '30-day',
            'amount' => '149.00',
            'currency' => 'PHP',
            'external_id' => 'cp_test_' . bin2hex(random_bytes(6)),
            'status' => 'paid',
            'paid_at' => new DateTime('-1 day'),
            'expires_at' => new DateTime('+29 days'),
        ], ['accessibleFields' => ['*' => true]]));
    }

    /** Signed in as user 1 with an active pass. */
    private function asPassHolder(): void
    {
        $this->givePass();
        $this->loginAs(1);
    }

    /**
     * GETs a practice set and carries the served ids into later requests.
     *
     * @return list<array<string, mixed>>
     */
    private function servePractice(): array
    {
        $this->asAjax();
        $this->get('/api/questions/practice?limit=5');
        $this->assertResponseOk();
        $served = $this->servedInSession();
        $this->assertIsArray($served, 'practice() should record the served ids in the session');
        $this->session(['Practice' => ['served' => $served]]);

        return $this->responseJson()['data'];
    }

    /**
     * The served-ids list the last request left in the session (see the class
     * comment for why this reads `$_SESSION` directly).
     *
     * @return mixed
     */
    private function servedInSession(): mixed
    {
        return $_SESSION['Practice']['served'] ?? null;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function answer(int $questionId, array $body): void
    {
        $this->asAjax();
        $this->post("/api/questions/$questionId/answer", $body);
    }

    public function testTheBankCanNoLongerBeListedOrFetchedById(): void
    {
        $this->asPassHolder();
        // The 404 production serves. The debug error page quotes source lines
        // from the stack, including this test's own needle below.
        Configure::write('debug', false);

        foreach (['/api/questions', '/api/questions/1'] as $url) {
            $this->asAjax();
            $this->get($url);
            $this->assertResponseCode(404, $url);
            $this->assertStringNotContainsString('12 x 12 = 144', (string)$this->_response->getBody(), $url);
        }
    }

    public function testPracticeCarriesNoAnswerKeyOrExplanation(): void
    {
        $this->asPassHolder();

        $questions = $this->servePractice();

        $raw = (string)$this->_response->getBody();
        $this->assertStringNotContainsString('is_correct', $raw);
        $this->assertStringNotContainsString('explanation', $raw);
        $this->assertStringNotContainsString('12 x 12 = 144', $raw);
        $this->assertSame(1, $questions[0]['question_id']);
        $this->assertSame('What is 12 x 12?', $questions[0]['question_text']);
        $this->assertCount(2, $questions[0]['choices']);
        $this->assertArrayHasKey('choice_text', $questions[0]['choices'][0]);
        $this->assertSame([1], $this->servedInSession());
    }

    public function testTheRightChoiceIsConfirmedWithTheExplanation(): void
    {
        $this->asPassHolder();
        $this->servePractice();

        $this->answer(1, ['choice_id' => 1]);

        $this->assertResponseOk();
        $this->assertSame([
            'success' => true,
            'question_id' => 1,
            'correct' => true,
            'correct_choice_id' => 1,
            'explanation' => '12 x 12 = 144.',
        ], $this->responseJson());
    }

    public function testAWrongChoiceRevealsTheRightOneAndCanBeAnsweredAgain(): void
    {
        $this->asPassHolder();
        $this->servePractice();

        $this->answer(1, ['choice_id' => 2]);
        $this->assertResponseOk();
        $this->assertFalse($this->responseJson()['correct']);
        $this->assertSame(1, $this->responseJson()['correct_choice_id']);
        $this->assertSame('12 x 12 = 144.', $this->responseJson()['explanation']);

        // Answering again is allowed (D3): nothing more to give away.
        $this->answer(1, ['choice_id' => 1]);
        $this->assertResponseOk();
        $this->assertTrue($this->responseJson()['correct']);
    }

    public function testAQuestionThatWasNotServedCannotBeAnswered(): void
    {
        $this->asPassHolder();

        // Never served, and an id that doesn't exist: the same answer, so the
        // endpoint can't be used to walk the bank or probe ids.
        foreach ([1, 999] as $id) {
            $this->answer($id, ['choice_id' => 1]);
            $this->assertResponseCode(403, "question $id");
            $this->assertSame('not_served', $this->responseJson()['reason'] ?? null);
            $this->assertArrayNotHasKey('correct_choice_id', $this->responseJson());
            $this->assertArrayNotHasKey('explanation', $this->responseJson());
        }
    }

    public function testAServedQuestionDeletedSinceIs404(): void
    {
        $this->asPassHolder();
        $this->servePractice();
        $questions = $this->getTableLocator()->get('Questions');
        $questions->delete($questions->get(1));

        $this->answer(1, ['choice_id' => 1]);

        $this->assertResponseCode(404);
    }

    public function testAChoiceFromElsewhereIsRefused(): void
    {
        $this->asPassHolder();
        $this->servePractice();

        foreach ([['choice_id' => 999], ['choice_id' => 'abc'], []] as $body) {
            $this->answer(1, $body);
            $this->assertResponseCode(422);
            $this->assertArrayNotHasKey('correct_choice_id', $this->responseJson());
        }
    }

    public function testAnsweringNeedsTheAjaxHeaderAndAPost(): void
    {
        $this->asPassHolder();
        $this->servePractice();

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/api/questions/1/answer', ['choice_id' => 1]);
        $this->assertResponseCode(403);

        $this->asAjax();
        $this->get('/api/questions/1/answer');
        $this->assertResponseCode(405);
    }

    public function testAnsweringFollowsTheSessionAndPassRules(): void
    {
        // No session at all.
        $this->answer(1, ['choice_id' => 1]);
        $this->assertResponseCode(401);
        $this->assertSame('signed_out', $this->responseJson()['reason'] ?? null);

        // Signed in, no pass.
        $this->loginAs(1);
        $this->session(['Practice' => ['served' => [1]]]);
        $this->answer(1, ['choice_id' => 1]);
        $this->assertResponseCode(403);
        $this->assertSame('no_active_pass', $this->responseJson()['reason'] ?? null);
    }

    public function testTheServedListKeepsOnlyTheMostRecent200(): void
    {
        $this->asPassHolder();
        $this->session(['Practice' => ['served' => range(1000, 1199)]]);

        $this->servePractice();

        $served = $this->servedInSession();
        $this->assertIsArray($served);
        $this->assertCount(200, $served);
        $this->assertSame(1, end($served), 'The newest id is last');
        $this->assertNotContains(1000, $served, 'The oldest id dropped off');
    }

    public function testATopicPageNoLongerCarriesItsQuestions(): void
    {
        $this->asPassHolder();
        $this->asAjax();

        $this->get('/api/topics/1');

        $this->assertResponseOk();
        $topic = $this->responseJson()['data'];
        $this->assertArrayNotHasKey('questions', $topic);
        $this->assertSame('Numerical Reasoning', $topic['name']);
        $this->assertNotEmpty($topic['topic_review']['content'] ?? null);
        $this->assertStringNotContainsString('12 x 12 = 144', (string)$this->_response->getBody());
    }
}

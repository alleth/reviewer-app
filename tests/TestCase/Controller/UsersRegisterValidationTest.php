<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * POST /api/register against the users validator (B2): long emails that the
 * old 35-char limit rejected now register, and missing/malformed input comes
 * back as readable field errors instead of a database error.
 */
class UsersRegisterValidationTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->configRequest([
            'headers' => ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'],
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'fname' => 'Juan',
            'lname' => 'Santos',
            'email' => 'juan@example.com',
            'user_name' => 'juansantos',
            'user_pass' => 'Password123',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        return (array)json_decode((string)$this->_response->getBody(), true);
    }

    public function testRegisterAcceptsEmailLongerThanOldLimit(): void
    {
        $email = 'firstname.middlename.lastname.2026@example.com';
        $this->post('/api/register', $this->payload(['email' => $email]));

        $this->assertResponseOk();
        $this->assertTrue($this->body()['success'] ?? null);
        $this->assertSame($email, $this->body()['user']['email'] ?? null);
    }

    public function testRegisterRejectsMissingEmailWithReadableError(): void
    {
        $data = $this->payload();
        unset($data['email']);
        $this->post('/api/register', $data);

        $body = $this->body();
        $this->assertFalse($body['success'] ?? null);
        $this->assertContains('Email is required.', $body['errors']['email'] ?? []);
    }

    public function testRegisterRejectsMalformedEmail(): void
    {
        $this->post('/api/register', $this->payload(['email' => 'not-an-email']));

        $body = $this->body();
        $this->assertFalse($body['success'] ?? null);
        $this->assertContains('Enter a valid email address.', $body['errors']['email'] ?? []);
    }

    public function testRegisterRejectsMissingUserName(): void
    {
        $this->post('/api/register', $this->payload(['user_name' => '']));

        $body = $this->body();
        $this->assertFalse($body['success'] ?? null);
        $this->assertContains('Username is required.', $body['errors']['user_name'] ?? []);
    }
}

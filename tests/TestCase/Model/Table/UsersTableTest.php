<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\UsersTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\UsersTable Test Case
 */
class UsersTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\UsersTable
     */
    protected $Users;

    /**
     * Fixtures
     *
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Users') ? [] : ['className' => UsersTable::class];
        $this->Users = $this->getTableLocator()->get('Users', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Users);

        parent::tearDown();
    }

    /**
     * A complete, valid new-user payload; tests override one field at a time.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function newUserData(array $overrides = []): array
    {
        return $overrides + [
            'fname' => 'Juan',
            'lname' => 'Santos',
            'email' => 'juan@example.com',
            'user_name' => 'juansantos',
        ];
    }

    /**
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testValidNewUserHasNoErrors(): void
    {
        $user = $this->Users->newEntity($this->newUserData());

        $this->assertSame([], $user->getErrors());
    }

    /**
     * Emails up to the VARCHAR(100) column width are accepted — the old 35-char
     * limit rejected ordinary long addresses at register and Google sign-up.
     *
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testEmailLongerThanOldLimitIsAccepted(): void
    {
        $email = str_repeat('a', 40) . '@example.com';
        $user = $this->Users->newEntity($this->newUserData(['email' => $email]));

        $this->assertArrayNotHasKey('email', $user->getErrors());
    }

    /**
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testEmailAtColumnWidthIsAcceptedAndOneOverIsRejected(): void
    {
        $domain = '@example.com';
        $atLimit = str_repeat('a', 100 - strlen($domain)) . $domain;
        $overLimit = 'a' . $atLimit;

        $ok = $this->Users->newEntity($this->newUserData(['email' => $atLimit]));
        $tooLong = $this->Users->newEntity($this->newUserData(['email' => $overLimit]));

        $this->assertArrayNotHasKey('email', $ok->getErrors());
        $this->assertArrayHasKey('maxLength', $tooLong->getErrors()['email'] ?? []);
    }

    /**
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testEmailIsRequiredOnCreate(): void
    {
        $data = $this->newUserData();
        unset($data['email']);

        $missing = $this->Users->newEntity($data);
        $empty = $this->Users->newEntity($this->newUserData(['email' => '']));

        $this->assertArrayHasKey('email', $missing->getErrors());
        $this->assertArrayHasKey('email', $empty->getErrors());
    }

    /**
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testMalformedEmailIsRejected(): void
    {
        $user = $this->Users->newEntity($this->newUserData(['email' => 'not-an-email']));

        $this->assertSame('Enter a valid email address.', $user->getErrors()['email']['email'] ?? null);
    }

    /**
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testUserNameIsRequiredOnCreate(): void
    {
        $data = $this->newUserData();
        unset($data['user_name']);

        $missing = $this->Users->newEntity($data);
        $empty = $this->Users->newEntity($this->newUserData(['user_name' => '']));

        $this->assertArrayHasKey('user_name', $missing->getErrors());
        $this->assertArrayHasKey('user_name', $empty->getErrors());
    }

    /**
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testNameAndUserNameLengthsMatchColumnWidth(): void
    {
        $ok = $this->Users->newEntity($this->newUserData([
            'fname' => str_repeat('a', 50),
            'lname' => str_repeat('b', 50),
            'user_name' => str_repeat('c', 50),
        ]));
        $tooLong = $this->Users->newEntity($this->newUserData([
            'fname' => str_repeat('a', 51),
            'lname' => str_repeat('b', 51),
            'user_name' => str_repeat('c', 51),
        ]));

        $this->assertSame([], $ok->getErrors());
        $this->assertArrayHasKey('fname', $tooLong->getErrors());
        $this->assertArrayHasKey('lname', $tooLong->getErrors());
        $this->assertArrayHasKey('user_name', $tooLong->getErrors());
    }

    /**
     * Google accounts don't always provide a given/family name, so empty names
     * must stay valid (the columns are NOT NULL, but '' is allowed).
     *
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testEmptyNamesAreAllowed(): void
    {
        $user = $this->Users->newEntity($this->newUserData(['fname' => '', 'lname' => '']));

        $this->assertSame([], $user->getErrors());
    }

    /**
     * Profile edits patch only the fields sent, so an update that leaves email
     * and user_name out must not trip the create-only presence rules.
     *
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testPartialUpdateDoesNotRequireEmailOrUserName(): void
    {
        $user = $this->Users->get(1);
        $user = $this->Users->patchEntity($user, ['fname' => 'Janet']);

        $this->assertSame([], $user->getErrors());
    }

    /**
     * @return void
     * @uses \App\Model\Table\UsersTable::validationDefault()
     */
    public function testUpdateCannotBlankOutEmail(): void
    {
        $user = $this->Users->get(1);
        $user = $this->Users->patchEntity($user, ['email' => '']);

        $this->assertArrayHasKey('email', $user->getErrors());
    }

    /**
     * @return void
     * @uses \App\Model\Table\UsersTable::buildRules()
     */
    public function testDuplicateEmailAndUserNameAreRejectedOnSave(): void
    {
        $user = $this->Users->newEntity($this->newUserData([
            'email' => 'jane@example.com',
            'user_name' => 'janedelacruz',
        ]));

        $this->assertFalse($this->Users->save($user));
        $this->assertArrayHasKey('email', $user->getErrors());
        $this->assertArrayHasKey('user_name', $user->getErrors());
    }

    /**
     * End-to-end: a 40+ character email now saves (it was rejected before B2).
     *
     * @return void
     */
    public function testLongEmailUserSaves(): void
    {
        $user = $this->Users->newEntity($this->newUserData([
            'email' => 'firstname.middlename.lastname.2026@example.com',
        ]));

        $this->assertNotFalse($this->Users->save($user));
    }
}

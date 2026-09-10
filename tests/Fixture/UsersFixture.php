<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 */
class UsersFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'user_id' => 1,
                'fname' => 'Jane',
                'lname' => 'Dela Cruz',
                'email' => 'jane@example.com',
                'user_name' => 'janedelacruz',
                // password_hash('Password123', PASSWORD_DEFAULT) — a fixed digest so
                // tests don't depend on the current bcrypt cost factor at runtime.
                'user_pass' => '$2y$10$DrB7rgOd2Iw/97bjgjwb5uBFUD48L.uZPkAypF0H7pCPgedkeBhP.',
                'google_id' => null,
                'session_token' => null,
            ],
        ];
        parent::init();
    }
}

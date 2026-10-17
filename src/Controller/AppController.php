<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;

/**
 * Base for every controller. SessionAuthTrait gives them all the same
 * session check (activeUserId / notLoggedIn), CSRF gate (requireAjaxHeader)
 * and JSON helper.
 */
class AppController extends Controller
{
    use SessionAuthTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Flash');
    }
}

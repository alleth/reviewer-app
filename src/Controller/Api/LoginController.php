<?php
namespace App\Controller\Api;

use Cake\Controller\Controller;

class LoginController extends Controller
{
    public function sessionCheck()
    {
        $loggedIn = $this->request->getSession()->check('Auth.User'); // Adjust based on your login setup
        $this->set([
            'loggedIn' => $loggedIn,
            '_serialize' => ['loggedIn']
        ]);
    }

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        // Authentication component can be added if needed for actual authentication
        // $this->loadComponent('Authentication.Authentication');
    }

    public function logout()
    {
        // Destroy the session (simulating logout)
        $this->request->getSession()->destroy();
        $this->set([
            'success' => true,
            'message' => 'Logged out',
            '_serialize' => ['success', 'message']
        ]);
    }

    public function options(...$args)
    {
        // Handle preflight OPTIONS request for CORS
        $this->response = $this->response
            ->withHeader('Access-Control-Allow-Origin', '*') // Adjust origin if necessary
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withStatus(200); // Successful response

        return $this->response;
    }
}

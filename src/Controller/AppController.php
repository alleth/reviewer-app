<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Controller\Controller;
use Cake\Http\Response;

/**
 * Application Controller
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        // $this->loadComponent('FormProtection'); // Enable if needed
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        // Set CORS headers
        $this->response = $this->response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');

        // Handle preflight OPTIONS request
        if ($this->request->getMethod() === 'OPTIONS') {
            $this->autoRender = false;
            $this->response = $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['status' => 'OK']));
            // DO NOT RETURN! Just call exit:
            echo $this->response->getBody();
            exit(); // important!
        }
    }

    public function options(): Response
    {
        return $this->response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withStatus(200);
    }

    public function logout(): Response
    {
        $this->request->getSession()->destroy();

        return $this->response
            ->withType('application/json')
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withStringBody(json_encode([
                'success' => true,
                'message' => 'Logged out'
            ]));
    }
}

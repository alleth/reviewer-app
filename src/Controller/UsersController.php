<?php

namespace App\Controller;

use Cake\Http\Response;

class UsersController extends AppController
{
    public function login()
    {
        $this->request->allowMethod(['post']);

        // Allow CORS for development
        $this->response = $this->response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true');

        // Automatically parses JSON or form data
        $data = $this->request->getData();

        $username = $data['user_name'] ?? '';
        $password = $data['user_pass'] ?? '';

        $user = $this->Users->find()->where(['user_name' => $username])->first();

        if ($user && password_verify($password, $user->user_pass)) {
            $this->request->getSession()->write('Auth.User', $user);
            $response = ['success' => true, 'user' => $user];
        } else {
            $response = ['success' => false, 'message' => 'Invalid username or password'];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));
    }

    public function session(): \Cake\Http\Response
    {
        $user = $this->request->getSession()->read('Auth.User');

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'loggedIn' => $user !== null,
                'user' => $user,
            ]));
    }

    public function register()
    {
        $this->request->allowMethod(['post']);

        $this->response = $this->response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');

        $data = $this->request->getData();

        $data['fname'] = $data['fname'] ?? '';
        $data['lname'] = $data['lname'] ?? '';
        $data['email'] = $data['email'] ?? '';
        $data['user_name'] = $data['user_name'] ?? '';
        $data['user_pass'] = password_hash($data['user_pass'], PASSWORD_DEFAULT);

        $user = $this->Users->newEmptyEntity();
        $user = $this->Users->patchEntity($user, $data);

        if ($this->Users->save($user)) {
            $response = ['success' => true, 'user' => $user];
        } else {
            \Cake\Log::write('error', print_r($user->getErrors(), true));
            $response = ['success' => false, 'errors' => $user->getErrors()];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));
    }
}

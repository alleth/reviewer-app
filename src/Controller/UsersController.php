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

        try {
            $data = $this->request->getData();

            $data['fname'] = $data['fname'] ?? '';
            $data['lname'] = $data['lname'] ?? '';
            $data['email'] = $data['email'] ?? '';
            $data['user_name'] = $data['user_name'] ?? '';
            $data['user_pass'] = password_hash($data['user_pass'] ?? '', PASSWORD_DEFAULT);

            $user = $this->Users->newEmptyEntity();
            $user = $this->Users->patchEntity($user, $data);

            if ($this->Users->save($user)) {
                $response = ['success' => true, 'user' => $user];
            } else {
                $response = ['success' => false, 'errors' => $user->getErrors()];
            }
        } catch (\Exception $e) {
            $response = ['success' => false, 'error' => $e->getMessage()];

            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode($response));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));
    }
}

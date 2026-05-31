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

    public function googleLogin(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);

        try {
            $credential = $this->request->getData('credential');
            if (empty($credential)) {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Missing credential']));
            }

            $clientId = env('GOOGLE_CLIENT_ID');
            if (empty($clientId)) {
                return $this->response
                    ->withStatus(500)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Google client not configured']));
            }

            $client = new \Google_Client(['client_id' => $clientId]);
            $payload = $client->verifyIdToken($credential);

            if (!$payload) {
                return $this->response
                    ->withStatus(401)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Invalid Google token']));
            }

            $googleId = $payload['sub'];
            $email = $payload['email'] ?? '';
            $fname = $payload['given_name'] ?? '';
            $lname = $payload['family_name'] ?? '';

            $user = $this->Users->find()->where(['google_id' => $googleId])->first();

            if (!$user) {
                $user = $this->Users->find()->where(['email' => $email])->first();
                if ($user) {
                    $user->google_id = $googleId;
                    $this->Users->save($user);
                }
            }

            if (!$user) {
                $userName = strstr($email, '@', true) ?: 'user' . substr($googleId, 0, 8);
                $suffix = 0;
                $baseName = $userName;
                while ($this->Users->find()->where(['user_name' => $userName])->count() > 0) {
                    $suffix++;
                    $userName = $baseName . $suffix;
                }

                $user = $this->Users->newEntity([
                    'fname' => $fname,
                    'lname' => $lname,
                    'email' => $email,
                    'user_name' => $userName,
                    'google_id' => $googleId,
                ]);

                if (!$this->Users->save($user)) {
                    return $this->response
                        ->withStatus(422)
                        ->withType('application/json')
                        ->withStringBody(json_encode(['success' => false, 'errors' => $user->getErrors()]));
                }
            }

            $this->request->getSession()->write('Auth.User', $user);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'user' => $user]));
        } catch (\Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    public function logout(): \Cake\Http\Response
    {
        $session = $this->request->getSession();
        $session->delete('Auth.User');
        $session->renew();

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => true]));
    }

    public function session(): \Cake\Http\Response
    {
        $user = $this->request->getSession()->read('Auth.User');

        return $this->response
            ->withType('application/json')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->withHeader('Pragma', 'no-cache')
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

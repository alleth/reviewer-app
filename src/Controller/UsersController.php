<?php

namespace App\Controller;

use Cake\Http\Response;

class UsersController extends AppController
{
    /**
     * Lightweight CSRF gate for the state-changing auth actions. This app has no CSRF
     * middleware (removed entirely so the cross-origin SPA can POST at all) and session
     * cookies are SameSite=None (required for the same reason) — so without this, any
     * other website could trigger these actions using a visitor's session cookie via a
     * plain <form> POST.
     *
     * A plain HTML form cannot set custom request headers, only fetch/XHR can — and a
     * cross-origin fetch/XHR triggers a CORS preflight, which CorsMiddleware only allows
     * for FRONTEND_URL. So requiring this header turns the CORS policy we already have
     * into an effective CSRF check, without needing a separate token scheme.
     *
     * @return \Cake\Http\Response|null A 403 response if the header is missing, null to continue.
     */
    private function requireAjaxHeader(): ?\Cake\Http\Response
    {
        if ($this->request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest') {
            return $this->response
                ->withStatus(403)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Missing required request header']));
        }

        return null;
    }

    public function login()
    {
        $this->request->allowMethod(['post']);

        if ($blocked = $this->requireAjaxHeader()) {
            return $blocked;
        }

        // Allow CORS for development
        $this->response = $this->response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true');

        try {
            // Automatically parses JSON or form data
            $data = $this->request->getData();

            $username = $data['user_name'] ?? '';
            $password = $data['user_pass'] ?? '';

            // The frontend advertises "Username or Email" on this field, so match either.
            $user = $this->Users->find()
                ->where(['OR' => ['user_name' => $username, 'email' => $username]])
                ->first();

            if ($user && $user->user_pass !== null && password_verify($password, $user->user_pass)) {
                $this->request->getSession()->write('Auth.User', $user);
                $response = ['success' => true, 'user' => $user];
            } elseif ($user && $user->user_pass === null) {
                // Google-only account: no password was ever set for it. Give the frontend
                // enough to render a personalized "continue with Google to finish setup"
                // prompt instead of a plain error.
                $response = [
                    'success' => false,
                    'needsGoogleSetup' => true,
                    'message' => 'For your security, this account needs a one-time setup. Continue with Google to finish it.',
                    'account' => [
                        'email' => $user->email,
                        'fname' => $user->fname,
                        'lname' => $user->lname,
                    ],
                ];
            } else {
                $response = ['success' => false, 'message' => 'Invalid username or password'];
            }
        } catch (\Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));
    }

    public function googleLogin(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);

        if ($blocked = $this->requireAjaxHeader()) {
            return $blocked;
        }

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

            $isNewUser = false;

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

                $isNewUser = true;
            }

            $this->request->getSession()->write('Auth.User', $user);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'user' => $user, 'isNewUser' => $isNewUser]));
        } catch (\Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    /**
     * Lets a logged-in Google-only account (user_pass is null) set a password for the
     * first time, and touch up the profile fields Google login auto-derived. Refuses to
     * run once a password already exists — this is first-time setup, not a change-password
     * flow (which would need the current password).
     */
    public function setupAccount(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post']);

        if ($blocked = $this->requireAjaxHeader()) {
            return $blocked;
        }

        $sessionUser = $this->request->getSession()->read('Auth.User');
        if (!$sessionUser) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Not logged in']));
        }

        try {
            $user = $this->Users->get($sessionUser->user_id);

            if ($user->user_pass !== null) {
                return $this->response
                    ->withStatus(409)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'This account already has a password set.']));
            }

            $data = $this->request->getData();
            $password = $data['user_pass'] ?? '';
            if ($password === '') {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Password is required.']));
            }

            $patch = ['user_pass' => password_hash($password, PASSWORD_DEFAULT)];
            foreach (['fname', 'lname', 'user_name'] as $field) {
                if (!empty($data[$field])) {
                    $patch[$field] = $data[$field];
                }
            }

            $user = $this->Users->patchEntity($user, $patch);

            if (!$this->Users->save($user)) {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'errors' => $user->getErrors()]));
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

    /**
     * Updates the logged-in user's profile fields (name, username, email) from
     * the Settings page. Does not touch the password — that's setupAccount() for
     * first-time setup, and a dedicated change-password flow later.
     */
    public function updateAccount(): \Cake\Http\Response
    {
        $this->request->allowMethod(['post', 'put', 'patch']);

        if ($blocked = $this->requireAjaxHeader()) {
            return $blocked;
        }

        $sessionUser = $this->request->getSession()->read('Auth.User');
        if (!$sessionUser) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Not logged in']));
        }

        try {
            $user = $this->Users->get($sessionUser->user_id);

            $data = $this->request->getData();
            $patch = [];
            foreach (['fname', 'lname', 'user_name', 'email'] as $field) {
                if (array_key_exists($field, $data)) {
                    $patch[$field] = trim((string)$data[$field]);
                }
            }

            $user = $this->Users->patchEntity($user, $patch);

            if (!$this->Users->save($user)) {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'errors' => $user->getErrors()]));
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
        $this->request->allowMethod(['post']);

        if ($blocked = $this->requireAjaxHeader()) {
            return $blocked;
        }

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

        if ($blocked = $this->requireAjaxHeader()) {
            return $blocked;
        }

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

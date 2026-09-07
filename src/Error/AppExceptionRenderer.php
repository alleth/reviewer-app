<?php
declare(strict_types=1);

namespace App\Error;

use Cake\Database\Exception\MissingConnectionException;
use Cake\Error\Renderer\WebExceptionRenderer;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Turns "database is unreachable" exceptions into a friendly JSON 503 instead
 * of leaking a raw `SQLSTATE[HY000] [2002] Connection refused` message.
 *
 * Railway's trial plan sleeps the MySQL service when idle (see railway.toml),
 * so the first request after a quiet period can land before the DB is back.
 * App\Database\Driver\Mysql already retries the transient codes; this covers
 * the case where the retries are exhausted before MySQL finishes waking.
 */
class AppExceptionRenderer extends WebExceptionRenderer
{
    /**
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function render(): ResponseInterface
    {
        if ($this->isDatabaseAsleep($this->error)) {
            return $this->controller->getResponse()
                ->withStatus(503)
                ->withHeader('Retry-After', '5')
                ->withType('application/json')
                ->withStringBody((string)json_encode([
                    'success' => false,
                    'message' => 'The server is waking up from sleep. Please try again in a few seconds.',
                ]));
        }

        return parent::render();
    }

    /**
     * Walks the exception chain for the tell-tale signs of an unreachable or
     * still-waking database server.
     *
     * @param \Throwable $error The thrown exception.
     * @return bool
     */
    private function isDatabaseAsleep(Throwable $error): bool
    {
        $needles = ['[2002]', '[2003]', '[2006]', '[2013]', 'Connection refused', 'server has gone away'];

        $current = $error;
        while ($current !== null) {
            if ($current instanceof MissingConnectionException) {
                return true;
            }
            foreach ($needles as $needle) {
                if (str_contains($current->getMessage(), $needle)) {
                    return true;
                }
            }
            $current = $current->getPrevious();
        }

        return false;
    }
}

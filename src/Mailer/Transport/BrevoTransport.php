<?php
declare(strict_types=1);

namespace App\Mailer\Transport;

use Cake\Http\Client;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;
use RuntimeException;

/**
 * Sends mail through Brevo's transactional HTTP API instead of SMTP.
 *
 * Railway blocks all outbound SMTP ports (25/465/587/2525), so CakePHP's
 * SmtpTransport can never connect from a deployed container. This transport
 * POSTs to https://api.brevo.com/v3/smtp/email over 443, which is always
 * reachable. Activated by setting the BREVO_API_KEY env var (see
 * config/app.php -> EmailTransport.default and UsersController::sendMail()).
 */
class BrevoTransport extends AbstractTransport
{
    /**
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'apiKey' => null,
        'url' => 'https://api.brevo.com/v3/smtp/email',
    ];

    /**
     * @param \Cake\Mailer\Message $message Email message.
     * @return array<string, string>
     */
    public function send(Message $message): array
    {
        $this->checkRecipient($message);

        $from = $message->getFrom();
        $fromEmail = (string)array_key_first($from);
        $fromName = $from[$fromEmail] ?? $fromEmail;
        if ($fromName === $fromEmail) {
            $fromName = 'CareerPass';
        }

        $to = [];
        foreach ($message->getTo() as $email => $name) {
            $recipient = ['email' => (string)$email];
            if ($name !== '' && $name !== $email) {
                $recipient['name'] = (string)$name;
            }
            $to[] = $recipient;
        }

        $payload = [
            'sender' => ['email' => $fromEmail, 'name' => $fromName],
            'to' => $to,
            'subject' => $message->getOriginalSubject(),
            'textContent' => $message->getBodyText() ?: ' ',
        ];

        $html = $message->getBodyHtml();
        if ($html !== '') {
            $payload['htmlContent'] = $html;
        }

        $apiKey = (string)$this->getConfig('apiKey');
        if ($apiKey === '') {
            throw new RuntimeException('BrevoTransport: apiKey is not configured.');
        }

        $response = (new Client())->post(
            (string)$this->getConfig('url'),
            $payload,
            [
                'type' => 'json',
                'headers' => ['api-key' => $apiKey, 'accept' => 'application/json'],
            ],
        );

        if (!$response->isOk()) {
            throw new RuntimeException(sprintf(
                'Brevo API responded %d: %s',
                $response->getStatusCode(),
                $response->getStringBody(),
            ));
        }

        return ['headers' => '', 'message' => $response->getStringBody()];
    }
}

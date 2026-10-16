<?php
declare(strict_types=1);

namespace App\Payment;

use RuntimeException;

/**
 * Xendit could not be reached or answered with an error. Usually temporary:
 * the webhook answers 503 so Xendit retries, and the reconcile command tries
 * again on its next run.
 */
class XenditException extends RuntimeException
{
}

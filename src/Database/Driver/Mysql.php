<?php
declare(strict_types=1);

namespace App\Database\Driver;

use Cake\Database\Driver\Mysql as CakeMysql;

/**
 * Retries a handful of transient MySQL connection error codes before giving up.
 *
 * CakePHP's Driver::createPdo() already has retry infrastructure built in
 * (CommandRetry + ErrorCodeWaitStrategy), but the base Mysql driver ships with
 * RETRY_ERROR_CODES = [], so none of it actually fires. Railway's trial-plan
 * scale-to-zero (see railway.toml's sleepApplication) means the first request
 * after an idle period can race the app's own cold start against the MySQL
 * host becoming reachable on Railway's private network, producing
 * "SQLSTATE[HY000] [2002] Connection refused". A few retries with a short
 * wait absorbs that race instead of failing the request outright.
 */
class Mysql extends CakeMysql
{
    /**
     * @var array<int>
     */
    protected const RETRY_ERROR_CODES = [
        2002, // Can't connect (connection refused / no such socket)
        2003, // Can't connect to MySQL server on '<host>'
        2006, // MySQL server has gone away
        2013, // Lost connection to MySQL server during query
    ];
}

<?php
declare(strict_types=1);

namespace App\Auth;

use UnexpectedValueException;

/**
 * The Google credential couldn't be parsed as a token at all (wrong number of
 * segments, bad base64/JSON, unsupported algorithm). That's a bad request from
 * the client, so googleLogin() answers 400 rather than 500.
 */
class InvalidGoogleCredentialException extends UnexpectedValueException
{
}

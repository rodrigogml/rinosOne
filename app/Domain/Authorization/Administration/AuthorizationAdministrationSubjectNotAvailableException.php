<?php

namespace App\Domain\Authorization\Administration;

use LogicException;

/**
 * Signals that a requested subject is not visible in the resolved authorization context.
 */
class AuthorizationAdministrationSubjectNotAvailableException extends LogicException {}

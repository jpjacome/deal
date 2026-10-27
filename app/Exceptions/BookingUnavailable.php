<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A requested booking breaks an availability rule. The message is user-facing (Spanish).
 */
class BookingUnavailable extends RuntimeException {}

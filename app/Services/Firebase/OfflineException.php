<?php

namespace App\Services\Firebase;

use RuntimeException;

/**
 * Firebase could not be reached (no internet, DNS failure, timeout).
 */
class OfflineException extends RuntimeException {}

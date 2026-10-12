<?php

namespace App\Services\AiAssist;

use RuntimeException;

/**
 * Thrown when an AI request cannot produce suggestions. The message is safe to show the admin.
 */
class AiAssistFailed extends RuntimeException {}

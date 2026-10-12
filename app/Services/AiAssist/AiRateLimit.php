<?php

namespace App\Services\AiAssist;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers when an admin's AI rate-limit wait ends, so every editor page load
 * can show the same remaining wait the server enforces.
 *
 * The deadline is taken from the Retry-After the throttle sends, rather than
 * read from the framework's internal limiter key.
 */
class AiRateLimit
{
    /**
     * Record that the admin must wait the given number of seconds.
     */
    public function blockFor(User $user, int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        Cache::put($this->key($user), now()->addSeconds($seconds)->getTimestamp(), $seconds);
    }

    /**
     * Get the whole seconds left before the admin can use the AI again, or 0.
     */
    public function secondsRemaining(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        $endsAt = Cache::get($this->key($user));

        return $endsAt === null ? 0 : max(0, (int) $endsAt - now()->getTimestamp());
    }

    /**
     * Get the cache key holding the admin's wait deadline.
     */
    private function key(User $user): string
    {
        return "ai-assist-blocked:{$user->id}";
    }
}

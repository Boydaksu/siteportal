<?php

declare(strict_types=1);

namespace SitePortal\Core\Security;

use SitePortal\Core\Database\Connection;
use Throwable;

final class RateLimiter
{
    private ?Connection $db;

    /** @var array<string, array{hits: int, reset_at: int}> */
    private array $memoryStorage = [];

    /** @var (callable(): int)|null */
    private $timeProvider = null;

    public function __construct(?Connection $db = null, ?callable $timeProvider = null)
    {
        $this->db = $db;
        $this->timeProvider = $timeProvider;
    }

    public static function createInMemory(?callable $timeProvider = null): self
    {
        return new self(null, $timeProvider);
    }

    /**
     * Increment the counter for a given key for a decay duration.
     *
     * @return int Current number of attempts
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $now = $this->currentTime();
        $hash = $this->hashKey($key);

        if ($this->db !== null) {
            try {
                return $this->hitDatabase($hash, $now, $decaySeconds);
            } catch (Throwable) {
                // Fallback to memory on DB error
            }
        }

        return $this->hitMemory($hash, $now, $decaySeconds);
    }

    /**
     * Get the number of attempts for a given key.
     */
    public function attempts(string $key): int
    {
        $now = $this->currentTime();
        $hash = $this->hashKey($key);

        if ($this->db !== null) {
            try {
                $row = $this->db->fetch(
                    'SELECT hits, reset_at FROM rate_limits WHERE key_hash = :hash',
                    ['hash' => $hash]
                );

                if ($row !== null && (int) $row['reset_at'] > $now) {
                    return (int) $row['hits'];
                }

                if ($row !== null && (int) $row['reset_at'] <= $now) {
                    $this->db->execute('DELETE FROM rate_limits WHERE key_hash = :hash', ['hash' => $hash]);
                }

                return 0;
            } catch (Throwable) {
                // Fallback to memory on DB error
            }
        }

        if (isset($this->memoryStorage[$hash])) {
            if ($this->memoryStorage[$hash]['reset_at'] > $now) {
                return $this->memoryStorage[$hash]['hits'];
            }
            unset($this->memoryStorage[$hash]);
        }

        return 0;
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /**
     * Get the number of remaining attempts for a given key.
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /**
     * Get the number of seconds until the "key" is accessible again (Retry-After).
     */
    public function availableIn(string $key): int
    {
        $now = $this->currentTime();
        $hash = $this->hashKey($key);

        if ($this->db !== null) {
            try {
                $row = $this->db->fetch(
                    'SELECT reset_at FROM rate_limits WHERE key_hash = :hash',
                    ['hash' => $hash]
                );

                if ($row !== null) {
                    $resetAt = (int) $row['reset_at'];
                    return max(0, $resetAt - $now);
                }

                return 0;
            } catch (Throwable) {
                // Fallback to memory on DB error
            }
        }

        if (isset($this->memoryStorage[$hash])) {
            $resetAt = $this->memoryStorage[$hash]['reset_at'];
            return max(0, $resetAt - $now);
        }

        return 0;
    }

    /**
     * Reset the number of attempts for a given key.
     */
    public function resetAttempts(string $key): void
    {
        $hash = $this->hashKey($key);

        if ($this->db !== null) {
            try {
                $this->db->execute('DELETE FROM rate_limits WHERE key_hash = :hash', ['hash' => $hash]);
            } catch (Throwable) {
                // Ignore DB error
            }
        }

        unset($this->memoryStorage[$hash]);
    }

    /**
     * Clear all rate limits (memory and DB).
     */
    public function clear(): void
    {
        if ($this->db !== null) {
            try {
                $this->db->query('DELETE FROM rate_limits');
            } catch (Throwable) {
                // Ignore DB error
            }
        }

        $this->memoryStorage = [];
    }

    private function hitMemory(string $hash, int $now, int $decaySeconds): int
    {
        if (isset($this->memoryStorage[$hash]) && $this->memoryStorage[$hash]['reset_at'] > $now) {
            $this->memoryStorage[$hash]['hits']++;
            return $this->memoryStorage[$hash]['hits'];
        }

        $this->memoryStorage[$hash] = [
            'hits' => 1,
            'reset_at' => $now + $decaySeconds,
        ];

        return 1;
    }

    private function hitDatabase(string $hash, int $now, int $decaySeconds): int
    {
        assert($this->db !== null);

        $row = $this->db->fetch(
            'SELECT hits, reset_at FROM rate_limits WHERE key_hash = :hash',
            ['hash' => $hash]
        );

        if ($row !== null && (int) $row['reset_at'] > $now) {
            $newHits = (int) $row['hits'] + 1;
            $this->db->execute(
                'UPDATE rate_limits SET hits = :hits WHERE key_hash = :hash',
                ['hits' => $newHits, 'hash' => $hash]
            );
            return $newHits;
        }

        $resetAt = $now + $decaySeconds;
        if ($row !== null) {
            $this->db->execute(
                'UPDATE rate_limits SET hits = 1, reset_at = :reset_at WHERE key_hash = :hash',
                ['reset_at' => $resetAt, 'hash' => $hash]
            );
        } else {
            $this->db->execute(
                'INSERT INTO rate_limits (key_hash, hits, reset_at) VALUES (:hash, 1, :reset_at)',
                ['hash' => $hash, 'reset_at' => $resetAt]
            );
        }

        return 1;
    }

    private function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    private function currentTime(): int
    {
        if ($this->timeProvider !== null) {
            return ($this->timeProvider)();
        }

        return time();
    }
}

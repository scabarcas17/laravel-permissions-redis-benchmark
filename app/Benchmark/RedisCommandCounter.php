<?php

declare(strict_types=1);

namespace App\Benchmark;

use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

/**
 * Counts the Redis commands a run sends on the connections the strategies
 * use: `default` (the Redis package) and `cache` (the cache store). Command
 * events are switched on only while counting, so timed runs pay nothing.
 */
class RedisCommandCounter
{
    private const array CONNECTIONS = ['default', 'cache'];

    private int $count = 0;

    private bool $listening = false;

    public function start(): void
    {
        if (! $this->listening) {
            Event::listen(CommandExecuted::class, function (): void {
                $this->count++;
            });

            $this->listening = true;
        }

        $this->count = 0;

        foreach (self::CONNECTIONS as $name) {
            Redis::connection($name)->setEventDispatcher(app('events'));
        }
    }

    public function stop(): int
    {
        foreach (self::CONNECTIONS as $name) {
            Redis::connection($name)->unsetEventDispatcher();
        }

        return $this->count;
    }
}

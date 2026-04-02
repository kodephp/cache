<?php

declare(strict_types=1);

namespace Kode\Cache\Contract;

interface LimiterInterface
{
    public function tooManyAttempts(): bool;

    public function attempts(): int;

    public function hit(): int;

    public function remaining(): int;

    public function reset(): bool;

    public function availableIn(): int;

    public function clear(): bool;
}

<?php

declare(strict_types=1);

namespace Kode\Cache;

use Kode\Cache\Contract\LockInterface;
use Kode\Cache\Contract\StoreInterface;
use Kode\Cache\Exception\CacheException;

class Lock implements LockInterface
{
    protected StoreInterface $store;

    protected string $name;

    protected ?string $token = null;

    protected int $seconds = 0;

    protected bool $owner = false;

    public function __construct(StoreInterface $store, string $name, int $seconds = 0)
    {
        $this->store = $store;
        $this->name = $name;
        $this->seconds = $seconds;
        $this->token = $this->generateToken();
    }

    public function acquire(): bool
    {
        if (method_exists($this->store, 'add')) {
            if ($this->store->add($this->name, $this->token, $this->seconds)) {
                $this->owner = true;
                return true;
            }
        } else {
            if (!$this->store->has($this->name) && $this->store->set($this->name, $this->token, $this->seconds)) {
                $this->owner = true;
                return true;
            }
        }

        return false;
    }

    public function release(): bool
    {
        if (!$this->owner) {
            return false;
        }

        $this->owner = false;
        return $this->store->delete($this->name);
    }

    public function isOwned(): bool
    {
        if (!$this->owner) {
            return false;
        }

        $token = $this->store->get($this->name);

        return $token === $this->token;
    }

    public function block(int $seconds): bool
    {
        $start = time();

        while (!$this->acquire()) {
            if (time() - $start >= $seconds) {
                return false;
            }

            usleep(100000);
        }

        return true;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    protected function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}

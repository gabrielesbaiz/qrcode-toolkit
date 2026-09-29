<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Support;

use Closure;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\FileStore;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;
use Throwable;

/**
 * Where, and whether, a rendered code is cached.
 *
 * The payload is stored base64 encoded. That is not decoration: PNG and
 * PDF output contains null bytes, and the database, memcached and dynamodb
 * stores each mangle or reject raw binary in their own way. A third more
 * storage buys every Laravel driver.
 */
final readonly class CacheSpec
{
    public function __construct(
        private ?CacheFactory $cache = null,
        public bool $enabled = false,
        public ?string $store = null,
        public int $ttl = 2592000,
        public string $prefix = 'qrcode-toolkit:',
        public ?string $key = null,
    ) {}

    /**
     * Get a copy with different settings.
     */
    public function with(?int $ttl = null, ?string $store = null, ?string $key = null, ?bool $enabled = null): self
    {
        return new self(
            $this->cache,
            $enabled ?? $this->enabled,
            $store ?? $this->store,
            $ttl ?? $this->ttl,
            $this->prefix,
            $key ?? $this->key,
        );
    }

    /**
     * The tag every cached code carries, where the store supports tags.
     */
    public const TAG = 'qrcode-toolkit';

    /**
     * Get the repository to read and write through.
     *
     * Tagged where the store allows it, so the codes can be purged on
     * their own rather than by flushing the whole application cache.
     */
    public function repository(): Repository
    {
        $repository = $this->cache?->store($this->store);

        if ($repository === null) {
            throw new RuntimeException('No cache factory is bound.');
        }

        return $this->taggable($repository)
            ? $repository->tags([self::TAG])
            : $repository;
    }

    /**
     * Determine whether the configured store can carry tags.
     */
    public function taggable(?Repository $repository = null): bool
    {
        $repository ??= $this->cache?->store($this->store);

        if ($repository === null) {
            return false;
        }

        try {
            return method_exists($repository, 'tags')
                && ! $repository->getStore() instanceof FileStore
                && ! $repository->getStore() instanceof DatabaseStore;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get a copy with caching switched off.
     */
    public function disabled(): self
    {
        return new self($this->cache, false, $this->store, $this->ttl, $this->prefix, $this->key);
    }

    /**
     * Return the cached payload, or produce and store it.
     *
     * @param  Closure(): string  $make
     */
    public function remember(string $key, Closure $make): string
    {
        if (! $this->enabled || ! $this->cache instanceof CacheFactory) {
            return $make();
        }

        $repository = $this->cache->store($this->store);
        $key = $this->prefix.($this->key ?? $key);

        $hit = $repository->get($key);

        if (is_string($hit)) {
            $decoded = base64_decode($hit, true);

            // A corrupt entry is rewritten rather than thrown over.
            if ($decoded !== false) {
                return $decoded;
            }
        }

        $payload = $make();
        $encoded = base64_encode($payload);

        $this->ttl > 0
            ? $repository->put($key, $encoded, $this->ttl)
            : $repository->forever($key, $encoded);

        return $payload;
    }
}

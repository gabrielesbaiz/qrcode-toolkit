<?php

declare(strict_types=1);

namespace Gabrielesbaiz\QrcodeToolkit\Commands;

use Gabrielesbaiz\QrcodeToolkit\Support\CacheSpec;
use Illuminate\Console\Command;
use Throwable;

/**
 * Forget the cached codes, without flushing the whole application cache.
 */
class ClearQrCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'qr:clear';

    /**
     * The console command description.
     */
    protected $description = 'Forget every cached QR code';

    /**
     * Execute the console command.
     */
    public function handle(CacheSpec $cache): int
    {
        if (! $cache->taggable()) {
            // Redis and memcached can carry a tag; the file and database
            // stores cannot, and there is no way to enumerate keys by
            // prefix through the cache contract. Saying so is better than
            // flushing somebody's session store on their behalf.
            $this->components->warn(
                'This cache store cannot be purged selectively, because it does not support tags. '
                .'Cached codes expire on their own ttl, or run cache:clear to flush everything.',
            );

            return self::SUCCESS;
        }

        try {
            $cache->repository()->clear();
        } catch (Throwable $exception) {
            $this->components->error('Could not clear the cache: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Cached QR codes forgotten.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Extensions\Chatbot\System\Console\Commands;

use App\Extensions\Chatbot\System\Services\EventOutboxProcessor;
use Illuminate\Console\Command;

class ProcessRuntimeOutboxCommand extends Command
{
    protected $signature = 'chatbot:runtime-outbox {--limit=100} {--release-stale} {--prune}';
    protected $description = 'Publish queued Chatbot runtime events and maintain the durable outbox';

    public function handle(EventOutboxProcessor $processor): int
    {
        if ($this->option('release-stale')) {
            $this->info('Released stale locks: ' . $processor->releaseStaleLocks());
        }
        if ($this->option('prune')) {
            $this->info('Pruned events: ' . $processor->prune());
        }

        $result = $processor->process((int) $this->option('limit'));
        $this->info(sprintf('Claimed %d, published %d, failed %d.', $result['claimed'], $result['published'], $result['failed']));

        return self::SUCCESS;
    }
}

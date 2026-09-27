<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Models\ConversationContext;
use Illuminate\Console\Command;

final class ExpireCommerceContexts extends Command
{
    protected $signature = 'chatbot-ecommerce:contexts-expire {--limit=1000}';
    protected $description = 'Remove expired short-lived conversational commerce context records.';

    public function handle(): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 10000);
        $ids = ConversationContext::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('id')->limit($limit)->pluck('id');
        $deleted = $ids->isEmpty() ? 0 : ConversationContext::query()->whereIn('id', $ids)->delete();
        $this->info("Expired {$deleted} conversational commerce contexts.");
        return self::SUCCESS;
    }
}

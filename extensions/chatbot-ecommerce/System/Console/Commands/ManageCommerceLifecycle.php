<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\CommerceLifecycleRuntime;
use Illuminate\Console\Command;

final class ManageCommerceLifecycle extends Command
{
    protected $signature = 'chatbot-ecommerce:lifecycle {action : status|enable|disable} {--reason=}';
    protected $description = 'Inspect or change the ecommerce extension lifecycle state without deleting commerce data.';

    public function handle(CommerceLifecycleRuntime $lifecycle): int
    {
        $action = strtolower((string) $this->argument('action'));
        if ($action === 'status') { $this->line($lifecycle->status()); return self::SUCCESS; }
        if ($action === 'enable') { $lifecycle->enable('4.9.0', (string) $this->option('reason')); return self::SUCCESS; }
        if ($action === 'disable') { $lifecycle->disable((string) $this->option('reason')); return self::SUCCESS; }
        $this->error('Action must be status, enable, or disable.');
        return self::INVALID;
    }
}

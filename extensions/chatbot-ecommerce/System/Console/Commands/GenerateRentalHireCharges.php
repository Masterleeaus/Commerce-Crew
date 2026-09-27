<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Console\Commands;

use App\Extensions\ChatbotEcommerce\System\Services\RentalHireRuntime;
use Illuminate\Console\Command;

final class GenerateRentalHireCharges extends Command
{
    protected $signature = 'chatbot-ecommerce:rental-hire-generate-charges {--through=} {--limit=500}';
    protected $description = 'Generate due rental and hire charges from active agreements.';

    public function handle(RentalHireRuntime $runtime): int
    {
        $count = $runtime->generateDueCharges($this->option('through') ?: null, (int) $this->option('limit'));
        $this->info("Generated {$count} rental/hire charge(s).");
        return self::SUCCESS;
    }
}

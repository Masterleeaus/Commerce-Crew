<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\ChatbotEcommerce\System\Models\CommerceLifecycleState;
use App\Extensions\ChatbotEcommerce\System\Support\LifecycleStatus;
use Illuminate\Support\Facades\Schema;

final class CommerceLifecycleRuntime
{
    private const KEY = 'chatbot-ecommerce';

    public function ensureInstalled(string $version): void
    {
        if (! Schema::hasTable('ext_chatbot_ecommerce_lifecycle_states')) return;
        $state = CommerceLifecycleState::query()->where('extension_key', self::KEY)->first();
        if ($state === null) {
            CommerceLifecycleState::query()->create([
                'extension_key' => self::KEY, 'status' => LifecycleStatus::ENABLED,
                'installed_version' => $version, 'preserve_data' => true, 'enabled_at' => now(),
            ]);
            return;
        }
        if ((string) $state->status === LifecycleStatus::UNINSTALLED) {
            $this->enable($version, 'Extension reinstalled.');
            return;
        }
        $state->forceFill(['installed_version' => $version])->save();
    }

    public function isEnabled(): bool
    {
        if (! Schema::hasTable('ext_chatbot_ecommerce_lifecycle_states')) return true;
        $status = CommerceLifecycleState::query()->where('extension_key', self::KEY)->value('status');
        return $status === null || LifecycleStatus::canServe((string) $status);
    }

    public function status(): string
    {
        if (! Schema::hasTable('ext_chatbot_ecommerce_lifecycle_states')) return LifecycleStatus::ENABLED;
        return (string) (CommerceLifecycleState::query()->where('extension_key', self::KEY)->value('status') ?: LifecycleStatus::ENABLED);
    }

    public function enable(string $version, ?string $reason = null): void
    {
        if (! Schema::hasTable('ext_chatbot_ecommerce_lifecycle_states')) return;
        CommerceLifecycleState::query()->updateOrCreate(['extension_key' => self::KEY], [
            'status' => LifecycleStatus::ENABLED, 'installed_version' => $version,
            'preserve_data' => true, 'reason' => $reason, 'enabled_at' => now(),
            'disabled_at' => null, 'uninstall_started_at' => null, 'uninstalled_at' => null,
        ]);
    }

    public function disable(?string $reason = null): void
    {
        if (! Schema::hasTable('ext_chatbot_ecommerce_lifecycle_states')) return;
        CommerceLifecycleState::query()->updateOrCreate(['extension_key' => self::KEY], [
            'status' => LifecycleStatus::DISABLED, 'preserve_data' => true,
            'reason' => $reason, 'disabled_at' => now(),
        ]);
    }

    public function beginUninstall(?string $reason = null): void
    {
        if (! Schema::hasTable('ext_chatbot_ecommerce_lifecycle_states')) return;
        CommerceLifecycleState::query()->updateOrCreate(['extension_key' => self::KEY], [
            'status' => LifecycleStatus::UNINSTALLING, 'preserve_data' => true,
            'reason' => $reason, 'uninstall_started_at' => now(),
        ]);
    }

    public function completeUninstall(): void
    {
        if (! Schema::hasTable('ext_chatbot_ecommerce_lifecycle_states')) return;
        CommerceLifecycleState::query()->where('extension_key', self::KEY)->update([
            'status' => LifecycleStatus::UNINSTALLED, 'preserve_data' => true, 'uninstalled_at' => now(),
        ]);
    }
}

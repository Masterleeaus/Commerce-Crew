<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Services;

use App\Extensions\Chatbot\System\Models\Chatbot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class CommerceTenantRuntime
{
    /** @return list<int> */
    public function ownedChatbotIds(Request $request): array
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        if ($userId <= 0) {
            abort(401);
        }

        $user = $request->user();
        if ($user !== null && method_exists($user, 'isAdmin') && (bool) $user->isAdmin()) {
            return Chatbot::query()->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        }

        return Chatbot::query()->where('user_id', $userId)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function assertChatbotOwner(Request $request, Chatbot $chatbot): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if (method_exists($user, 'isAdmin') && (bool) $user->isAdmin()) {
            return;
        }
        if ((int) $chatbot->getAttribute('user_id') !== (int) $user->getAuthIdentifier()) {
            abort(404);
        }
    }

    public function scope(Builder $query, Request $request, string $column = 'chatbot_id'): Builder
    {
        return $query->whereIn($column, $this->ownedChatbotIds($request));
    }

    public function assertRecord(Request $request, Model $record, string $column = 'chatbot_id'): void
    {
        $ownerUserId = (int) $record->getAttribute('owner_user_id');
        if ($ownerUserId > 0 && $ownerUserId !== (int) $request->user()?->getAuthIdentifier()) {
            abort(404);
        }
        $chatbotId = $this->recordChatbotId($record, $column);
        if ($chatbotId <= 0 || ! in_array($chatbotId, $this->ownedChatbotIds($request), true)) {
            abort(404);
        }
    }

    public function recordChatbotId(Model $record, string $column = 'chatbot_id'): int
    {
        $direct = (int) $record->getAttribute($column);
        if ($direct > 0) {
            return $direct;
        }
        foreach (['cart', 'checkout', 'account', 'rentalAccount', 'order', 'product', 'zone'] as $relation) {
            if (! method_exists($record, $relation)) {
                continue;
            }
            $related = $record->{$relation}()->first();
            if ($related instanceof Model) {
                $resolved = $this->recordChatbotId($related, $column);
                if ($resolved > 0) {
                    return $resolved;
                }
            }
        }

        return 0;
    }

    public function requestedOwnedChatbotId(Request $request, string $field = 'chatbot_id'): int
    {
        $chatbotId = (int) $request->input($field);
        if ($chatbotId <= 0 || ! in_array($chatbotId, $this->ownedChatbotIds($request), true)) {
            abort(403);
        }

        return $chatbotId;
    }
}

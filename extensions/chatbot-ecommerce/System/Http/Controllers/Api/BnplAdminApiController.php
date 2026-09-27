<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Controllers\Api;

use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\ChatbotEcommerce\System\Http\Resources\Api\BnplOfferResource;
use App\Extensions\ChatbotEcommerce\System\Models\BnplOffer;
use App\Extensions\ChatbotEcommerce\System\Models\BnplProviderProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BnplAdminApiController extends Controller
{
    public function providers(Request $request): JsonResponse
    {
        $query = BnplProviderProfile::query()
            ->whereIn('chatbot_id', $this->ownedChatbotIds($request))
            ->orderBy('display_name');

        return response()->json(['data' => $query->get()->map(fn (BnplProviderProfile $profile): array => $this->profileData($profile))]);
    }

    public function storeProvider(Request $request): JsonResponse
    {
        $data = $this->validatedProvider($request);
        $this->assertOwnsChatbot($request, (int) $data['chatbot_id']);
        $this->assertComplianceFields($data);

        $profile = BnplProviderProfile::query()->create($this->normaliseProvider($data));

        return response()->json(['data' => $this->profileData($profile)], 201);
    }

    public function updateProvider(BnplProviderProfile $profile, Request $request): JsonResponse
    {
        $this->assertProfileAccess($request, $profile);
        $data = $this->validatedProvider($request, true);
        $merged = array_replace($profile->toArray(), $data);
        $this->assertComplianceFields($merged);
        $profile->forceFill($this->normaliseProvider($data, true))->save();

        return response()->json(['data' => $this->profileData($profile->refresh())]);
    }

    public function disableProvider(BnplProviderProfile $profile, Request $request): JsonResponse
    {
        $this->assertProfileAccess($request, $profile);
        $profile->forceFill(['active' => false])->save();

        return response()->json(['data' => $this->profileData($profile->refresh())]);
    }

    public function offers(Request $request): JsonResponse
    {
        $query = BnplOffer::query()
            ->with(['providerProfile', 'paymentIntent'])
            ->whereIn('chatbot_id', $this->ownedChatbotIds($request))
            ->latest('id');

        foreach (['status', 'scope', 'shopping_mode'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->string($filter)->toString());
            }
        }
        if ($request->filled('provider_code')) {
            $query->whereHas('providerProfile', fn ($query) => $query->where('code', $request->string('provider_code')->toString()));
        }

        return response()->json(BnplOfferResource::collection(
            $query->paginate(min(max($request->integer('per_page', 25), 1), 100)),
        )->response()->getData(true));
    }

    /** @return array<string,mixed> */
    private function validatedProvider(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'chatbot_id' => [$sometimes, 'integer', 'min:1'],
            'code' => [$sometimes, 'string', 'max:80', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/'],
            'display_name' => [$sometimes, 'string', 'max:160'],
            'provider_type' => [$sometimes, Rule::in(['afterpay_clearpay', 'klarna', 'zip', 'affirm', 'paypal_pay_later', 'generic'])],
            'active' => ['sometimes', 'boolean'],
            'supported_scopes' => [$sometimes, 'array', 'min:1'],
            'supported_scopes.*' => [Rule::in(['checkout', 'rental_hire'])],
            'supported_shopping_modes' => [$sometimes, 'array', 'min:1'],
            'supported_shopping_modes.*' => [Rule::in(['native', 'marketplace_assisted'])],
            'supported_account_types' => ['sometimes', 'array'],
            'supported_account_types.*' => [Rule::in(['rent', 'hire', 'lease', 'other'])],
            'supported_currencies' => ['sometimes', 'array'],
            'supported_currencies.*' => ['string', 'size:3'],
            'supported_countries' => ['sometimes', 'array'],
            'supported_countries.*' => ['string', 'size:2'],
            'minimum_amount' => [$sometimes, 'integer', 'min:1'],
            'maximum_amount' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'installment_counts' => [$sometimes, 'array', 'min:1'],
            'installment_counts.*' => ['integer', 'min:2', 'max:60'],
            'interval_days' => [$sometimes, 'integer', 'min:1', 'max:365'],
            'first_payment_delay_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'merchant_fee_bps' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'hosted_checkout_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'terms_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'privacy_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'hardship_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'complaints_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'licence_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'credential_reference' => ['sometimes', 'nullable', 'string', 'max:191'],
            'metadata' => ['sometimes', 'array'],
        ]);
    }

    /** @param array<string,mixed> $data */
    private function normaliseProvider(array $data, bool $partial = false): array
    {
        foreach (['supported_scopes', 'supported_shopping_modes', 'supported_account_types'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = array_values(array_unique(array_map(static fn ($value): string => strtolower(trim((string) $value)), (array) $data[$field])));
            }
        }
        if (array_key_exists('supported_currencies', $data)) {
            $data['supported_currencies'] = array_values(array_unique(array_map(static fn ($value): string => strtoupper(trim((string) $value)), (array) $data['supported_currencies'])));
        }
        if (array_key_exists('supported_countries', $data)) {
            $data['supported_countries'] = array_values(array_unique(array_map(static fn ($value): string => strtoupper(trim((string) $value)), (array) $data['supported_countries'])));
        }
        if (array_key_exists('installment_counts', $data)) {
            $data['installment_counts'] = array_values(array_unique(array_map('intval', (array) $data['installment_counts'])));
            sort($data['installment_counts']);
        }
        foreach (['code', 'provider_type'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = strtolower(trim((string) $data[$field]));
            }
        }

        return $data;
    }

    /** @param array<string,mixed> $data */
    private function assertComplianceFields(array $data): void
    {
        if (! (bool) ($data['active'] ?? false)) {
            return;
        }
        if (in_array('native', (array) ($data['supported_shopping_modes'] ?? []), true) && empty($data['hosted_checkout_url'])) {
            throw ValidationException::withMessages(['hosted_checkout_url' => 'An active native BNPL provider requires a hosted checkout URL.']);
        }
        if ((bool) config('chatbot-ecommerce.bnpl.require_licensed_provider', true)) {
            foreach (['licence_reference', 'terms_url', 'hardship_url', 'complaints_url'] as $field) {
                if (trim((string) ($data[$field] ?? '')) === '') {
                    throw ValidationException::withMessages([$field => 'This field is required for an active BNPL provider.']);
                }
            }
        }
    }

    private function assertProfileAccess(Request $request, BnplProviderProfile $profile): void
    {
        if ($profile->chatbot_id === null || ! in_array((int) $profile->chatbot_id, $this->ownedChatbotIds($request), true)) {
            abort(403);
        }
    }

    private function assertOwnsChatbot(Request $request, int $chatbotId): void
    {
        if (! in_array($chatbotId, $this->ownedChatbotIds($request), true)) {
            abort(403);
        }
    }

    /** @return list<int> */
    private function ownedChatbotIds(Request $request): array
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        if ($userId <= 0) {
            abort(401);
        }

        return Chatbot::query()->where('user_id', $userId)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return array<string,mixed> */
    private function profileData(BnplProviderProfile $profile): array
    {
        return [
            'uuid' => $profile->uuid,
            'chatbot_id' => $profile->chatbot_id,
            'code' => $profile->code,
            'display_name' => $profile->display_name,
            'provider_type' => $profile->provider_type,
            'active' => (bool) $profile->active,
            'supported_scopes' => $profile->supported_scopes ?? [],
            'supported_shopping_modes' => $profile->supported_shopping_modes ?? [],
            'supported_account_types' => $profile->supported_account_types ?? [],
            'supported_currencies' => $profile->supported_currencies ?? [],
            'supported_countries' => $profile->supported_countries ?? [],
            'minimum_amount' => (int) $profile->minimum_amount,
            'maximum_amount' => $profile->maximum_amount !== null ? (int) $profile->maximum_amount : null,
            'installment_counts' => $profile->installment_counts ?? [],
            'interval_days' => (int) $profile->interval_days,
            'first_payment_delay_days' => (int) $profile->first_payment_delay_days,
            'merchant_fee_bps' => (int) $profile->merchant_fee_bps,
            'hosted_checkout_url' => $profile->hosted_checkout_url,
            'terms_url' => $profile->terms_url,
            'privacy_url' => $profile->privacy_url,
            'hardship_url' => $profile->hardship_url,
            'complaints_url' => $profile->complaints_url,
            'licence_reference' => $profile->licence_reference,
            'credential_reference' => $profile->credential_reference,
            'metadata' => $profile->metadata ?? [],
        ];
    }
}

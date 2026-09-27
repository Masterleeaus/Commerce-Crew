{{-- Voice Call Agent Configuration --}}
@if (Auth::user()?->isAdmin() || (Auth::user()?->relationPlan?->checkOpenAiItem(\App\Enums\Introduction::AI_EXT_VOICE_CALL->value) ?? false))
    <div>
        <x-forms.input
            class="order-3 ms-auto"
            class:label="text-heading-foreground"
            label="{{ __('Enable Voice Call Agent') }}"
            name="voice_call_enabled"
            size="sm"
            type="checkbox"
            switcher
            x-model.boolean="activeChatbot.voice_call_enabled"
        />
    </div>

    <div
        x-cloak
        x-show="activeChatbot.voice_call_enabled"
        x-transition
    >
        <div class="flex flex-col gap-7">
            <div>
                <x-forms.input
                    class:label="text-heading-foreground"
                    label="{{ __('First message of VoiceCall Agent') }}"
                    placeholder="{{ __('Hello! How can I help you today?') }}"
                    name="voice_call_first_message"
                    size="lg"
                    x-model="activeChatbot.voice_call_first_message"
                />

                <template
                    x-for="(error, index) in formErrors.voice_call_first_message"
                    :key="'error-' + index"
                >
                    <div class="mt-2 text-2xs/5 font-medium text-red-500">
                        <p x-text="error"></p>
                    </div>
                </template>
            </div>
        </div>
    </div>
@else
    @php
        $voiceCallFeatureKey = \App\Enums\Introduction::AI_EXT_VOICE_CALL->value;
        $requiredPlan = \App\Models\Plan::query()
            ->where('active', true)
            ->get()
            ->first(fn ($plan) => $plan->checkOpenAiItem($voiceCallFeatureKey));
    @endphp

    <a
        class="group flex items-center justify-between gap-3"
        href="{{ route('dashboard.user.payment.subscription') }}"
    >
        <div class="pointer-events-none flex grow items-center justify-between gap-2 opacity-50">
            <x-forms.input
                class:container="grow"
                class="order-3 ms-auto"
                class:label="text-heading-foreground"
                label="{{ __('Enable Voice Call Agent') }}"
                name="voice_call_enabled_disabled"
                size="sm"
                type="checkbox"
                switcher
                disabled
            />
        </div>

        @if ($requiredPlan)
            <x-badge
                class="shrink-0 !bg-primary text-2xs !text-primary-foreground transition hover:translate-y-0 hover:shadow-none group-hover:scale-110 group-hover:shadow-lg group-hover:shadow-black/10"
            >
                {{ $requiredPlan->name }}
            </x-badge>
        @endif
    </a>
@endif

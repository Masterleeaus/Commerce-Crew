<div>
    <x-forms.input
        class:label="text-heading-foreground"
        label="{{ __('Interaction type') }}"
        name="interaction_type"
        size="lg"
        type="select"
        x-model="activeChatbot.interaction_type"
    >
        <option value="{{ \App\Extensions\Chatbot\System\Enums\InteractionType::SMART_SWITCH->value }}">
            @lang('AI & Human Agent')
        </option>
        <option value="{{ \App\Extensions\Chatbot\System\Enums\InteractionType::AUTOMATIC_RESPONSE->value }}">
            @lang('Only AI')
        </option>
        <option value="{{ \App\Extensions\Chatbot\System\Enums\InteractionType::HUMAN_SUPPORT->value }}">
            @lang('Only Human Agent')
        </option>
    </x-forms.input>
    <template
        x-for="(error, index) in formErrors.interaction_type"
        :key="'error-' + index"
    >
        <div class="mt-2 text-2xs/5 font-medium text-red-500">
            <p x-text="error"></p>
        </div>
    </template>
</div>

<div
    class="flex flex-wrap items-center justify-between gap-2"
    x-cloak
    x-show="activeChatbot.interaction_type === 'smart_switch'"
>
    <p class="m-0 text-2xs font-medium text-heading-foreground">
        {{ __('Handoff to Human Agent Instructions') }}
    </p>
    <x-modal
        class:modal-head="border-b-0"
        class:modal-body="pt-0"
        class:modal-content="w-[min(600px,100%)]"
    >
        <x-slot:trigger
            size="sm"
            variant="ghost-shadow"
            type="button"
        >
            {{ __('Configure') }}
        </x-slot:trigger>

        <x-slot:modal>
            <h3 class="mb-3.5">
                {{ __('When to Handoff to Human') }}
            </h3>
            <p class="mb-9 text-balance text-base font-medium opacity-50">
                {{ __('Define the conditions under which the AI should transfer the conversation to a human agent. ') }}
            </p>

            <div class="mb-8 space-y-3">
                @foreach ($human_agent_conditions as $condition)
                    <x-forms.input
                        class:label="text-heading-foreground border rounded-xl px-2.5 py-3"
                        data-condition="{{ $condition }}"
                        label="{{ $condition }}"
                        type="checkbox"
                        custom
                        ::checked="activeChatbot.human_agent_conditions?.includes($el.getAttribute('data-condition'))"
                        @change="onHumanAgentConditionsChange"
                    />
                @endforeach
            </div>

            <x-button
                class="w-full"
                variant="secondary"
                @click.prevent="modalOpen = false"
            >
                {{ __('Save Instructions') }}
            </x-button>
        </x-slot:modal>
    </x-modal>

    <select
        class="hidden"
        id="human_agent_conditions"
        name="human_agent_conditions"
        multiple
        x-model="activeChatbot.human_agent_conditions"
    >
        @foreach ($human_agent_conditions as $condition)
            <option value="{{ $condition }}">
                {{ $condition }}
            </option>
        @endforeach
    </select>
</div>

<div>
    <x-forms.input
        class:label="text-heading-foreground"
        type="textarea"
        rows="4"
        label="{{ __('Connect Message') }}"
        placeholder="{{ __('I’ve forwarded your request to a human agent. An agent will connect with you as soon as possible.') }}"
        name="connect_message"
        size="lg"
        x-model="activeChatbot.connect_message"
        @input.throttle.250ms="externalChatbot && externalChatbot.toggleWindowState('close')"
    />

    <template
        x-for="(error, index) in formErrors.connect_message"
        :key="'error-' + index"
    >
        <div class="mt-2 text-2xs/5 font-medium text-red-500">
            <p x-text="error"></p>
        </div>
    </template>
</div>

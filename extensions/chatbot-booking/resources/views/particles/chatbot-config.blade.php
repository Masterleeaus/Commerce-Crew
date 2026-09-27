<div>
    <x-forms.input
        class="h-[18px] w-[34px] [background-size:0.625rem]"
        class:label="text-heading-foreground flex-row-reverse justify-between"
        label="{{ __('Booking Assistant') }}"
        name="is_booking_assistant"
        size="lg"
        type="checkbox"
        switcher
        x-model.boolean="activeChatbot.is_booking_assistant"
    />

    <template
        x-for="(error, index) in formErrors.is_booking_assistant"
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
    x-show="activeChatbot.is_booking_assistant"
>
    <p class="m-0 text-2xs font-medium text-heading-foreground">
        {{ __('Booking Assistant Instructions') }}
    </p>
    <x-modal
        class:modal-head="border-b-0"
        class:modal-body="pt-0"
        class:modal-content="max-w-[600px]"
        class:modal-container="max-w-[600px]"
    >
        <x-slot:trigger
            variant="ghost-shadow"
            type="button"
        >
            {{ __('Edit') }}
        </x-slot:trigger>

        <x-slot:modal>
            <h3 class="mb-3.5">
                {{ __('When to Show Booking Assistant') }}
            </h3>
            <p class="mb-9 text-balance text-base font-medium opacity-50">
                {{ __('Define the conditions under which the Booking Asisstant should appear.') }}
            </p>

            <div class="mb-8 space-y-3">
                @foreach ($booking_assistant_conditions as $condition)
                    <x-forms.input
                        class:label="text-heading-foreground border rounded-xl px-2.5 py-3"
                        data-condition="{{ $condition }}"
                        label="{{ $condition }}"
                        type="checkbox"
                        custom
                        ::checked="activeChatbot.booking_assistant_conditions?.includes($el.getAttribute('data-condition'))"
                        @change="onBookingAssistantConditionsChange"
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
        id="booking_assistant_conditions"
        name="booking_assistant_conditions"
        multiple
        x-model="activeChatbot.booking_assistant_conditions"
    >
        @foreach ($booking_assistant_conditions as $condition)
            <option value="{{ $condition }}">
                {{ $condition }}
            </option>
        @endforeach
    </select>
</div>

<div
    x-cloak
    x-show="activeChatbot.is_booking_assistant"
>
    <x-forms.input
        class:label="text-heading-foreground"
        label="{{ __('Booking Assistant Embed Code') }}"
        type="textarea"
        rows="4"
        placeholder="{{ __('Paste the inline embed code supplied by your service provider') }}"
        tooltip="{{ __('Get your inline embed code from your scheduling tool settings. Cal.com: Event Types → Embed. Calendly: Share → Add to website → Inline Embed') }}"
        name="booking_assistant_iframe"
        size="lg"
        x-model="activeChatbot.booking_assistant_iframe"
    />

    <template
        x-for="(error, index) in formErrors.booking_assistant_iframe"
        :key="'error-' + index"
    >
        <div class="mt-2 text-2xs/5 font-medium text-red-500">
            <p x-text="error"></p>
        </div>
    </template>
</div>
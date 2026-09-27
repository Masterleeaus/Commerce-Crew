<div class="grid grid-cols-1 gap-5 lg:grid-cols-2 lg:gap-11">
    {{-- Add new chatbot card --}}
    <x-card
        class:body="lg:p-16 p-9"
        class="text-center"
    >
        <figure class="mx-auto mb-6 inline-grid size-40 place-items-center rounded-full bg-heading-foreground/[3%]">
            <img
                src="{{ asset('vendor/chatbot-voice/images/new-voice-chabot.png') }}"
                alt="Add New Voice Chatbot"
            >
        </figure>
        <p class="mx-auto mb-6 max-w-[370px] font-heading text-xl font-semibold leading-[1.3em] text-heading-foreground">
            @lang('Create AI voice chatbots that sound and behave just like a human.')
        </p>
        <x-button
            @click.prevent="setActiveChatbot('new_chatbot', 1, true);"
            variant="ghost-shadow"
            href="#"
        >
            <x-tabler-plus class="size-4" />
            @lang('Add New Voice Chatbot')
        </x-button>
    </x-card>

    {{-- Show history card --}}
    <x-card
        class:body="lg:p-16 p-9"
        class="text-center"
    >
        <figure class="mx-auto mb-6 inline-grid size-40 place-items-center rounded-full bg-heading-foreground/[3%]">
            <img
                src="{{ asset('vendor/chatbot-voice/images/conversation-history.png') }}"
                alt="View Chat History"
            >
        </figure>
        <p
            class="mx-auto mb-6 max-w-[370px] font-heading text-xl font-semibold leading-[1.3em] text-heading-foreground">
            @lang('Explore recent conversations from your users.')
        </p>

        <div class="flex flex-wrap justify-center gap-x-3 gap-y-1">
            <x-button
                variant="ghost-shadow"
                href="#"
                @click.prevent="$store.externalChatbotHistory.setOpen(true)"
            >
                <x-tabler-robot
                    class="size-5"
                    stroke-width="1.5"
                />
                @lang('AI Bot Messages')
            </x-button>
        </div>
    </x-card>
</div>

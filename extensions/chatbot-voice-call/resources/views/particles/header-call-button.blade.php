{{-- Voice Call Button - shown in header next to agent name --}}
<button
    class="inline-grid size-10 place-items-center rounded-full transition active:scale-[0.85]"
    type="button"
    title="{{ __('Start Voice Call') }}"
    x-show="$store.voiceCall.enabled && currentView === 'conversation-messages' && ($store.voiceCall.status === 'idle' || $store.voiceCall.status === 'ended')"
    x-cloak
    @click.prevent="$store.voiceCall.start()"
>
    <x-tabler-phone-call
        class="size-[22px]"
        stroke-width="1.75"
    />
</button>

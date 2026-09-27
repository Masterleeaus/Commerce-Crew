@php
    $embed_code = '';
    if (isset($chatbot) && \App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-booking') && !empty($chatbot->getAttribute('booking_assistant_iframe'))) {
        $embed_code = $chatbot->getAttribute('booking_assistant_iframe');
    }
@endphp
<template x-if="showBookingIframe">
    <div
        class="lqd-ext-chatbot-window-booking-iframe absolute z-20 rounded-md"
        style="
            background-color: var(--lqd-ext-chat-window-bg);
            width:calc(100% - 24px);
            margin-left:12px;
            height: calc(100% - 120px);
            overflow: hidden;
        "
    >
        <div class="lqd-ext-chatbot-window-booking-iframe-header flex justify-between items-center p-7">
            <div class="text-sm font-semibold ">{{ __('Schedule') }}</div>
            <div
                    class="w-8 h-8 grid place-items-center border border-solid border-black/10 rounded-full cursor-pointer"
                    @click.prevent="setBookingIframe(false)"
                >
                    <x-tabler-x class="size-4" />
                </div>
        </div>
        <div class="lqd-ext-chatbot-window-booking-iframe-container" style="height: calc(100% - 120px);">
            @if(!empty($embed_code))
                {!! $embed_code !!}
            @else
                <p>{{ __('Embed code not found. Add a embed code.') }}</p>
            @endif
        </div>
    </div>
</template>

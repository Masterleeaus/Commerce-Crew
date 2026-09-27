{{-- Voice Call Info Boxes - "Call Started" and "Call ended." dividers in chat --}}
<template x-if="message.role === 'voice-call-started'">
    <div class="my-4 flex items-center gap-3 text-3xs">
        <span class="h-px grow bg-current opacity-10"></span>
        <span class="opacity-50">{{ __('Call Started') }}</span>
        <span class="h-px grow bg-current opacity-10"></span>
    </div>
</template>

<template x-if="message.role === 'voice-call-ended'">
    <div class="my-4 flex items-center gap-3 text-3xs">
        <span class="h-px grow bg-current opacity-10"></span>
        <span class="opacity-50">{{ __('Call ended.') }}</span>
        <span class="h-px grow bg-current opacity-10"></span>
    </div>
</template>

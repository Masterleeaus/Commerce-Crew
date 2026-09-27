<div
    class="mt-6"
    x-cloak
    x-show="reviewWidget.visible && (activeChatbot.review_responses || []).length"
    x-transition.opacity
>
    <div
        class="lqd-ext-chatbot-window-conversation-message"
        data-type="assistant"
    >
        <figure class="lqd-ext-chatbot-window-conversation-message-avatar">
            <img
                {{-- blade-formatter-disable --}}
            @if ($is_editor)
                :src="() => activeChatbot.avatar ? `${window.location.origin}/${activeChatbot.avatar}` : ''"
            @else
                src="/{{ $chatbot['avatar'] }}"
                alt="{{ $chatbot['title'] }}"
                @if (!empty($chatbot['trigger_avatar_size']))
                    style="width: {{ (int) $chatbot['trigger_avatar_size'] }}px"
                @endif
            @endif
            {{-- blade-formatter-enable --}}
                width="27"
                height="27"
            />
        </figure>

        <div class="lqd-ext-chatbot-window-conversation-message-content-wrap">
            <div class="lqd-ext-chatbot-window-conversation-message-content text-xs/5">
                <p
                    class="mb-3.5 text-balance"
                    x-text="getReviewPrompt()"
                ></p>

                <div class="flex flex-wrap gap-2">
                    <template
                        x-for="(response, index) in activeChatbot.review_responses"
                        :key="'review-response-' + index"
                    >
                        <button
                            class="inline-flex min-h-36 min-w-8 items-center justify-center gap-1 rounded bg-black/5 p-2 text-center text-2xs transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-black/5"
                            type="button"
                            :disabled="reviewWidget.submitting"
                            @click.prevent="sendReviewResponse(response)"
                            x-text="response"
                        ></button>
                    </template>
                </div>

                <template x-if="reviewWidget.error">
                    <p
                        class="mt-3 text-[11px] font-medium text-red-400"
                        x-text="reviewWidget.error"
                    ></p>
                </template>
            </div>
        </div>
    </div>
</div>

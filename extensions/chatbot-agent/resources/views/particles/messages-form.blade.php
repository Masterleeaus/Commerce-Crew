<div class="sticky bottom-0 lg:px-8 lg:py-7">
    <x-progressive-blur class="max-lg:hidden" />

    <form
        class="lqd-chat-form gap-y-1 border-t bg-foreground/5 backdrop-blur-2xl backdrop-contrast-[105%] lg:rounded-xl lg:border-t-0"
        id="chat_form"
        @submit.prevent="onSendMessage"
        x-transition
    >
        <textarea
            class="min-h-12 w-full resize-none border-none bg-transparent px-6 pt-4 placeholder:text-foreground/70 focus:outline-none sm:text-xs"
            id="message"
            rows="2"
            name="message"
            @keydown.enter.prevent="onMessageFieldHitEnter"
            @input="onMessageFieldInput"
            @input.throttle.50ms="$el.scrollTop = $el.scrollHeight"
            x-ref="message"
            placeholder="{{ __('Message') }}"
        ></textarea>

        <div class="flex w-full items-center justify-between gap-2 px-6 pb-4">
            <div class="flex items-center gap-1">
                <span
                    class="relative inline-grid size-7 cursor-pointer place-items-center rounded-full text-foreground/80 transition hover:bg-background hover:text-foreground hover:shadow-lg"
                    type="button"
                    title="{{ __('Attach Files') }}"
                >
                    <x-tabler-plus class="size-[18px]" />
                    <input
                        class="absolute inset-0 z-10 cursor-pointer opacity-0 file:w-full file:cursor-pointer file:p-0"
                        type="file"
                        x-ref="media"
                        name="media"
                        @change="setAttachmentsPreview"
                    />
                </span>

                <div class="relative">
                    <button
                        class="inline-grid size-7 place-items-center rounded-full text-foreground/80 transition hover:bg-background hover:text-foreground hover:shadow-lg"
                        type="button"
                        title="{{ __('Emojis') }}"
                        x-ref="emojiTrigger"
                        @click.prevent="showEmojiPicker = !showEmojiPicker"
                    >
                        <x-tabler-mood-smile class="size-[18px]" />
                    </button>

                    <div
                        class="lqd-chatbot-emoji pointer-events-auto absolute -start-8 bottom-full mb-2 sm:-start-4"
                        x-ref="emojiPicker"
                        x-show="showEmojiPicker"
                        @click.outside="showEmojiPicker = false"
                    ></div>
                </div>

                <x-dropdown.dropdown
                    anchor="start"
                    trigger-type="click"
                    offsetY="10px"
                >
                    <x-slot:trigger
                        class="inline-grid size-7 place-items-center rounded-full text-foreground/80 transition hover:bg-background hover:text-foreground hover:shadow-lg"
                        variant="link"
                        ::class="{ 'pointer-events-none opacity-60': messageRewriteLoading }"
                        title="{{ __('Rewrite Message') }}"
                    >
                        <x-tabler-sparkles
                            class="size-[18px]"
                            x-show="!messageRewriteLoading"
                        />
                        <x-tabler-refresh
                            class="size-[18px] animate-spin"
                            x-cloak
                            x-show="messageRewriteLoading"
                        />
                    </x-slot:trigger>

                    <x-slot:dropdown
                        class="min-w-56 !rounded-2xl px-4 pb-1 pt-4 text-xs font-medium"
                    >
                        <p class="mb-0 border-b pb-3 text-3xs/none font-semibold uppercase tracking-wider text-foreground/60">
                            {{ __('Actions') }}
                        </p>
                        <ul>
                            <template
                                x-for="option in rewriteOptions"
                                :key="option.key"
                            >
                                <li class="border-b last:border-b-0">
                                    <x-button
                                        class="w-full justify-start py-3 text-start"
                                        variant="link"
                                        @click.prevent="rewriteMessage(option.key); toggle('collapse');"
                                        ::disabled="messageRewriteLoading"
                                    >
                                        <span x-text="option.label"></span>
                                    </x-button>
                                </li>
                            </template>
                        </ul>
                    </x-slot:dropdown>
                </x-dropdown.dropdown>

                <x-dropdown.dropdown
                    anchor="start"
                    triggerType="click"
                    offsetY="10px"
                >
                    <x-slot:trigger
                        class="inline-grid size-7 place-items-center rounded-full text-foreground/80 transition hover:bg-background hover:text-foreground hover:shadow-lg"
                        variant="link"
                        title="{{ __('Canned Responses') }}"
                        @click="toggleCannedResponses"
                    >
                        <x-tabler-message-2 class="size-[18px]" />

                    </x-slot:trigger>

                    <x-slot:dropdown
                        class="w-[min(420px,calc(100vw-40px))] px-4 py-6"
                    >
                        <div class="relative mb-3">
                            <x-forms.input
                                class="border-none bg-foreground/5"
                                type="search"
                                x-model="cannedResponsesSearch"
                                placeholder="{{ __('Search for articles') }}"
                            />
                            <x-tabler-search class="pointer-events-none absolute end-4 top-1/2 size-4 -translate-y-1/2" />
                        </div>

                        <div class="max-h-[min(600px,calc(100vh-200px))] overflow-y-auto">
                            <template x-if="cannedResponsesLoading">
                                <div class="flex items-center justify-center gap-2 rounded-2xl bg-foreground/[0.05] p-6 text-sm text-foreground/70">
                                    <x-tabler-refresh class="size-4 animate-spin" />
                                    <span>{{ __('Loading canned responses...') }}</span>
                                </div>
                            </template>

                            <template x-if="!cannedResponsesLoading && !filteredCannedResponses().length">
                                <div class="rounded-2xl bg-foreground/[0.05] p-6 text-center text-sm text-foreground/70">
                                    <p class="mb-1 font-semibold">
                                        {{ __('No canned responses found.') }}
                                    </p>
                                    <a
                                        class="text-xs font-medium text-primary hover:underline"
                                        href="{{ route('dashboard.chatbot.canned-response.index') }}"
                                        target="_blank"
                                    >
                                        {{ __('Create a canned response') }}
                                    </a>
                                </div>
                            </template>

                            <template
                                x-for="response in filteredCannedResponses()"
                                :key="response.id"
                            >
                                <x-button
                                    class="line-clamp-2 block w-full !rounded-2xl border-b p-4 text-start text-2xs transition hover:bg-foreground/5"
                                    type="button"
                                    variant="none"
                                    @click.prevent="insertCannedResponse(response); toggle('collapse');"
                                >
                                    <span
                                        class="mb-2 block font-semibold text-heading-foreground"
                                        x-text="response.title"
                                    ></span>
                                    <span
                                        class="font-normal"
                                        x-text="stripHtml(response.content)"
                                    ></span>
                                </x-button>
                            </template>
                        </div>
                    </x-slot:dropdown>
                </x-dropdown.dropdown>

                <template x-if="attachmentsPreview.length">
                    <div class="relative flex flex-wrap gap-1">
                        <template x-for="attachment in attachmentsPreview">
                            <template x-if="attachment.type.startsWith('image/')">
                                <img
                                    class="size-10 shrink-0 rounded-md object-cover object-center shadow-sm shadow-black/5 md:size-14"
                                    :src="attachment.url"
                                >
                            </template>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex items-center gap-2">
                <select
                    class="border-0 bg-transparent text-xs font-medium text-foreground focus:outline-none"
                    name="is_internal_note"
                    x-ref="isInternalNote"
                >
                    <option value="0">{{ __('Reply') }}</option>
                    <option value="1">{{ __('Note') }}</option>
                </select>

                <x-button
                    class="bg-background text-[12px] font-medium text-foreground shadow-lg disabled:shadow-none max-lg:!bg-transparent max-lg:shadow-none max-lg:hover:text-foreground"
                    hover-variant="primary"
                    type="submit"
                    x-ref="submitBtn"
                    disabled
                    ::disabled="!$refs.message.value.trim() && !attachmentsPreview.length"
                >
                    <span class="max-lg:hidden">
                        {{ __('Send') }}
                    </span>
                    <svg
                        class="fill-current lg:hidden"
                        width="19"
                        height="16"
                        viewBox="0 0 19 16"
                        fill="currentColor"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path d="M0 16V10L8 8L0 6V0L19 8L0 16Z" />
                    </svg>
                </x-button>
            </div>
        </div>
    </form>
</div>

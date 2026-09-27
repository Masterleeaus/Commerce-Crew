<div class="space-y-1.5">
    <div class="flex flex-wrap items-center gap-3">
        <x-forms.input
            class:container="grow"
            class:label="text-heading-foreground"
            class="order-3 ms-auto"
            label="{{ __('Leave Feedback/Review') }}"
            tooltip="{{ __('Allow visitors to leave quick feedback and prepare reusable responses for your team.') }}"
            name="is_review_enabled"
            size="sm"
            type="checkbox"
            switcher
            x-model.boolean="activeChatbot.is_review_enabled"
            @change="onReviewToggleChange($event.target.checked)"
        />

        <x-modal
            class:modal-content="w-[min(600px,100%)]"
            id="chatbot-review-modal"
            title="{{ __('Configure Review') }}"
            x-cloak
            x-show="activeChatbot.is_review_enabled"
        >
            <x-slot:trigger
                size="sm"
                variant="ghost-shadow"
            >
                {{ __('Configure') }}
            </x-slot:trigger>

            <x-slot:modal>
                <div class="space-y-6">
                    <div>
                        <x-forms.input
                            class:label="text-heading-foreground"
                            label="{{ __('Review Prompt') }}"
                            name="review_prompt"
                            placeholder="{{ __('Tell us about your experience with our assistant.') }}"
                            rows="4"
                            size="lg"
                            type="textarea"
                            x-model="activeChatbot.review_prompt"
                        />

                        <template
                            x-for="(error, index) in formErrors.review_prompt"
                            :key="'error-review-prompt-' + index"
                        >
                            <p
                                class="mt-2 text-2xs/5 font-medium text-red-500"
                                x-text="error"
                            ></p>
                        </template>
                    </div>

                    <div class="rounded-2xl border border-border p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-heading-foreground">
                                    {{ __('Review Responses') }}
                                </p>
                                <p class="text-2xs">
                                    {{ __('Add canned responses that can be quickly inserted when replying to feedback. Maximum :count items.', ['count' => 5]) }}
                                </p>
                            </div>
                            <span
                                class="text-2xs font-medium text-heading-foreground"
                                x-text="((activeChatbot.review_responses || []).length) + '/' + reviewMaxResponses"
                            ></span>
                        </div>

                        <div class="mt-4 space-y-4">
                            <template x-if="!activeChatbot.review_responses || activeChatbot.review_responses.length === 0">
                                <p class="rounded-xl border border-dashed border-border px-4 py-3 text-center text-2xs">
                                    {{ __('You have not added any saved responses yet.') }}
                                </p>
                            </template>

                            <template
                                x-for="(response, index) in activeChatbot.review_responses"
                                :key="'review-response-' + index"
                            >
                                <div class="rounded-xl border border-input-border/60 p-4">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <p class="text-2xs font-semibold text-heading-foreground">
                                            {{ __('Response') }}
                                            <span
                                                class="ms-1 font-medium"
                                                x-text="'#' + (index + 1)"
                                            ></span>
                                        </p>
                                        <button
                                            class="text-2xs font-semibold text-red-500 transition hover:text-red-600"
                                            type="button"
                                            @click.prevent="removeReviewResponse(index)"
                                        >
                                            {{ __('Remove') }}
                                        </button>
                                    </div>

                                    <x-forms.input
                                        class:container="w-full"
                                        label=""
                                        name="review_responses[]"
                                        placeholder="{{ __('Type your saved response') }}"
                                        size="lg"
                                        x-model="activeChatbot.review_responses[index]"
                                    />

                                    <template
                                        x-for="(error, errorIndex) in formErrors['review_responses.' + index] ?? []"
                                        :key="'error-review-responses-' + index + '-' + errorIndex"
                                    >
                                        <p
                                            class="mt-2 text-2xs/5 font-medium text-red-500"
                                            x-text="error"
                                        ></p>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <x-button
                                size="sm"
                                type="button"
                                variant="success"
                                x-bind:disabled="(activeChatbot.review_responses || []).length >= reviewMaxResponses"
                                @click.prevent="addReviewResponse()"
                            >
                                <x-tabler-plus class="size-4" />
                                {{ __('Add Response') }}
                            </x-button>
                            <p class="text-2xs">
                                {{ __('Need more than :count responses? Update them regularly to keep feedback relevant.', ['count' => 5]) }}
                            </p>
                        </div>
                    </div>

                    <x-button
                        class="w-full"
                        type="button"
                        variant="secondary"
                        @click.prevent="modalOpen = false"
                    >
                        {{ __('Done') }}
                    </x-button>
                </div>
            </x-slot:modal>
        </x-modal>
    </div>

    <template
        x-for="(error, index) in formErrors.is_review_enabled"
        :key="'error-review-toggle-' + index"
    >
        <p
            class="text-2xs/5 font-medium text-red-500"
            x-text="error"
        ></p>
    </template>
</div>

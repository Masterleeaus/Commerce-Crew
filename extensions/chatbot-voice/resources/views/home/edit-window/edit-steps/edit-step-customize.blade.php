{{-- Editing Step 2 - Customize --}}
<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 transition-all"
    data-step="2"
    x-show="editingStep === 2"
    x-transition:enter-start="opacity-0 -translate-x-3"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-3"
>
    <h2 class="mb-3.5">
        @lang('Customize')
    </h2>
    <p class="text-xs/5 opacity-60 lg:max-w-[360px]">
        @lang('Create and configure a chatbot that interacts with your users, ensuring it delivers accurate information.')
    </p>

    <div class="flex flex-col gap-7 pt-9">
        <div>
            <label
                class="mb-5 block w-full cursor-pointer text-xs font-medium text-heading-foreground"
                for="{{ $avatars->isEmpty() ? '' : 'avatar-' . $avatars[0]['id'] }}"
            >
                @lang('Avatar')
            </label>
            <p class="mb-5 w-full text-sm">
                @lang('Select an avatar for your chatbot.')
            </p>
            <div class="grid grid-cols-3 gap-x-2 gap-y-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
                <div class="lqd-chatbot-avatar-list contents">
                    @forelse ($avatars as $avatar)
                        <div class="lqd-chatbot-avatar-list-item relative flex justify-center">
                            <input
                                class="peer invisible size-0"
                                id="avatar-{{ $avatar['id'] }}"
                                type="radio"
                                name="avatar"
                                value="{{ $avatar['avatar'] }}"
                                x-model="activeChatbot.avatar"
                            />
                            <label
                                class="relative inline-grid size-10 cursor-pointer place-items-center transition-all hover:scale-110 peer-checked:drop-shadow-xl"
                                for="avatar-{{ $avatar['id'] }}"
                                tabindex="0"
                            >
                                <img
                                    width="40"
                                    height="40"
                                    src="{{ $avatar['avatar_url'] }}"
                                >
                            </label>
                            <span
                                class="pointer-events-none invisible absolute start-1/2 top-1/2 inline-grid size-6 -translate-x-1/2 -translate-y-1/2 scale-75 place-items-center rounded-full bg-white/15 text-heading-foreground opacity-0 backdrop-blur-md backdrop-saturate-150 transition-all peer-checked:visible peer-checked:scale-100 peer-checked:opacity-100"
                            >
                                <x-tabler-check class="size-[18px] text-white" />
                            </span>
                        </div>
                    @empty
                        <div class="lqd-chatbot-avatar-list-item relative flex hidden justify-center">
                            <input
                                class="peer invisible size-0"
                                type="radio"
                                name="avatar"
                                x-model="activeChatbot.avatar"
                            />
                            <label
                                class="relative inline-grid size-10 cursor-pointer place-items-center transition-all hover:scale-110 peer-checked:drop-shadow-xl"
                                tabindex="0"
                            >
                                <img
                                    width="40"
                                    height="40"
                                >
                            </label>
                            <span
                                class="pointer-events-none invisible absolute start-1/2 top-1/2 inline-grid size-6 -translate-x-1/2 -translate-y-1/2 scale-75 place-items-center rounded-full bg-white/15 text-heading-foreground opacity-0 backdrop-blur-md backdrop-saturate-150 transition-all peer-checked:visible peer-checked:scale-100 peer-checked:opacity-100"
                            >
                                <x-tabler-check class="size-[18px] text-white" />
                            </span>
                        </div>
                    @endforelse
                </div>
                <div
                    class="relative flex justify-center"
                    x-data="customAvatar"
                >
                    <input
                        class="peer invisible size-0"
                        id="avatar-custom"
                        type="file"
                        x-bind="customAvatarPicker"
                    >
                    <label
                        class="inline-grid size-10 cursor-pointer place-items-center rounded-full bg-heading-foreground/5 text-heading-foreground transition-all hover:scale-110 hover:bg-heading-foreground hover:text-heading-background peer-checked:drop-shadow-xl"
                        for="avatar-custom"
                        tabindex="0"
                    >
                        <x-tabler-plus class="size-4" />
                    </label>
                </div>
            </div>

            <template
                x-for="(error, index) in formErrors.avatar"
                :key="'error-' + index"
            >
                <div class="mt-2 text-2xs/5 font-medium text-red-500">
                    <p x-text="error"></p>
                </div>
            </template>
        </div>

        <div>
            <label
                class="mb-5 block w-full cursor-pointer text-xs font-medium text-heading-foreground"
                for=""
            >
                @lang('Voice')
            </label>
            <p class="mb-5 w-full text-sm">
                @lang('Choose an voice that represents your brand.')
            </p>
            <div class="grid grid-cols-1 gap-3">
                <x-forms.input
                    class="max-sm:text-[9px]"
                    id="voice"
                    size="lg"
                    type="select"
                    name="voice"
                    x-model="activeChatbot.voice_id"
                >
                    @foreach ($voices as $voice)
                        <option value="{{ $voice->voice_id }}">
                            {{ $voice->name . ' (' . implode(', ', (array) $voice->labels) . ')' }}
                        </option>
                    @endforeach
                </x-forms.input>
            </div>

            <template
                x-for="(error, index) in formErrors.voice"
                :key="'error-' + index"
            >
                <div class="mt-2 text-2xs/5 font-medium text-red-500">
                    <p x-text="error"></p>
                </div>
            </template>
        </div>

        <div>
            <label
                class="mb-5 block w-full cursor-pointer text-xs font-medium text-heading-foreground"
                for="position-left"
            >
                @lang('Position')
            </label>
            <div class="flex flex-wrap justify-between gap-2">
                @foreach (\App\Extensions\ChatbotVoice\System\Enums\PositionEnum::toArray() as $position)
                    <div class="relative text-center">
                        <input
                            class="peer invisible absolute size-0"
                            id="position-{{ $position }}"
                            type="radio"
                            name="position"
                            value="{{ $position }}"
                            x-model="activeChatbot.position"
                        />
                        <label
                            @class([
                                'h-[105px] w-[150px] rounded-lg bg-heading-foreground/5 p-3 text-heading-foreground/20 flex items-end cursor-pointer transition-all hover:scale-105',
                                'justify-end' => $position === 'right',
                            ])
                            for="position-{{ $position }}"
                        >
                            <svg
                                @class([
                                    '-scale-x-100' => $position === 'right',
                                ])
                                width="37"
                                height="50"
                                viewBox="0 0 37 50"
                                fill="currentColor"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M0 45.0721C0 42.863 1.79086 41.0721 4 41.0721C6.20914 41.0721 8 42.863 8 45.0721C8 47.2813 6.20914 49.0721 4 49.0721C1.79086 49.0721 0 47.2813 0 45.0721Z"
                                />
                                <path
                                    d="M0 4.07214C0 1.863 1.79086 0.0721436 4 0.0721436H33C35.2091 0.0721436 37 1.863 37 4.07214V33.0721C37 35.2813 35.2091 37.0721 33 37.0721H4C1.79086 37.0721 0 35.2813 0 33.0721V4.07214Z"
                                />
                            </svg>
                        </label>
                        <span
                            class="pointer-events-none absolute end-1.5 top-1.5 inline-grid size-7 scale-110 place-items-center rounded-full bg-primary/10 text-primary opacity-0 transition-all peer-checked:scale-100 peer-checked:opacity-100"
                        >
                            <x-tabler-check class="size-5" />
                        </span>
                        <span class="mt-5 block text-xs font-semibold capitalize text-heading-foreground">
                            {{ $position }}
                        </span>
                    </div>
                    @if (!$loop->last)
                        <span class="w-px bg-heading-foreground/10"></span>
                    @endif
                @endforeach
            </div>

            <template
                x-for="(error, index) in formErrors.position"
                :key="'error-' + index"
            >
                <div class="mt-2 text-2xs/5 font-medium text-red-500">
                    <p x-text="error"></p>
                </div>
            </template>
        </div>
    </div>
</div>

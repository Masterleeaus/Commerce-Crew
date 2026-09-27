<ul class="lqd-ext-chatbot-history-list flex flex-col gap-2">
    <template
        x-for="(chatItem, index) in chatsList"
        x-show="chatsList.length"
    >
        <li
            class="lqd-ext-chatbot-history-list-item group/chat-item relative rounded-xl px-5 py-3.5 text-heading-foreground transition-colors hover:bg-heading-foreground/5 [&.lqd-active]:bg-heading-foreground/5"
            :class="{ 'lqd-active': activeChat ? activeChat == chatItem.id : index === 0 }"
        >

            <div class="flex items-start gap-3">
                <template x-if="chatItem?.chatbot_channel === 'frame'">
                    <svg
                        class="flex-shrink-0"
                        width="32"
                        height="31"
                        viewBox="0 0 32 31"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M16 0C7.44048 0 0.5 6.93943 0.5 15.5C0.5 24.0606 7.4398 31 16 31C24.5609 31 31.5 24.0606 31.5 15.5C31.5 6.93943 24.5609 0 16 0ZM16 4.63468C18.8323 4.63468 21.1274 6.93057 21.1274 9.76163C21.1274 12.5934 18.8323 14.8886 16 14.8886C13.1691 14.8886 10.874 12.5934 10.874 9.76163C10.874 6.93057 13.1691 4.63468 16 4.63468ZM15.9966 26.9475C13.1718 26.9475 10.5846 25.9187 8.58906 24.2158C8.10294 23.8012 7.82243 23.1931 7.82243 22.5552C7.82243 19.6839 10.1461 17.386 13.0179 17.386H18.9834C21.8559 17.386 24.1708 19.6839 24.1708 22.5552C24.1708 23.1938 23.8916 23.8005 23.4048 24.2151C21.41 25.9187 18.8221 26.9475 15.9966 26.9475Z"
                            :fill="chatItem.color"
                        />
                    </svg>
                </template>

                <div class="grow">
                    <div class="flex flex-col gap-1">
                        <div class="grid grid-cols-12">
                            <div class="col-span-11 flex">
                                <h4
                                    class="truncate"
                                    x-text="chatItem.conversation_name ?? 'Conversation'"
                                ></h4>
                            </div>
                            <div class="col-span-1 flex justify-center text-[11px] opacity-50">
                                <p class="m-0 whitespace-nowrap">
                                    <span
                                        x-text="getShortDiffHumanTime(Math.floor((new Date() - new Date(chatItem?.lastMessage?.created_at || chatItem.created_at)) / 1000))"
                                    ></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <a
                class="lqd-ext-chatbot-history-list-item-trigger absolute start-0 top-0 inline-block h-full w-full"
                :data-id="chatItem.id"
                href="#"
                title="{{ __('Open Chat History') }}"
                @click.prevent="setActiveChat"
            ></a>
        </li>
    </template>
    <template x-if="!chatsList.length">
        <p class="mb-0.5 font-semibold text-heading-foreground">
            {{ __('No chat history found.') }}
        </p>
    </template>
</ul>

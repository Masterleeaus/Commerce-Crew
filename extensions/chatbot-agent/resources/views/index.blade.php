@php
    $status_filters = [
        'all' => [
            'key' => 'all',
            'label' => __('All'),
            'count' => 0,
        ],
        'new' => [
            'key' => 'new',
            'label' => __('Open'),
            'count' => 1,
        ],
        'closed' => [
            'key' => 'closed',
            'label' => __('Closed'),
            'count' => 2,
        ],

        //        'deleted' => [
        //            'label' => __('Deleted'),
        //        ],
    ];

    $agent_filters = [
        'all' => [
            'label' => __('All'),
        ],
        'ai' => [
            'label' => __('AI Agent'),
        ],
        'human' => [
            'label' => __('Human Agent'),
        ],
    ];

    $sort_filters = [
        'newest' => [
            'label' => __('Newest'),
        ],
        'oldest' => [
            'label' => __('Oldest'),
        ],
    ];

    $channel_filters = [
        'all' => [
            'label' => __('All Channel'),
        ],
        'frame' => [
            'label' => __('Livechat'),
        ],
    ];

    if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-telegram')) {
        $channel_filters['telegram'] = [
            'label' => __('Telegram'),
        ];
    }

    if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-whatsapp')) {
        $channel_filters['whatsapp'] = [
            'label' => __('Whatsapp'),
        ];
    }

    if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-messenger')) {
        $channel_filters['messenger'] = [
            'label' => __('Messenger'),
        ];
    }
    if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-instagram')) {
        $channel_filters['instagram'] = [
            'label' => __('Instagram'),
        ];
    }

    $hasCustomerTagExtension = \App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-customer-tag');

    $customerTagRoutes = [
        'index' => $hasCustomerTagExtension ? route('dashboard.chatbot-agent.customer-tags.index') : null,
        'store' => $hasCustomerTagExtension ? route('dashboard.chatbot-agent.customer-tags.store') : null,
        'sync' => $hasCustomerTagExtension ? route('dashboard.chatbot-agent.customer-tags.sync') : null,
    ];
    $availableChatbots = isset($agentOptions) ? $agentOptions : collect();

    $conversation_agent_filters = [
        'all' => [
            'label' => __('All Agents'),
        ],
    ];

    foreach ($availableChatbots as $chatbotAgent) {
        $conversation_agent_filters[$chatbotAgent->id] = [
            'label' => $chatbotAgent->title,
        ];
    }
@endphp

@extends('panel.layout.app', [
    'disable_tblr' => true,
    'disable_header' => true,
    'disable_footer' => true,
    'disable_titlebar' => true,
    'layout_wide' => true,
    'disable_mobile_bottom_menu' => true,
])
@section('title', __('MagicBots'))
@section('titlebar_actions', '')

@push('css')
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/picmo/picmo.min.css') }}"
    />
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.css') }}"
    />
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('assets/libs/jscolorpicker/dist/colorpicker.css') }}"
    >

    <style>
        .lqd-chatbot-emoji .picmo__picker.picmo__picker {
            --background-color: hsl(var(--background));
            --secondary-background-color: hsl(var(--background));
            --border-color: hsl(0 0% 0% / 5%);
            --search-background-color: hsl(0 0% 0% / 5%);
            --search-height: 40px;
            --accent-color: hsl(var(--primary));
            --ui-font-size: 14px;
            /* --picker-width: 100%; */
            /* --emoji-size-multiplier: 1; */
            /* --emoji-preview-size: 2em; */
            --emoji-size: 1.75rem;
            /* --emoji-area-height: min(300px, calc(min(var(--lqd-ext-chat-window-h), calc(100vh - (var(--lqd-ext-chat-offset-y) * 2) - var(--lqd-ext-chat-trigger-h) - var(--lqd-ext-chat-window-y-offset))) - 140px)); */
        }

        .lqd-chatbot-emoji .picmo__picker .picmo__searchContainer .picmo__searchField {
            border-radius: 8px;
            font-size: 14px;
        }

        .lqd-chatbot-emoji .picmo__picker .picmo__emojiButton {
            border-radius: 6px;
        }

        .lqd-chatbot-emoji .picmo__picker .picmo__preview {
            display: none;
        }

        @media(min-width: 992px) {
            .lqd-header {
                display: none !important;
            }

            .focus-mode .lqd-page-content-container {
                max-width: 100% !important;
            }
        }
    </style>
@endpush

@push('after-body-open')
    <script>
        (() => {
            document.body.classList.add('navbar-shrinked');
        })();
    </script>
@endpush

@section('content')
    <div
        class="lqd-ext-chatbot-history flex h-screen flex-col max-lg:[--header-height:65px] lg:flex-row"
        x-data="chatbotAgentMessages"
    >
        <div
            class="lqd-ext-chatbot-history-sidebar group/history-sidebar relative flex shrink-0 flex-col bg-foreground/[3%] lg:w-[clamp(250px,27%,400px)]"
            :class="{ 'mobile-dropdown-open': mobileDropdownOpen }"
        >
            @includeIf('chatbot-agent::particles.conversations-filter')

            <div
                class="transition-all max-lg:fixed max-lg:bottom-0 max-lg:top-[calc(var(--header-height)+3.5rem)] max-lg:z-10 max-lg:flex max-lg:h-0 max-lg:w-full max-lg:flex-col max-lg:overflow-hidden max-lg:bg-background/90 max-lg:backdrop-blur-lg lg:contents max-lg:[&.active]:h-full"
                :class="{ 'active': mobile.filtersVisible }"
            >
                @includeIf('chatbot-agent::particles.conversations-sort')

                @includeIf('chatbot-agent::particles.conversations-channel-filter')

                @include('chatbot-agent::particles.conversations-list')
            </div>
        </div>

        <div
            class="lqd-ext-chatbot-history-content-wrap flex h-full grow flex-col overflow-y-auto lg:w-1/2"
            x-ref="historyContentWrap"
        >
            @include('chatbot-agent::particles.messages-header')

            <div
                class="lqd-ext-chatbot-history-messages relative flex h-full flex-col gap-2 pt-10"
                x-ref="historyMessages"
            >
                <div class="mt-auto space-y-2 px-4 max-lg:pb-[calc(var(--bottom-menu-height)+2rem)] xl:px-10">
                    @include('chatbot-agent::particles.messages-list')
                </div>

                @include('chatbot-agent::particles.messages-form')
            </div>
        </div>

        <div
            class="lqd-ext-chatbot-contact-info flex flex-col border-s transition-all max-lg:fixed max-lg:bottom-0 max-lg:top-[calc(var(--header-height)+3.5rem)] max-lg:z-10 max-lg:h-0 max-lg:max-h-[calc(100%-var(--header-height)-3.5rem)] max-lg:w-full max-lg:overflow-hidden max-lg:bg-background/90 max-lg:backdrop-blur-lg lg:w-[clamp(250px,27%,400px)] max-lg:[&.active]:h-full"
            :class="{ 'active': mobile.contactInfoVisible }"
        >
            @include('chatbot-agent::particles.contact-info-head')

            <div class="grid grow grid-cols-1 place-items-start overflow-y-auto">
                @include('chatbot-agent::particles.contact-info-tab-details', ['hasCustomerTagExtension' => $hasCustomerTagExtension])
                @include('chatbot-agent::particles.contact-info-tab-history')
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ custom_theme_url('assets/libs/jscolorpicker/dist/colorpicker.iife.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/fslightbox/fslightbox.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/picmo/picmo.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/air-datepicker.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/datepicker/locale/en.js') }}"></script>
    @if (\App\Helpers\Classes\MarketplaceHelper::isRegistered('chatbot-agent'))
        <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    @endif

    <script
        src="https://cdn.ably.com/lib/ably.min-1.js"
        type="text/javascript"
    ></script>
    <script>
        const customerTagRoutes = @json($customerTagRoutes);
    </script>
    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('chatbotAgentMessages', () => ({
                    selectedStatus: {
                        label: '{{ __('All') }}',
                        count: 0,
                        status: 'all'
                    },
                    statusCount: {
                        all: 0,
                        new: 0,
                        closed: 0,
                    },
                    filters: {
                        status: '{{ array_key_first($status_filters) }}',
                        agent: '{{ array_key_first($agent_filters) }}',
                        sort: '{{ array_key_first($sort_filters) }}',
                        channel: '{{ array_key_first($channel_filters) }}',
                        chatbot: '{{ array_key_first($conversation_agent_filters) }}',
                        unreadsOnly: false,
                        dateRange: {
                            start: null,
                            end: null,
                        },
                    },
                    chatsList: [],
                    attachmentsPreview: [],
                    activeChat: null,
                    activeSessionId: null,
                    fetching: false,
                    allLoaded: false,
                    /** * @type {IntersectionObserver} */
                    loadMoreIO: null,
                    originalLoadMoreHref: null,
                    mobileDropdownOpen: false,
                    messageTime: null,
                    conversationsSearchFormVisible: false,
                    messagesSearchFormVisible: false,
                    showEmojiPicker: false,

                    hasCustomerTags: @json($hasCustomerTagExtension),
                    customerTagModal: {
                        show: false,
                        loading: false,
                        saving: false,
                        creating: false,
                        items: [],
                        selected: [],
                        form: {
                            tag: '',
                            tag_color: '#6366f1',
                        },
                    },
                    showCannedResponses: false,
                    showRewriteModal: false,
                    messageRewriteLoading: false,
                    rewriteOptions: [{
                            key: 'improve',
                            label: '{{ __('Make it better') }}',
                        },
                        {
                            key: 'lengthen',
                            label: '{{ __('Make it longer') }}',
                        },
                        {
                            key: 'fix_grammar',
                            label: '{{ __('Fix Grammar Mistakes') }}',
                        },
                    ],
                    cannedResponses: [],
                    cannedResponsesLoaded: false,
                    cannedResponsesLoading: false,
                    cannedResponsesSearch: '',

                    datepicker: null,
                    contactInfo: {
                        activeTab: 'details',
                        editMode: false,
                    },
                    mobile: {
                        filtersVisible: false,
                        contactInfoVisible: false,
                    },
                    userConversationHistory: [],
                    historyLoading: false,

                    showExportOptions: false,

                    async init() {
                        this.onSendMessage = this.onSendMessage.bind(this);
                        this.setActiveChat = this.setActiveChat.bind(this);

                        this.originalLoadMoreHref = this.$refs.loadMore.href;

                        await this.fetchChats({
                            loadMore: true
                        });

                        await this.initAbly();

                        await this.getConversationsHistory(this.activeChat?.session_id);

                        this.setupLoadMoreIO();

                        Alpine.store('chatbotAgentMessages', this);

                        this.initEmojiPicker();
                        this.initDateRangePicker();
                    },
                    initDateRangePicker() {
                        this.$nextTick(() => {
                            this.datepicker = new AirDatepicker('#conversationsDatepicker', {
                                locale: defaultLocale,
                                inline: true,
                                range: true,
                                multipleDatesSeparator: ' - ',
                                dateFormat: 'yyyy-MM-dd',
                                onSelect: ({
                                    date,
                                    formattedDate,
                                    datepicker
                                }) => {
                                    if (Array.isArray(date) && date.length === 2) {
                                        this.filters.dateRange.start = date[0];
                                        this.filters.dateRange.end = date[1];
                                    }
                                }
                            });
                        });
                    },
                    initEmojiPicker() {
                        const picker = picmo.createPicker({
                            rootElement: this.$refs.emojiPicker
                        });

                        picker.addEventListener('emoji:select', event => {
                            this.$refs.message.value += event.emoji;
                            this.showEmojiPicker = false;

                            this.$refs.message.focus()
                        });
                    },
                    async loadMore() {
                        if (this.fetching || this.allLoaded) return;

                        await this.fetchChats({
                            loadMore: true
                        });
                    },
                    setupLoadMoreIO() {
                        this.loadMoreIO = new IntersectionObserver(async ([entry], observer) => {
                            if (entry.isIntersecting && !this.fetching && !this.allLoaded) {
                                await this.loadMore();
                            }
                        });

                        this.loadMoreIO.observe(this.$refs.loadMoreWrap);
                    },
                    normalizeConversationPayload(conversation) {
                        if (!conversation) {
                            return conversation;
                        }

                        if (!Array.isArray(conversation.customer_tags)) {
                            conversation.customer_tags = [];
                        }

                        return conversation;
                    },
                    async toggleCustomTagModal() {
                        this.customerTagModal.show = !this.customerTagModal.show;

                        if (this.customerTagModal.show && !this.customerTagModal.items.length) {
                            this.$nextTick(() => this.loadCustomerTags())
                        };
                    },
                    syncCustomerTagSelection() {
                        if (!this.hasCustomerTags) {
                            return;
                        }

                        if (!this.activeChat || !Array.isArray(this.activeChat.customer_tags)) {
                            this.customerTagModal.selected = [];
                            return;
                        }

                        this.customerTagModal.selected = this.activeChat.customer_tags.map(tag => tag.id);
                    },
                    resetCustomerTagForm() {
                        this.customerTagModal.form = {
                            tag: '',
                            tag_color: '#6366f1',
                        };
                    },
                    async loadCustomerTags() {
                        if (!this.hasCustomerTags || !customerTagRoutes.index || !this.activeChat?.id) {
                            return;
                        }

                        this.customerTagModal.loading = true;

                        try {
                            const res = await fetch(`${customerTagRoutes.index}?conversation_id=${this.activeChat.id}`, {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                            });
                            const data = await res.json();

                            if (!res.ok) {
                                if (data.message) {
                                    toastr.error(data.message);
                                }
                                return;
                            }

                            this.customerTagModal.items = data.tags ?? [];
                            this.customerTagModal.selected = data.assigned ?? [];

                            if (!this.customerTagModal.items.length) {
                                this.resetCustomerTagForm();
                            }
                        } finally {
                            this.customerTagModal.loading = false;
                        }
                    },
                    async toggleCustomerTagSelection(tagId) {
                        if (!this.hasCustomerTags || !customerTagRoutes.sync || !this.activeChat?.id || this.customerTagModal.saving) {
                            return;
                        }

                        if (!this.customerTagModal.items.length) {
                            toastr.error('{{ __('Please create a tag first.') }}');
                            return;
                        }

                        const wasSelected = this.customerTagModal.selected.includes(tagId);
                        const previousSelection = [...this.customerTagModal.selected];

                        if (wasSelected) {
                            this.customerTagModal.selected = this.customerTagModal.selected.filter(id => id !== tagId);
                        } else {
                            this.customerTagModal.selected = [...this.customerTagModal.selected, tagId];
                        }

                        const success = await this.syncCustomerTags();

                        if (!success) {
                            this.customerTagModal.selected = previousSelection;
                        }
                    },
                    async syncCustomerTags(afterSuccess = null) {
                        if (!this.hasCustomerTags || !customerTagRoutes.sync || !this.activeChat?.id) {
                            return false;
                        }

                        @if ($app_is_demo)
                            toastr.error('This feature is disabled in demo version.');
                            return false;
                        @else
                            this.customerTagModal.saving = true;

                            try {
                                const res = await fetch(customerTagRoutes.sync, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        conversation_id: this.activeChat.id,
                                        tag_ids: this.customerTagModal.selected,
                                    }),
                                });
                                const data = await res.json();

                                if (!res.ok) {
                                    if (data.message) {
                                        toastr.error(data.message);
                                    }
                                    return false;
                                }

                                this.activeChat.customer_tags = data.data?.customer_tags ?? [];

                                const chatIndex = this.chatsList.findIndex(chat => chat.id == this.activeChat.id);

                                if (chatIndex !== -1) {
                                    this.chatsList[chatIndex].customer_tags = this.activeChat.customer_tags;
                                }

                                this.syncCustomerTagSelection();

                                if (typeof afterSuccess === 'function') {
                                    afterSuccess();
                                }

                                toastr.success(data.message ?? '{{ __('Customer tags updated.') }}');

                                return true;
                            } catch (error) {
                                toastr.error('{{ __('Unable to update tags right now.') }}');
                                return false;
                            } finally {
                                this.customerTagModal.saving = false;
                            }
                        @endif
                    },
                    async createCustomerTag() {
                        if (!this.hasCustomerTags || !customerTagRoutes.store) {
                            return;
                        }

                        @if ($app_is_demo)
                            toastr.error('This feature is disabled in demo version.');
                            return;
                        @else
                            const tagName = (this.customerTagModal.form.tag || '').trim();

                            if (!tagName) {
                                toastr.error('{{ __('Please enter a tag name.') }}');
                                return;
                            }

                            this.customerTagModal.creating = true;

                            try {
                                const res = await fetch(customerTagRoutes.store, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        tag: tagName,
                                        tag_color: this.customerTagModal.form.tag_color,
                                    }),
                                });
                                const data = await res.json();

                                if (!res.ok) {
                                    if (data.message) {
                                        toastr.error(data.message);
                                    }
                                    return;
                                }

                                toastr.success(data.message ?? '{{ __('Customer tag created.') }}');

                                this.customerTagModal.form.tag = '';

                                await this.loadCustomerTags();
                            } finally {
                                this.customerTagModal.creating = false;
                            }
                        @endif
                    },
                    async updateConversationDetails({
                        name = '',
                        color = ''
                    }) {
                        @if ($app_is_demo)
                            toastr.error('This feature is disabled in demo version.');
                            return;
                        @else
                            name = name || this.$refs.conversationNameInput.value;
                            color = color || this.activeChat.color;

                            let conversation_name = name.trim();
                            let conversation_color = color.trim();

                            const res = await fetch('{{ route('dashboard.chatbot-agent.conversations.update') }}', {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    conversation_id: this.activeChat.id,
                                    conversation_name: conversation_name,
                                    color: conversation_color,
                                }),
                            });
                            const data = await res.json();

                            if (!res.ok) {
                                toastr.error(data.message || '{{ __('Failed to update conversation details.') }}');
                                return;
                            }

                            this.activeChat.conversation_name = conversation_name;
                            this.activeChat.color = conversation_color;

                            toastr.success('{{ __('Conversation details updated successfully.') }}');
                        @endif
                    },
                    async initAbly() {
                        @if (setting('ably_public_key') && is_string(setting('ably_public_key')) && strlen(setting('ably_public_key')) > 8)
                            const realtime = new Ably.Realtime.Promise("{{ setting('ably_public_key') }}");

                            const channel = realtime.channels.get("panel-conversation-{{ \Illuminate\Support\Facades\Auth::id() }}");

                            await channel.subscribe("conversation", async message => {
                                const newMessageData = this.normalizeConversationPayload(message.data.chatbotConversation);
                                const newMessageId = newMessageData.id;
                                const conversationIsActive = this.activeChat && newMessageId === this.activeChat.id;
                                const conversationIndex = this.chatsList.findIndex(chat => chat.id === newMessageId);

                                if (conversationIndex === -1) {
                                    newMessageData.histories = [...newMessageData.histories || [], newMessageData.lastMessage];
                                    this.chatsList.unshift(newMessageData);
                                } else {
                                    const conversation = this.chatsList.at(conversationIndex);
                                    conversation.histories?.push(newMessageData.lastMessage);
                                    conversation.lastMessage = newMessageData.lastMessage;
                                }

                                if (conversationIsActive) {
                                    this.activeChat.customer_tags = newMessageData.customer_tags;
                                    this.syncCustomerTagSelection();
                                    this.scrollMessagesToBottom();

                                    // Mark the new message as read locally since agent is viewing this conversation
                                    if (newMessageData.lastMessage) {
                                        newMessageData.lastMessage.read_at = new Date().toISOString();
                                    }

                                    // Mark as read on the server
                                    fetch('{{ route('dashboard.chatbot-agent.history') }}?conversation_id=' + this.activeChat.id, {
                                        method: 'GET',
                                        headers: {
                                            'Accept': 'application/json'
                                        },
                                    }).catch(() => {});
                                }
                            });
                        @endif

                    },

                    async onSendMessage() {
                        @if ($app_is_demo)
                            toastr.error('This feature is disabled in demo version.');
                            return;
                        @else
                            const messageInput = this.$refs.message;
                            const mediaInput = this.$refs.media;
                            const messageString = messageInput.value.trim();

                            if (!messageString && !mediaInput.files.length) {
                                return toastr.error('{{ __('Please fill required fields.') }}')
                            };

                            const isInternalNote = this.$refs.isInternalNote.value === '1';

                            const newUserMessage = {
                                id: new Date().getTime(),
                                user_id: {{ \Illuminate\Support\Facades\Auth::id() }},
                                message: messageString,
                                media_name: mediaInput.files.length ? mediaInput.files[0].name : '',
                                media_url: mediaInput.files.length ? '#' : '',
                                role: 'assistant',
                                is_internal_note: isInternalNote,
                                user: true,
                                created_at: new Date().toLocaleString()
                            };
                            const formData = new FormData();

                            let chatIndex = this.chatsList.findIndex(chat => chat.id == this.activeChat.id);

                            if (chatIndex !== -1) {
                                const histories = this.chatsList[chatIndex].histories;

                                if (!Array.isArray(histories)) {
                                    this.chatsList[chatIndex].histories = [];
                                } else {
                                    this.chatsList[chatIndex].histories = histories;
                                }

                                this.chatsList[chatIndex].histories.push(newUserMessage);
                            }

                            this.scrollMessagesToBottom();

                            const promises = [];
                            const files = mediaInput.files;
                            const messagesArray = [
                                ['message', messageString]
                            ];

                            const sendFirstMessage = async () => {
                                const formData = new FormData();

                                formData.append('conversation_id', this.activeChat.id);
                                formData.append('message', messageString);
                                formData.append('is_internal_note', this.$refs.isInternalNote.value);

                                if (files.length) {
                                    const dataTransfer = new DataTransfer();
                                    dataTransfer.items.add(files[0]);

                                    formData.append('media', dataTransfer.files[0]);
                                }

                                const res = await fetch('{{ route('dashboard.chatbot-agent.history') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                    },
                                    body: formData
                                });

                                return await res.json();
                            }
                            const sendTheRestAttachments = async () => {
                                if (files.length <= 1) {
                                    return [Promise.resolve(true)];
                                };

                                return Array.from(files).map(async (file, index) => {
                                    if (index === 0) return;

                                    const formData = new FormData();
                                    const dataTransfer = new DataTransfer();

                                    dataTransfer.items.add(file);

                                    formData.append('conversation_id', this.activeChat.id);
                                    formData.append('media', dataTransfer.files[0]);

                                    const res = await fetch('{{ route('dashboard.chatbot-agent.history') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Accept': 'application/json',
                                        },
                                        body: formData
                                    });

                                    return await res.json();
                                });

                            }

                            messageInput.value = '';
                            this.attachmentsPreview = [];
                            this.$refs.submitBtn.setAttribute('disabled', 'disabled');

                            Promise.all([sendFirstMessage(), sendTheRestAttachments()])
                                .then(([firstMessage]) => {
                                    mediaInput.value = null;

                                    this.chatsList[chatIndex].histories.at(-1).media_name = firstMessage.data.media_name;
                                    this.chatsList[chatIndex].histories.at(-1).media_url = firstMessage.data.media_url;
                                })
                                .catch(e => {
                                    toastr.error('{{ __('Something went wrong. Please try again later.') }}')
                                });
                        @endif
                    },
                    onMessageFieldHitEnter(event) {
                        if (!event.shiftKey) {
                            this.onSendMessage();
                        } else {
                            event.target.value += '\n';
                            event.target.scrollTop = event.target.scrollHeight
                        };
                    },
                    onMessageFieldInput(event) {
                        const messageString = this.$refs.message.value.trim();

                        if (messageString) {
                            this.$refs.submitBtn.removeAttribute('disabled');
                        } else {
                            this.$refs.submitBtn.setAttribute('disabled', 'disabled');
                        }
                    },
                    async toggleCannedResponses() {
                        if (this.showCannedResponses) {
                            this.closeCannedResponses();
                            return;
                        }

                        await this.openCannedResponses();
                    },
                    async openCannedResponses() {
                        this.showEmojiPicker = false;

                        if (!this.cannedResponsesLoaded && !this.cannedResponsesLoading) {
                            await this.fetchCannedResponses();
                        }

                        this.cannedResponsesSearch = '';
                        this.showCannedResponses = true;
                    },
                    closeCannedResponses() {
                        this.showCannedResponses = false;
                    },
                    toggleRewriteModal() {
                        if (this.messageRewriteLoading) {
                            return;
                        }

                        this.showEmojiPicker = false;
                        this.closeCannedResponses();
                        this.showRewriteModal = !this.showRewriteModal;
                    },
                    closeRewriteModal() {
                        this.showRewriteModal = false;
                    },
                    async fetchCannedResponses() {
                        this.cannedResponsesLoading = true;

                        try {
                            const res = await fetch('{{ route('dashboard.chatbot-agent.canned-responses') }}', {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                            });

                            const data = await res.json();

                            if (!res.ok) {
                                throw new Error(data?.message || '{{ __('Unable to load canned responses. Please try again.') }}');
                            }

                            this.cannedResponses = Array.isArray(data?.data) ? data.data : [];
                            this.cannedResponsesLoaded = true;
                        } catch (error) {
                            toastr.error(error?.message || '{{ __('Unable to load canned responses. Please try again.') }}');
                        } finally {
                            this.cannedResponsesLoading = false;
                        }
                    },
                    filteredCannedResponses() {
                        const searchTerm = this.cannedResponsesSearch.trim().toLowerCase();

                        if (!searchTerm) {
                            return this.cannedResponses;
                        }

                        return this.cannedResponses.filter((item) => {
                            const title = (item.title || '').toLowerCase();
                            const content = this.stripHtml(item.content || '').toLowerCase();

                            return title.includes(searchTerm) || content.includes(searchTerm);
                        });
                    },
                    stripHtml(htmlString) {
                        const temp = document.createElement('div');
                        temp.innerHTML = htmlString;

                        return (temp.textContent || temp.innerText || '').trim();
                    },
                    insertCannedResponse(response) {
                        const textarea = this.$refs.message;

                        if (!textarea) {
                            return;
                        }

                        const content = this.stripHtml(response?.content || '');

                        if (!content) {
                            return;
                        }

                        const existingValue = textarea.value;
                        const hasExistingContent = existingValue.trim().length > 0;
                        const needsNewLine = hasExistingContent && !existingValue.endsWith('\n');
                        const separator = needsNewLine ? '\n' : '';

                        textarea.value = `${existingValue}${separator}${content}`;
                        textarea.scrollTop = textarea.scrollHeight;

                        this.onMessageFieldInput();
                        this.closeCannedResponses();

                        textarea.focus();
                        const cursorPosition = textarea.value.length;
                        textarea.setSelectionRange(cursorPosition, cursorPosition);
                    },
                    async rewriteMessage(variantKey) {
                        if (!variantKey) {
                            return;
                        }

                        @if ($app_is_demo)
                            toastr.error('This feature is disabled in demo version.');
                            return;
                        @else
                            const messageInput = this.$refs.message;

                            if (!messageInput) {
                                return;
                            }

                            const currentValue = messageInput.value.trim();

                            if (!currentValue) {
                                this.showRewriteModal = true;
                                toastr.warning('{{ __('Please enter a message first.') }}');
                                return;
                            }

                            if (this.messageRewriteLoading) {
                                return;
                            }

                            this.messageRewriteLoading = true;
                            this.showRewriteModal = false;

                            try {
                                const res = await fetch('{{ route('dashboard.chatbot-agent.message-rewrite') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        message: currentValue,
                                        variant: variantKey,
                                    }),
                                });

                                const data = await res.json();

                                if (!res.ok || !data?.result) {
                                    throw new Error(data?.message || '{{ __('Unable to rewrite the message. Please try again.') }}');
                                }

                                messageInput.value = data.result.trim();
                                messageInput.dispatchEvent(new Event('input', {
                                    bubbles: true
                                }));
                                this.onMessageFieldInput();
                                this.scrollMessagesToBottom();
                            } catch (error) {
                                toastr.error(error?.message || '{{ __('Unable to rewrite the message. Please try again.') }}');
                            } finally {
                                this.messageRewriteLoading = false;
                            }
                        @endif
                    },
                    async fetchHistories(id) {
                        const res = await fetch('{{ route('dashboard.chatbot-agent.history') }}?conversation_id=' + id, {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                        });

                        const data = await res.json();

                        return data.data;
                    },
                    async fetchChats(opts = {}) {
                        const options = {
                            loadMore: false,
                            ...opts
                        };

                        if (!options.loadMore) {
                            this.$refs.loadMore.href = this.originalLoadMoreHref;
                        }

                        this.fetching = true;

                        let url =
                            `${this.$refs.loadMore.href}&agentFilter=${this.filters.agent}&chatbot_channel=${this.filters.channel}&chatbot_id=${this.filters.chatbot}&status=${this.filters.status}&unread=${this.filters.unreadsOnly}&sort=${this.filters.sort}`;

                        // Add date range parameters if they exist
                        if (this.filters.dateRange.start && this.filters.dateRange.end) {
                            const formatLocalDate = (d) => {
                                if (d instanceof Date) {
                                    const year = d.getFullYear();
                                    const month = String(d.getMonth() + 1).padStart(2, '0');
                                    const day = String(d.getDate()).padStart(2, '0');
                                    return `${year}-${month}-${day}`;
                                }
                                return d;
                            };
                            const startDate = formatLocalDate(this.filters.dateRange.start);
                            const endDate = formatLocalDate(this.filters.dateRange.end);
                            url += `&start_date=${startDate}&end_date=${endDate}`;
                        }

                        const res = await fetch(url, {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                        });
                        const data = await res.json();

                        let {
                            data: conversations,
                            status_count: statusCount,
                        } = data;

                        if (!res.ok || !conversations) {
                            if (data.message) {
                                toastr.error(data.message);
                            }
                            return;
                        }

                        conversations = Array.isArray(conversations) ?
                            conversations.map(conversation => this.normalizeConversationPayload(conversation)) : [];

                        this.statusCount = statusCount;

                        if (this.selectedStatus.status === '{{ $status_filters['all']['key'] }}') {
                            this.selectedStatus = {
                                label: '{{ $status_filters['all']['label'] }}',
                                count: statusCount.all ?? 0,
                                status: 'all'
                            }
                        } else if (this.selectedStatus.status === '{{ $status_filters['new']['key'] }}') {
                            this.selectedStatus = {
                                label: '{{ $status_filters['new']['label'] }}',
                                count: statusCount.new ?? 0,
                                status: 'new'
                            }
                        } else if (this.selectedStatus.status === '{{ $status_filters['closed']['key'] }}') {
                            this.selectedStatus = {
                                label: '{{ $status_filters['closed']['label'] }}',
                                count: statusCount.closed ?? 0,
                                status: 'closed'
                            }
                        }

                        this.lastTimeFetch = new Date().getTime();

                        if (!options.loadMore) {
                            this.chatsList = conversations;

                            if (this.activeChat) {
                                const refreshedActive = this.chatsList.find(chat => chat.id == this.activeChat.id);

                                if (refreshedActive) {
                                    this.activeChat = refreshedActive;
                                    this.syncCustomerTagSelection();
                                }
                            }
                        } else {
                            this.chatsList.push(...conversations);
                        }

                        this.allLoaded = data.meta.current_page >= data.meta.last_page;

                        this.$refs.loadMore.href = data.links.next ?? data.links.first;

                        if ((!options.loadMore || !this.activeChat) && this.chatsList.length) {
                            this.activeChat = this.chatsList[0];
                            this.syncCustomerTagSelection();
                        }

                        this.fetching = false;

                        this.scrollMessagesToBottom();
                    },
                    async handleConversationsSearch() {
                        const query = this.$refs.convSearchForm?.querySelector('input[name="conv_search"]')?.value?.trim();
                        this.fetching = true;

                        const res = await fetch('{{ route('dashboard.chatbot-agent.conversations.search') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            },
                            body: JSON.stringify({
                                search: query,
                            }),
                        });
                        const data = await res.json();
                        const {
                            data: conversations
                        } = data;

                        if (!res.ok || !conversations) {
                            if (data.message) {
                                toastr.error(data.message);
                            }
                            return;
                        }

                        const normalizedConversations = Array.isArray(conversations) ?
                            conversations.map(conversation => this.normalizeConversationPayload(conversation)) : [];

                        this.chatsList = normalizedConversations;
                        this.allLoaded = true;

                        this.activeChat = normalizedConversations.at(0) ?? null;
                        this.syncCustomerTagSelection();

                        this.fetching = false;
                        this.scrollMessagesToBottom();
                    },
                    async handleMessagesSearch(inputElement) {
                        const searchString = inputElement.value;

                        if (!searchString.trim()) {
                            if (this.activeChat) {
                                this.activeChat.histories = await this.fetchHistories(this.activeChat.id);
                            }
                            return;
                        }

                        if (this.activeChat?.histories) {
                            const filteredHistories = this.activeChat.histories.filter(message =>
                                message.message && message.message.toLowerCase().includes(searchString.toLowerCase())
                            );

                            this.activeChat.histories = filteredHistories;
                        }
                    },

                    async filterAgent(agent) {
                        if (this.filters.agent === agent) return;

                        this.filters.agent = agent;

                        await this.fetchChats();
                    },
                    async filterChatbot(chatbotId) {
                        this.filters.chatbot = chatbotId;

                        await this.fetchChats();
                    },

                    async filterStatus(status, label) {
                        if (this.filters.status === status) return;

                        this.filters.status = status;

                        this.selectedStatus = {
                            label: label,
                            status: status,
                            count: this.statusCount[status] ?? 0
                        }

                        this.fetchChats();

                        toastr.success('{{ trans('Filter ticket status') }}')
                    },

                    async filterSort(sort) {
                        if (this.filters.sort === sort) return;

                        this.filters.sort = sort;

                        await this.fetchChats();
                    },

                    async filterUnread(event) {
                        const checkbox = event.target;
                        const unreadsOnly = checkbox.checked;

                        if (this.filters.unreadsOnly === unreadsOnly) return;

                        this.filters.unreadsOnly = unreadsOnly;

                        await this.fetchChats();
                    },
                    async closeConversation(conversationId) {
                        if (conversationId == null) return;

                        @if ($app_is_demo)
                            return toastr.error('This feature is disabled in demo version.');
                        @endif

                        if (!confirm('Do you want closed this conversation history?')) {
                            return;
                        }

                        const res = await fetch('{{ route('dashboard.chatbot-agent.conversations.closed') }}?conversation_id=' + conversationId, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                        });

                        const data = await res.json();

                        if (data.status === 'success') {
                            const chatIndex = this.chatsList.findIndex(chat => chat.id == this.activeChat.id);

                            if (chatIndex === -1) return;

                            this.chatsList.splice(chatIndex, 1);

                            this.activeChat = this.chatsList.at(Math.max(0, Math.min(this.chatsList.length - 1, chatIndex)));

                            this.syncCustomerTagSelection();

                            toastr.success('{{ __('Conversation closed successfully.') }}');
                        } else {
                            toastr.error(data.message || '{{ __('Failed to close conversation.') }}');
                        }
                    },

                    async pinConversation(conversationId) {
                        if (conversationId == null) return;

                        @if ($app_is_demo)
                            return toastr.error('This feature is disabled in demo version.');
                        @endif

                        const res = await fetch('{{ route('dashboard.chatbot-agent.conversations.pinned') }}?conversation_id=' + conversationId, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                        });

                        const data = await res.json();

                        if (data.status === 'success') {
                            const chat = this.chatsList.find(chat => chat.id == data.data.id);

                            if (!chat) return;

                            chat.pinned = data.data.pinned;

                            // Re-sort chatsList to reflect the pinned status
                            this.chatsList.sort((a, b) => {
                                // First sort by pinned status (higher pinned numbers first)
                                if (a.pinned !== b.pinned) {
                                    return b.pinned - a.pinned;
                                }

                                // If both have same pinned status, sort by date based on filters.sort
                                const aDate = new Date(a.updated_at || a.created_at);
                                const bDate = new Date(b.updated_at || b.created_at);

                                if (this.filters.sort === 'newest') {
                                    return bDate - aDate;
                                } else {
                                    return aDate - bDate;
                                }
                            });

                            toastr.success(data.message ?? '{{ __('Conversation pin status updated successfully.') }}');
                        } else {
                            toastr.error(data.message || '{{ __('Failed to update conversation pin status.') }}');
                        }
                    },

                    async getConversationsHistory(sessionId) {
                        if (sessionId == null) return;

                        this.activeSessionId = sessionId;
                        this.historyLoading = true;

                        try {
                            const res = await fetch(
                                `{{ route('dashboard.chatbot-agent.conversations.history.session') }}?sessionId=${sessionId}`, {
                                    method: 'GET',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                    },
                                });
                            const data = await res.json();
                            const {
                                data: conversations
                            } = data;

                            if (!res.ok || !conversations) {
                                if (data.message) {
                                    toastr.error(data.message);
                                }
                                return;
                            }

                            this.userConversationHistory = conversations;
                        } finally {
                            this.historyLoading = false;
                        }
                    },

                    async deleteConversation(conversationId) {
                        if (conversationId == null) return;

                        @if ($app_is_demo)
                            toastr.error('This feature is disabled in demo version.');
                            return;
                        @else
                            if (!confirm('Do you want delete this conversation history?')) {
                                return;
                            }

                            const res = await fetch('{{ route('dashboard.chatbot-agent.destroy') }}', {
                                method: 'DELETE',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    conversation_id: conversationId,
                                }),
                            });
                            const responseData = await res.json();

                            if (responseData.status == 'success') {
                                const chatIndex = this.chatsList.findIndex(chat => chat.id == this.activeChat.id);

                                if (chatIndex === -1) return;

                                this.chatsList.splice(chatIndex, 1);

                                this.activeChat = this.chatsList.at(Math.max(0, Math.min(this.chatsList.length - 1, chatIndex)));

                                this.syncCustomerTagSelection();
                            }
                        @endif
                    },

                    async setActiveChat(chatId) {
                        if (chatId == null) return;

                        this.activeChat = this.chatsList.find(chat => chat.id == chatId);

                        if (!this.activeChat) return;

                        this.activeChat.histories = await this.fetchHistories(this.activeChat.id);

                        this.mobileDropdownOpen = false;

                        this.mobile.filtersVisible = false;

                        await this.getConversationsHistory(this.activeChat?.session_id);

                        this.syncCustomerTagSelection();

                        this.scrollMessagesToBottom();
                    },

                    setAttachmentsPreview() {
                        this.attachmentsPreview = [];

                        for (const file of this.$refs.media.files) {
                            const reader = new FileReader();

                            reader.onload = e => {
                                this.attachmentsPreview.push({
                                    url: e.target.result,
                                    type: file.type
                                });
                            };

                            reader.readAsDataURL(file);
                        }
                    },

                    getFormattedString(string, options = {}) {
                        if (options.isHTML) {
                            return string;
                        }
                        if (!('markdownit' in window) || !string) return string;

                        string
                            .replace(/>(\s*\r?\n\s*)</g, '><')
                            .replace(/\n(?!.*\n)/, '');

                        const renderer = window.markdownit({
                            breaks: true,
                            highlight: (str, lang) => {
                                const language = lang && lang !== '' ? lang : 'md';
                                const codeString = str;

                                if (Prism.languages[language]) {
                                    const highlighted = Prism.highlight(codeString,
                                        Prism.languages[language], language);
                                    return `<pre class="language-${language}"><code data-lang="${language}" class="language-${language}">${highlighted}</code></pre>`;
                                }

                                return codeString;
                            }
                        });

                        renderer.use(function(md) {
                            md.core.ruler.after('inline', 'convert_links', function(state) {
                                state.tokens.forEach(function(blockToken) {

                                    if (blockToken.type !== 'inline') return;

                                    blockToken.children.forEach(function(token, idx) {
                                        const {
                                            content
                                        } = token;
                                        if (content.includes('<a ')) {
                                            const linkRegex = /(.*)(<a\s+[^>]*\s+href="([^"]+)"[^>]*>([^<]*)<\/a>?)(.*)/;
                                            const linkMatch = content.match(linkRegex);

                                            if (linkMatch) {
                                                const [, before, , href, text, after] = linkMatch;

                                                const beforeToken = new state.Token('text', '', 0);
                                                beforeToken.content = before;

                                                const newToken = new state.Token('link_open', 'a', 1);
                                                newToken.attrs = [
                                                    ['href', href],
                                                    ['target', '_blank']
                                                ];
                                                const textToken = new state.Token('text', '', 0);
                                                textToken.content = text;
                                                const closingToken = new state.Token('link_close', 'a', -1);

                                                const afterToken = new state.Token('text', '', 0);
                                                afterToken.content = after;

                                                blockToken.children
                                                    .splice(idx, 1, beforeToken, newToken, textToken, closingToken, afterToken);
                                            }
                                        }
                                    });
                                });
                            });
                        });

                        return renderer.render(renderer.utils.unescapeAll(string));
                    },
                    getUnreadMessages(chatId) {
                        if (chatId == null) return;

                        let chat = this.chatsList.find(chat => chat.id === chatId);

                        if (!chat) return;

                        return chat?.histories?.filter(history => history.role == 'user' && history.read_at == null)?.length ?? 0;
                    },

                    getAllUnreadMessages() {
                        const unreadMessages = this.chatsList?.reduce((previousValue, chat) => {
                            return previousValue + (this.getUnreadMessages(chat.id) ?? 0);
                        }, 0);

                        return unreadMessages;
                    },

                    scrollMessagesToBottom(smooth = false) {
                        this.$nextTick(() => {
                            this.$refs.historyContentWrap.scrollTo({
                                top: this.$refs.historyContentWrap.scrollHeight,
                                behavior: smooth ? 'smooth' : 'auto'
                            });
                        })
                    },
                    getDiffHumanTime(time) {
                        const diff = Math.floor((new Date() - new Date(time)) / 1000);

                        return diff < 60 ? " {{ __('Just now') }}" :
                            diff < 3600 ? (Math.floor(diff / 60) === 1 ?
                                "1 {{ __('minute ago') }}" : Math.floor(diff / 60) +
                                " {{ __('minutes ago') }}") :
                            diff < 86400 ? (Math.floor(diff / 3600) === 1 ?
                                "1 {{ __('hour ago') }}" : Math.floor(diff / 3600) +
                                " {{ __('hours ago') }}") :
                            Math.floor(diff / 86400) === 1 ? "1 {{ __('day ago') }}" : Math.floor(
                                diff / 86400) + " {{ __('days ago') }}"
                    },

                    getShortDiffHumanTime(time) {
                        const diff = Math.floor((new Date() - new Date(time)) / 1000);

                        return diff < 60 ? '{{ __('Just now') }}' :
                            diff < 3600 ? Math.floor(diff / 60) + '{{ __('m') }}' :
                            diff < 86400 ? Math.floor(diff / 3600) + '{{ __('h') }}' :
                            Math.floor(diff / 86400) + '{{ __('d') }}'
                    },

                    // Defined shop function without implementation to avoid errors
                    productSelectVariant() {},
                    productAddToCart() {},
                    updatingCart: false,

                    async applyDateRange() {
                        if (!this.filters.dateRange.start || !this.filters.dateRange.end) {
                            toastr.warning('{{ __('Please select a date range') }}');
                            return;
                        }

                        await this.fetchChats();
                        toastr.success('{{ __('Date filter applied') }}');
                    },

                    async clearDateRange() {
                        this.filters.dateRange.start = null;
                        this.filters.dateRange.end = null;

                        if (this.datepicker) {
                            this.datepicker.clear();
                        }

                        await this.fetchChats();
                        toastr.success('{{ __('Date filter cleared') }}');
                    },

                    async exportConversations(format) {
                        if (!this.chatsList || this.chatsList.length === 0) {
                            toastr.warning('{{ __('No conversations to export') }}');
                            return;
                        }

                        try {
                            toastr.info('{{ __('Preparing export... Please wait') }}');

                            // Fetch all messages for each conversation
                            const exportData = await this.prepareExportDataWithMessages();

                            switch (format) {
                                case 'csv':
                                    this.exportAsCSV(exportData);
                                    break;
                                case 'json':
                                    this.exportAsJSON(exportData);
                                    break;
                                case 'pdf':
                                    this.exportAsPDF(exportData);
                                    break;
                            }

                            toastr.success(`{{ __('Conversations exported as') }} ${format.toUpperCase()}`);
                        } catch (error) {
                            console.error('Export error:', error);
                            toastr.error('{{ __('Failed to export conversations') }}');
                        }
                    },

                    async prepareExportDataWithMessages() {
                        const conversationsWithMessages = [];

                        for (const chat of this.chatsList) {
                            // Fetch full message history for each conversation
                            const messages = await this.fetchHistories(chat.id);

                            conversationsWithMessages.push({
                                id: chat.id,
                                customer_name: chat.customer?.name || 'N/A',
                                customer_email: chat.customer?.email || 'N/A',
                                chatbot_name: chat.chatbot?.title || 'N/A',
                                channel: chat.chatbot_channel || 'N/A',
                                status: chat.ticket_status || 'N/A',
                                created_at: chat.created_at || 'N/A',
                                updated_at: chat.updated_at || 'N/A',
                                messages: messages.map(msg => ({
                                    id: msg.id,
                                    role: msg.role || 'N/A',
                                    message: msg.message || '',
                                    created_at: msg.created_at || 'N/A',
                                    user_name: msg.user?.name || 'N/A'
                                }))
                            });
                        }

                        return conversationsWithMessages;
                    },

                    exportAsCSV(data) {
                        const headers = ['Conversation ID', 'Customer Name', 'Customer Email', 'Chatbot', 'Channel', 'Status', 'Message ID', 'Role',
                            'User Name', 'Message', 'Message Created At', 'Conversation Created At'
                        ];
                        const csvRows = [headers.join(',')];

                        data.forEach(conv => {
                            if (conv.messages && conv.messages.length > 0) {
                                conv.messages.forEach(msg => {
                                    const values = [
                                        conv.id,
                                        `"${conv.customer_name.replace(/"/g, '""')}"`,
                                        `"${conv.customer_email.replace(/"/g, '""')}"`,
                                        `"${conv.chatbot_name.replace(/"/g, '""')}"`,
                                        `"${conv.channel.replace(/"/g, '""')}"`,
                                        `"${conv.status.replace(/"/g, '""')}"`,
                                        msg.id,
                                        `"${msg.role.replace(/"/g, '""')}"`,
                                        `"${msg.user_name.replace(/"/g, '""')}"`,
                                        `"${(msg.message || '').replace(/"/g, '""').replace(/\n/g, ' ')}"`,
                                        `"${msg.created_at}"`,
                                        `"${conv.created_at}"`
                                    ];
                                    csvRows.push(values.join(','));
                                });
                            } else {
                                // If no messages, still add conversation info
                                const values = [
                                    conv.id,
                                    `"${conv.customer_name.replace(/"/g, '""')}"`,
                                    `"${conv.customer_email.replace(/"/g, '""')}"`,
                                    `"${conv.chatbot_name.replace(/"/g, '""')}"`,
                                    `"${conv.channel.replace(/"/g, '""')}"`,
                                    `"${conv.status.replace(/"/g, '""')}"`,
                                    'N/A',
                                    'N/A',
                                    'N/A',
                                    'No messages',
                                    'N/A',
                                    `"${conv.created_at}"`
                                ];
                                csvRows.push(values.join(','));
                            }
                        });

                        const csvString = csvRows.join('\n');
                        const blob = new Blob([csvString], {
                            type: 'text/csv;charset=utf-8;'
                        });
                        this.downloadFile(blob, `conversations_${this.getTimestamp()}.csv`);
                    },

                    exportAsJSON(data) {
                        const jsonString = JSON.stringify(data, null, 2);
                        const blob = new Blob([jsonString], {
                            type: 'application/json'
                        });
                        this.downloadFile(blob, `conversations_${this.getTimestamp()}.json`);
                    },

                    exportAsPDF(data) {
                        const {
                            jsPDF
                        } = window.jspdf;
                        const doc = new jsPDF();

                        const pageWidth = doc.internal.pageSize.getWidth();
                        const margin = 10;
                        const maxWidth = pageWidth - (margin * 2);
                        let y = 20;

                        // Title
                        doc.setFontSize(16);
                        doc.text('Conversations Export', margin, y);
                        y += 10;

                        // Export info
                        doc.setFontSize(10);
                        doc.text(`Export Date: ${new Date().toLocaleString()}`, margin, y);
                        y += 7;
                        doc.text(`Total Conversations: ${data.length}`, margin, y);
                        y += 10;

                        // Conversations with messages
                        doc.setFontSize(8);
                        data.forEach((conv, convIndex) => {
                            // Check space for conversation header
                            if (y > 270) {
                                doc.addPage();
                                y = 20;
                            }

                            // Conversation Header
                            doc.setFontSize(10);
                            doc.setFont(undefined, 'bold');
                            doc.text(`Conversation #${conv.id} - ${conv.customer_name}`, margin, y);
                            y += 5;

                            doc.setFontSize(8);
                            doc.setFont(undefined, 'normal');
                            doc.text(`Email: ${conv.customer_email} | Chatbot: ${conv.chatbot_name}`, margin + 3, y);
                            y += 4;
                            doc.text(`Channel: ${conv.channel} | Status: ${conv.status} | Created: ${conv.created_at}`, margin + 3, y);
                            y += 6;

                            // Messages
                            if (conv.messages && conv.messages.length > 0) {
                                doc.setFontSize(7);
                                conv.messages.forEach((msg, msgIndex) => {
                                    // Check if we need a new page
                                    if (y > 275) {
                                        doc.addPage();
                                        y = 20;
                                    }

                                    // Message header (role and time)
                                    doc.setFont(undefined, 'bold');
                                    const roleText = `[${msg.role.toUpperCase()}] ${msg.user_name} - ${msg.created_at}`;
                                    doc.text(roleText, margin + 5, y);
                                    y += 4;

                                    // Message content with word wrap
                                    doc.setFont(undefined, 'normal');
                                    const messageText = msg.message || 'No message content';
                                    const lines = doc.splitTextToSize(messageText, maxWidth - 10);

                                    lines.forEach(line => {
                                        if (y > 280) {
                                            doc.addPage();
                                            y = 20;
                                        }
                                        doc.text(line, margin + 7, y);
                                        y += 3.5;
                                    });

                                    y += 2; // Space between messages
                                });
                            } else {
                                doc.setFont(undefined, 'italic');
                                doc.text('No messages in this conversation', margin + 5, y);
                                y += 4;
                            }

                            y += 5; // Space between conversations

                            // Add separator line if not last conversation
                            if (convIndex < data.length - 1) {
                                if (y > 275) {
                                    doc.addPage();
                                    y = 20;
                                }
                                doc.setDrawColor(200, 200, 200);
                                doc.line(margin, y, pageWidth - margin, y);
                                y += 5;
                            }
                        });

                        doc.save(`conversations_${this.getTimestamp()}.pdf`);
                    },

                    downloadFile(blob, filename) {
                        const link = document.createElement('a');
                        const url = URL.createObjectURL(blob);
                        link.setAttribute('href', url);
                        link.setAttribute('download', filename);
                        link.style.visibility = 'hidden';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(url);
                    },

                    getTimestamp() {
                        const now = new Date();
                        return now.getFullYear() +
                            String(now.getMonth() + 1).padStart(2, '0') +
                            String(now.getDate()).padStart(2, '0') + '_' +
                            String(now.getHours()).padStart(2, '0') +
                            String(now.getMinutes()).padStart(2, '0') +
                            String(now.getSeconds()).padStart(2, '0');
                    }
                }));
            });
        })();
    </script>
@endpush

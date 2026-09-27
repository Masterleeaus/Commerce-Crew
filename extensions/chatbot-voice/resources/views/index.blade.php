@php
    $user_avatar = Auth::user()->avatar;

    if (!Auth::user()->github_token && !Auth::user()->google_token && !Auth::user()->facebook_token) {
        $user_avatar = '/' . $user_avatar;
    }
@endphp

@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Voice Chatbots'))
@section('titlebar_subtitle')
    {{ __('Build AI voice chatbots that speak and respond naturally—just like a real person.') }}
@endsection
@section('titlebar_actions')
    <x-button
        href="#"
        variant="ghost-shadow"
        @click.prevent="$store.externalChatbotHistory.setOpen(true)"
        x-data="{}"
    >
        @lang('Chat History')
    </x-button>

    <x-button
        href="#"
        @click.prevent="$store.externalChatbotEditor.setActiveChatbot('new_chatbot', 1, true);"
        x-data="{}"
    >
        <x-tabler-plus class="size-4" />
        @lang('Add New Voice Chatbot')
    </x-button>
@endsection

@section('content')
    <div class="py-10">
        <div
            class="lqd-external-chatbot-edit"
            x-data="externalChatbotEditor"
            @keydown.escape.window="setActiveChatbot(null)"
        >
            @include('chatbot-voice::home.actions-grid')

            @include('chatbot-voice::home.chatbots-list', ['chatbots' => $chatbots])

            @include('chatbot-voice::home.edit-window.edit-window', ['avatars' => $avatars])
        </div>

        @include('chatbot-voice::home.chats-history.chats-history')
    </div>
@endsection

@push('script')
    <link
        rel="stylesheet"
        href="{{ custom_theme_url('/assets/libs/prism/prism.css') }}"
    />
    <script src="{{ custom_theme_url('/assets/libs/prism/prism.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/beautify-html.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/markdownit/markdown-it.min.js') }}"></script>
    <script src="{{ custom_theme_url('/assets/libs/turndown.js') }}"></script>

    <script>
        (() => {
            document.addEventListener('alpine:init', () => {
                Alpine.data('externalChatbotEditor', () => ({
                    chatbots: @json($chatbots),
                    activeChatbot: {},
                    prevActiveChatbotId: null,
                    editingStep: 1,
                    submittingData: false,
                    // used for the chatbot ui
                    externalChatbot: null,
                    // used for the training tab
                    externalChatbotTraining: null,
                    defaultFormInputs: {
                        id: '',
                        title: '{{ $setting->site_name . __('Bots') }}',
                        bubble_message: '{{ __('Hey there, How can I help you?') }}',
                        welcome_message: '{{ __('Hi, how can I help you?') }}',
                        connect_message: '{{ __('I’ve forwarded your request to a human agent. An agent will connect with you as soon as possible.') }}',
                        instructions: '',
                        language: '',
                        avatar: (@json($avatars->isEmpty() ? [] : $avatars->random()))?.avatar || '{{ $user_avatar }}',
                        position: 'right',
                        active: true,
                    },
                    formErrors: {},
                    init() {
                        this.createNewChatObj();
                        this.initFormErrors();

                        Alpine.store('externalChatbotEditor', this);
                    },
                    createNewChatObj() {
                        this.chatbots.data.unshift({
                            ...this.defaultFormInputs,
                            id: 'new_chatbot',
                        })
                    },
                    initFormErrors() {
                        Object.keys(this.defaultFormInputs).forEach(key => {
                            this.formErrors[key] = [];
                        });
                    },
                    setActiveChatbot(chatbotId, step, skipCRUD = false) {
                        const topNoticeBar = document.querySelector('.top-notice-bar');
                        const navbar = document.querySelector('.lqd-navbar');
                        const pageContentWrap = document.querySelector('.lqd-page-content-wrap');
                        const navbarExpander = document.querySelector('.lqd-navbar-expander');

                        const activeChatbotId = this.activeChatbot.id;

                        this.activeChatbot = this.chatbots.data.find(c => c.id === chatbotId) || {
                            id: chatbotId
                        };

                        if (activeChatbotId) {
                            this.prevActiveChatbotId = activeChatbotId;
                        }

                        if (step) {
                            this.setEditingStep(step, skipCRUD);
                        }

                        this.formErrors = {};

                        document.documentElement.style.overflow = this.activeChatbot.id ? 'hidden' :
                            '';

                        if (window.innerWidth >= 992) {

                            if (navbar) {
                                navbar.style.position = this.activeChatbot.id ? 'fixed' : '';
                            }

                            if (pageContentWrap && navbar?.offsetWidth > 0) {
                                pageContentWrap.style.paddingInlineStart = this.activeChatbot.id ? 'var(--navbar-width)' : '';
                            }

                            if (topNoticeBar) {
                                topNoticeBar.style.visibility = this.activeChatbot.id ? 'hidden' :
                                    '';
                            }

                            if (navbarExpander) {
                                navbarExpander.style.visibility = this.activeChatbot.id ? 'hidden' :
                                    '';
                                navbarExpander.style.opacity = this.activeChatbot.id ? 0 : 1;
                            }
                        }
                    },
                    async setEditingStep(step, skipCRUD = false) {
                        const prevStep = this.editingStep;
                        let editingStep = step;

                        if (step === '>') {
                            editingStep = Math.min(4, this.editingStep + 1);
                        } else if (step === '<') {
                            editingStep = Math.max(1, this.editingStep - 1);
                        }

                        if (
                            !skipCRUD &&
                            prevStep !== editingStep &&
                            prevStep === 1 &&
                            this.activeChatbot.id === 'new_chatbot'
                        ) {
                            await this.createNewChatbot();
                            return;
                        }

                        if (
                            !skipCRUD &&
                            prevStep !== editingStep &&
                            (prevStep === 2 || (prevStep === 1 && editingStep === 2)) &&
                            this.activeChatbot.id !== 'new_chatbot'
                        ) {
                            await this.updateChatbot();
                        }

                        if (
                            !skipCRUD &&
                            this.externalChatbotTraining != null &&
                            editingStep === 3 &&
                            this.activeChatbot.id !== 'new_chatbot'
                        ) {
                            this.externalChatbotTraining.fetchEmbeddings();
                        }

                        this.prevEditingStep = this.editingStep;
                        this.editingStep = editingStep;
                    },
                    async toggleChatbotActivation(chatbotId) {
                        const chatbot = this.chatbots.data.find(c => c.id === chatbotId);

                        if (!chatbot) return;


                        await this.updateChatbot(chatbot);
                    },
                    async deleteChatbot(event) {
                        @if ($app_is_demo)
                            toastr.error(
                                '{{ trans('This feature is disabled in Demo version.') }}');
                            return;
                        @endif

                        if (!confirm(
                                '{{ __('Are you sure you want to delete this chatbot?') }}')) {
                            return;
                        }

                        const form = event.target;
                        const id = form.elements['id'].value;
                        const chatbotIndex = this.chatbots.data.findIndex(c => c.id == id);

                        this.submittinData = true;

                        const res = await fetch(form.action, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: this.getFormData(this.chatbots.data.at(chatbotIndex))
                        });

                        if (!res.ok) {
                            toastr.error('{{ __('Failed to delete chatbot') }}');
                            return;
                        }

                        const data = await res.json();

                        if (data.type !== 'success') {
                            toastr.error(data.message);
                            return;
                        }

                        if (chatbotIndex !== -1) {
                            this.chatbots.data.splice(chatbotIndex, 1);
                        }

                        this.submittingData = false;

                        toastr.clear();
                        toastr.success(data.message ||
                            '{{ __('Chatbot deleted successfully') }}');
                    },
                    training: {
                        activeTab: 'url',
                        setActiveTab(tab) {
                            if (this.activeTab === tab) return;
                            this.activeTab = tab;
                        }
                    },
                    async createNewChatbot() {
                        @if ($app_is_demo)
                            toastr.error(
                                '{{ trans('This feature is disabled in Demo version.') }}');
                            return;
                        @endif

                        this.submittingData = true;
                        this.formErrors = {};

                        const res = await fetch(
                            '{{ route('dashboard.chatbot-voice.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: this.getFormData()
                            });
                        const data = await res.json();
                        const {
                            data: chatbotData
                        } = data;

                        this.submittingData = false;

                        if (!res.ok || !chatbotData) {
                            if (data.errors) {
                                this.formErrors = data.errors;
                            } else if (data.message) {
                                toastr.error(data.message);
                            }

                            this.setEditingStep(1, true);
                            return;
                        }

                        this.chatbots.data.shift();

                        this.chatbots.data.unshift({
                            ...this.defaultFormInputs,
                            ...chatbotData
                        });

                        this.setActiveChatbot(chatbotData.id);
                        this.setEditingStep(2, true);
                        this.createNewChatObj();

                        toastr.clear();
                        toastr.success('{{ __('Chatbot created successfully') }}');
                    },
                    async updateChatbot(chatbot) {
                        this.submittingData = true;
                        this.formErrors = {};

                        const res = await fetch(
                            '{{ route('dashboard.chatbot-voice.update') }}', {
                                method: 'PUT',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: this.getFormData(chatbot)
                            });
                        const data = await res.json();
                        const {
                            data: chatbotData
                        } = data;

                        this.submittingData = false;

                        if (!res.ok || !chatbotData) {
                            if (data.errors) {
                                this.formErrors = data.errors;
                            } else if (data.message) {
                                toastr.error(data.message);
                            }

                            return;
                        }

                        const chatbotIndex = this.chatbots.data.findIndex(c => c.id ===
                            chatbotData.id);

                        if (chatbotIndex !== -1) {
                            this.chatbots.data[chatbotIndex] = {
                                ...this.chatbots.data[chatbotIndex],
                                ...chatbotData
                            };
                        }

                        toastr.clear();
                        toastr.success('{{ __('Chatbot updated successfully') }}');
                    },
                    getFormData(chatbot) {
                        const chatbotData = chatbot || this.activeChatbot;
                        const formData = {};

                        Object.keys(chatbotData).forEach(key => {
                            formData[key] = chatbotData[key];
                        });

                        return JSON.stringify(formData);
                    }
                }));
            });
        })();
    </script>
@endpush

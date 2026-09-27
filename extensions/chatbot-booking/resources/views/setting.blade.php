@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Chatbot Booking Settings'))
@section('titlebar_subtitle', __('This API key is used for these features: Human Agent for External Chatbot'))
@section('additional_css')
@endsection

@section('settings')
    <form
        method="post"
        enctype="multipart/form-data"
        action="{{ route('dashboard.admin.settings.chatbot.booking.update') }}"
    >
        @csrf
        <x-card
            class="mb-2 max-md:text-center"
            szie="lg"
        >

            <div
                class="form-control mb-3 border-none p-0 [&_.select2-selection--multiple]:!rounded-[--tblr-border-radius] [&_.select2-selection--multiple]:!border-[--tblr-border-color] [&_.select2-selection--multiple]:!p-[1em_1.23em]">
                <label class="form-label">{{ 'Calendly Access Token' }}
                </label>
                <input
                    class="form-control"
                    id="calendly_access_token"
                    type="text"
                    name="calendly_access_token"
                    value="{{ $app_is_demo ? '*********************' : setting('calendly_access_token') }}"
                    required
                >
                <x-alert
                    class="mt-2"
                    variant="lg"
                >
                    <p>
                        {{ __('Please ensure that your Calendly access token is fully functional and billing defined on your Calendly account.') }}

                        <x-button
                            variant="link"
                            href="https://calendly.com/integrations/api_webhooks/?via=magicai"
                            target="_blank"
                        >
                            {{ __('Get an Acces Token') }}
                        </x-button>
                    </p>
                </x-alert>
            </div>

        </x-card>
        <button class="btn btn-primary w-full">
            {{ __('Save') }}
        </button>
    </form>
@endsection
@push('script')
@endpush

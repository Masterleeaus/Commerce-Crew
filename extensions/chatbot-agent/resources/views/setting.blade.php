@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Ably Settings'))
@section('titlebar_subtitle', __('This API key is used for these features: Human Agent for External Chatbot'))
@section('additional_css')
@endsection

@section('settings')
    <form
        method="post"
        enctype="multipart/form-data"
        action="{{ route('dashboard.admin.settings.ably.update') }}"
    >
        @csrf
        <x-card class="mb-2 max-md:text-center">

            <div
                class="form-control mb-3 border-none p-0 [&_.select2-selection--multiple]:!rounded-[--tblr-border-radius] [&_.select2-selection--multiple]:!border-[--tblr-border-color] [&_.select2-selection--multiple]:!p-[1em_1.23em]">
                <label class="form-label">{{ 'API Private key' }}
                </label>
                <input
                    class="form-control"
                    id="ably_private_key"
                    type="text"
                    name="ably_private_key"
                    value="{{ $app_is_demo ? '*********************' : setting('ably_private_key') }}"
                    required
                >
                <label class="form-label mt-3">{{ 'API Public key' }}
                </label>
                <input
                    class="form-control"
                    id="ably_public_key"
                    type="text"
                    name="ably_public_key"
                    value="{{ $app_is_demo ? '*********************' : setting('ably_public_key') }}"
                    required
                >
                <x-alert
                    class="mt-2"
                    variant="lg"
                >
                    <p>
                        {{ __('Please ensure that your Ably api key is fully functional and billing defined on your Ably account.') }}

                        <x-button
                            variant="link"
                            href="https://www.ably.io/?via=magicai"
                            target="_blank"
                        >
                            {{ __('Get an API key') }}
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

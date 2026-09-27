@extends('panel.layout.settings')
@section('title', __('Instagram Chatbot Settings'))

@section('settings')
    <x-card>
        <form
            class="space-y-4"
            method="post"
            action="{{ route('dashboard.admin.chatbot-instagram.settings.update') }}"
        >
            @csrf

            <h4 class="text-xl font-semibold">
                {{ __('Meta Application Credentials') }}
            </h4>

            @foreach ($credentials as $key => $label)
                <div class="space-y-2">
                    <x-forms.input
                        id="{{ $key }}"
                        label="{{ __($label) }}"
                        type="text"
                        size="lg"
                        name="{{ $key }}"
                        value="{{ $appIsDemo ? '*********************' : old($key, $values[$key]) }}"
                        required
                    />
                    @error($key)
                        <small class="text-red-500">{{ $message }}</small>
                    @enderror
                </div>
            @endforeach

            <x-forms.input
                class="bg-foreground/5"
                label="{{ __('Webhook URL') }}"
                type="text"
                size="lg"
                value="{{ $webhookUrl }}"
                disabled
            />
            <p class="text-xs text-muted-foreground">
                {{ __('Use this URL as the Callback URL in your Facebook App Dashboard webhook configuration.') }}
            </p>

            <x-forms.input
                class="bg-foreground/5"
                label="{{ __('Redirect URL') }}"
                type="text"
                size="lg"
                value="{{ $redirectUrl }}"
                disabled
            />

            <p class="text-sm text-muted-foreground">
                {{ __('Use these credentials to complete the Facebook Login flow and verify the Instagram webhook subscription.') }}
            </p>

            <x-button
                class="w-full"
                size="lg"
                type="submit"
            >
                {{ __('Save Settings') }}
            </x-button>
        </form>
    </x-card>
@endsection

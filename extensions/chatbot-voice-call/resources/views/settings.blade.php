@extends('panel.layout.settings', ['layout' => 'wide'])
@section('title', __('Voice Call Settings'))
@section('titlebar_subtitle', __('Configure global voice call settings for external chatbots.'))

@section('settings')
    <form
        method="post"
        enctype="multipart/form-data"
        action="{{ route('dashboard.admin.settings.voice-call.update') }}"
    >
        @csrf
        <x-card class="mb-2 max-md:text-center">
            <div class="flex flex-col gap-5">
                <div
                    class="form-control border-none p-0"
                    x-data="{ provider: '{{ setting('voice_call_provider', '') }}' }"
                >
                    <label class="form-label">{{ __('Voice Call Provider') }}</label>
                    <select
                        class="form-control"
                        name="voice_call_provider"
                        x-model="provider"
                    >
                        <option value="">@lang('Select Provider')</option>
                        <option value="openai_realtime">@lang('OpenAI Realtime')</option>
                        <option value="elevenlabs">@lang('ElevenLabs')</option>
                    </select>

                    <div
                        class="mt-3"
                        x-show="provider === 'elevenlabs'"
                        x-cloak
                        x-transition
                    >
                        <label class="form-label">{{ __('Voice ID') }}</label>
                        <input
                            class="form-control"
                            type="text"
                            name="voice_call_voice_id"
                            placeholder="{{ __('Enter ElevenLabs Voice ID') }}"
                            value="{{ setting('voice_call_voice_id') }}"
                        >
                    </div>
                </div>
            </div>
        </x-card>
        <button class="btn btn-primary w-full">
            {{ __('Save') }}
        </button>
    </form>
@endsection

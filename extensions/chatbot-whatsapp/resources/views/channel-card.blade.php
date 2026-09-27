@php
	$image = 'vendor/chatbot-multi-channel/icons/whatsapp.svg';
	$image_dark_version = 'vendor/chatbot-multi-channel/icons/whatsapp-light.svg';
	$darkImageExists = file_exists(public_path($image_dark_version));
@endphp

<x-modal
	class:modal-head="border-b-0"
	class:modal-body="pt-3"
	class:modal-container="max-w-[600px]"
>
	<x-slot:trigger
		class="rounded-sm lqd-social-media-card flex flex-col  text-heading-foreground transition-all hover:scale-105 hover:border-heading-foreground/10 hover:shadow-lg hover:shadow-black/5"
		variant="outline"
		size="lg"
		type="button"
	>
		<figure class="mb-8 w-9 transition-all group-hover/card:scale-125">
			<img
				@class([
					'w-full h-auto',
					'dark:hidden' => $darkImageExists,
				])
				src="{{ asset($image) }}"
				alt="whatsapp"
			/>
			@if ($darkImageExists)
				<img
					class="hidden h-auto w-full dark:block"
					src="{{ asset($image_dark_version) }}"
					alt="whatsapp"
				/>
			@endif
		</figure>
		<h4 class="mb-2 text-lg text-inherit">
			Whatsapp
		</h4>

	</x-slot:trigger>

	<x-slot:modal>
		<h3 class="mb-3.5">
			Whatsapp
		</h3>
		<p class="mb-7 text-heading-foreground/60">
			@lang('You can add a new channel by including the Twilio WhatsApp integration details.')
		</p>

		<form id="storeForm-whatsapp" class="" action="{{ route('dashboard.chatbot-multi-channel.whatsapp.store') }}">
			<input hidden name="channel" value="whatsapp">
			<input hidden name="user_id" value="{{ \Illuminate\Support\Facades\Auth::id() }}">
			<div
				class="mb-3">
				<x-forms.input
					:label="__('Whatsapp sid')"
					name="credentials[whatsapp_sid]"
					size="lg"
					required
				>
				</x-forms.input>
			</div>
			<div
				class="mb-3">
				<x-forms.input
					:label="__('Whatsapp token')"
					name="credentials[whatsapp_token]"
					size="lg"
					required
				>
				</x-forms.input>
			</div>
			<div
				class="mb-3">
				<x-forms.input
					:label="__('Whatsapp phone')"
					name="credentials[whatsapp_phone]"
					size="lg"
					required
				>
				</x-forms.input>
			</div>
			<div
				class="mb-3">
				<x-forms.input
					:label="__('Whatsapp sandbox phone')"
					name="credentials[whatsapp_sandbox_phone]"
					size="lg"
					required
				>
				</x-forms.input>
			</div>
			<div class="mb-3">
				<x-forms.input
					id="whatsapp_environment"
					type="select"
					size="lg"
					name="credentials[whatsapp_environment]"
					label="{{ __('Environment') }}"
				>
					<option value="sandbox">@lang('SANDBOX')</option>
					<option value="production">@lang('PRODUCTION')</option>
				</x-forms.input>
			</div>
			@if ($app_is_demo)
				<x-button
					type="button"
					onclick="return toastr.info('This feature is disabled in Demo version.');"
				>
					{{ __('Add Channel') }}
				</x-button>
			@else
				<x-button
					type="button"
					x-on:click="storeChannel('storeForm-whatsapp')"
					form="storeForm-whatsapp"
					size="lg"
				>
					<span x-show="storeChannelFetch">
						Loading...
					</span>
					<span x-show="!storeChannelFetch">
						{{ __('Add Channel') }}
					</span>
				</x-button>
			@endif
		</form>
	</x-slot:modal>
</x-modal>

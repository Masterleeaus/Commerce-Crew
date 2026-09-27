@php
	$image = 'vendor/messenger-channel/icons/facebook-messenger.svg';
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
				alt="messenger"
			/>
			@if ($darkImageExists)
				<img
					class="hidden h-auto w-full dark:block"
					src="{{ asset($image_dark_version) }}"
					alt="messenger"
				/>
			@endif
		</figure>
		<h4 class="mb-2 text-lg text-inherit">
			Messenger
		</h4>

	</x-slot:trigger>

	<x-slot:modal>
		<h3 class="mb-3.5">
			Messenger
		</h3>
		<p class="mb-7 text-heading-foreground/60">
			@lang('You can add a new channel by including the Facebook messenger integration details.')
		</p>

		<form id="storeForm-messenger" class="" action="{{ route('dashboard.chatbot-multi-channel.messenger.store') }}">
			<input hidden name="channel" value="messenger">
			<input hidden name="user_id" value="{{ \Illuminate\Support\Facades\Auth::id() }}">

			<div
				class="mb-3">
				<x-forms.input
					:label="__('App ID')"
					name="credentials[app_id]"
					size="lg"
					required
				>
				</x-forms.input>
			</div>

			<div
				class="mb-3">
				<x-forms.input
					:label="__('App Secret')"
					name="credentials[app_secret]"
					size="lg"
					required
				>
				</x-forms.input>
			</div>

			<div
				class="mb-3">
				<x-forms.input
					:label="__('Page Name')"
					name="credentials[page_name]"
					size="lg"
					required
				>
				</x-forms.input>
			</div>
			<div
				class="mb-3">
				<x-forms.input
					:label="__('Access Token')"
					name="credentials[access_token]"
					size="lg"
					required
				>
				</x-forms.input>
			</div>
			<div
				class="mb-3">
				<x-forms.input
					:label="__('Verify token')"
					name="credentials[verify_token]"
					size="lg"
					required
				>
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
					x-on:click="storeChannel('storeForm-messenger')"
					form="storeForm-messenger"
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

@php
	$image = 'vendor/instagram-channel/icons/instagram.svg';
	$image_dark_version = 'vendor/instagram-channel/icons/instagram-light.svg';
	$darkImageExists = file_exists(public_path($image_dark_version));
@endphp

<x-modal
	class:modal-head="border-b-0"
	class:modal-body="pt-3"
	class:modal-container="max-w-[600px]"
>
	<x-slot:trigger
		class="rounded-sm lqd-social-media-card flex flex-col text-heading-foreground transition-all hover:scale-105 hover:border-heading-foreground/10 hover:shadow-lg hover:shadow-black/5"
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
				alt="instagram"
			/>
			@if ($darkImageExists)
				<img
					class="hidden h-auto w-full dark:block"
					src="{{ asset($image_dark_version) }}"
					alt="instagram"
				/>
			@endif
		</figure>
		<h4 class="mb-2 text-lg text-inherit">
			Instagram
		</h4>
	</x-slot:trigger>

	<x-slot:modal>
		<h3 class="mb-3.5">
			Instagram
		</h3>
		<p class="mb-5 text-heading-foreground/60">
			@lang('Sign in with your Meta app to receive messages via Instagram Direct. Channel details will be added automatically once OAuth is completed.')
		</p>

		<form id="storeForm-instagram" action="{{ route('dashboard.chatbot-multi-channel.instagram.store') }}">
			<input type="hidden" name="channel" value="instagram">
			<input type="hidden" name="user_id" value="{{ \Illuminate\Support\Facades\Auth::id() }}">

			<input type="hidden" name="credentials[page_id]" x-bind:value="instagramCredentials.page_id ?? ''">
			<input type="hidden" name="credentials[page_name]" x-bind:value="instagramCredentials.page_name ?? ''">
			<input type="hidden" name="credentials[instagram_id]" x-bind:value="instagramCredentials.instagram_id ?? ''">
			<input type="hidden" name="credentials[username]" x-bind:value="instagramCredentials.username ?? ''">
			<input type="hidden" name="credentials[name]" x-bind:value="instagramCredentials.name ?? ''">
			<input type="hidden" name="credentials[picture]" x-bind:value="instagramCredentials.picture ?? ''">
			<input type="hidden" name="credentials[access_token]" x-bind:value="instagramCredentials.access_token ?? ''">
			<input type="hidden" name="credentials[verify_token]" value="{{ setting('INSTAGRAM_VERIFY_TOKEN', 'chatbot-instagram') }}">
			<input type="hidden" name="credentials[app_id]" value="{{ setting('INSTAGRAM_APP_ID') }}">
			<input type="hidden" name="credentials[app_secret]" value="{{ setting('INSTAGRAM_APP_SECRET') }}">

			<div class="space-y-3">
				<div class="rounded-lg border border-white/10 bg-white/5 p-3 text-sm text-heading-foreground/70">
					<strong>@lang('Status'):</strong>
					<span x-text="instagramStatus ?? '{{ __('Connection pending.') }}'"></span>
				</div>

				@if ($app_is_demo)
					<x-button
						type="button"
						onclick="return toastr.info('This feature is disabled in demo mode.');"
					>
						{{ __('Connect with Instagram') }}
					</x-button>
				@elseif (! setting('INSTAGRAM_APP_ID') || ! setting('INSTAGRAM_APP_SECRET'))
					<div class="rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-700">
						<div class="mb-1 flex items-center gap-1.5 font-medium">
							<x-tabler-alert-triangle class="size-4" />
							{{ __('Configuration Required') }}
						</div>
						<p class="text-xs text-yellow-600">
							{{ __('Instagram App ID and App Secret must be configured before connecting.') }}
						</p>
						@if (auth()->user()?->isAdmin())
							<a
								class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-yellow-800 underline hover:text-yellow-900"
								href="{{ route('dashboard.admin.chatbot-instagram.settings.index') }}"
							>
								<x-tabler-settings class="size-3.5" />
								{{ __('Go to Instagram Settings') }}
							</a>
						@else
							<p class="mt-1 text-xs text-yellow-600">
								{{ __('Please ask your administrator to set them up.') }}
							</p>
						@endif
					</div>
				@else
					<x-button
						type="button"
						x-on:click="openInstagramOauth()"
						size="lg"
					>
						<span x-show="!instagramPopupOpen">
							{{ __('Connect with Instagram') }}
						</span>
						<span x-show="instagramPopupOpen">
							{{ __('The Instagram window is open...') }}
						</span>
					</x-button>
				@endif
			</div>
		</form>
	</x-slot:modal>
</x-modal>

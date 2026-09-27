<div>
	<x-forms.input
		class="h-[18px] w-[34px] [background-size:0.625rem]"
		class:label="text-heading-foreground flex-row-reverse justify-between"
		label="{{ __('Shopping Assistant') }}"
		name="is_shop"
		size="lg"
		type="checkbox"
		switcher
		x-model.boolean="activeChatbot.is_shop"
		@change="console.log(activeChatbot)"
	/>

	<template
		x-for="(error, index) in formErrors.is_shop"
		:key="'error-' + index"
	>
		<div class="mt-2 text-2xs/5 font-medium text-red-500">
			<p x-text="error"></p>
		</div>
	</template>
</div>

<div
	x-show="activeChatbot.is_shop"
	x-transition
>
	<x-forms.input
		class:label="text-heading-foreground"
		label="{{ __('Shop Source') }}"
		name="shop_source"
		size="lg"
		type="select"
		x-model="activeChatbot.shop_source"
	>
		<option
			value="shopify"
			selected
		>{{ __('Shopify') }}</option>
		<option value="woocommerce">{{ __('WooCommerce') }}</option>
	</x-forms.input>
	<x-alert
		class="rounde mt-4"
		x-show="activeChatbot.is_shop && activeChatbot.shop_source === 'woocommerce'"
	>
		<a
			href="https://api.liquid-themes.com/magicai-wordpress/magicai-woocommerce-extension.zip"
			target="_blank"
		>
			{{ __('An addon is required for WooCommerce. Please install the addon and activate it on your site.') }}
			<x-tabler-download class="inline size-4 align-text-bottom" />
		</a>
	</x-alert>
</div>

<div
	x-show="activeChatbot.is_shop && activeChatbot.shop_source === 'shopify'"
	x-transition
	x-init="activeChatbot"
>
	<x-forms.input
		class:label="text-heading-foreground"
		label="{{ __('Shopify Domain') }}"
		placeholder="example.myshopify.com"
		name="shopify_domain"
		size="lg"
		x-model="activeChatbot.shopify_domain"
	/>
</div>

<div
	x-show="activeChatbot.is_shop && activeChatbot.shop_source === 'shopify'"
	x-transition
>
	<x-forms.input
		class:label="text-heading-foreground"
		label="{{ __('Shopify Access Token') }}"
		placeholder="{{ __('Enter access token') }}"
		name="shopify_access_token"
		size="lg"
		type="password"
		autocomplete="new-password"
	/>
</div>

<div
	x-show="activeChatbot.is_shop && activeChatbot.shop_source === 'woocommerce'"
	x-transition
	x-init="activeChatbot"
>
	<x-forms.input
		class:label="text-heading-foreground"
		label="{{ __('WooCommerce Domain') }}"
		placeholder="http://example.com"
		name="woocommerce_domain"
		size="lg"
		x-model="activeChatbot.woocommerce_domain"
	/>
</div>

<div
	x-show="activeChatbot.is_shop && activeChatbot.shop_source === 'woocommerce'"
	x-transition
>
	<x-forms.input
		class:label="text-heading-foreground"
		label="{{ __('WooCommerce Consumer Key') }}"
		placeholder="{{ __('Enter consumer key') }}"
		name="woocommerce_consumer_key"
		size="lg"
		type="password"
		autocomplete="new-password"
	/>
</div>

<div
	x-show="activeChatbot.is_shop && activeChatbot.shop_source === 'woocommerce'"
	x-transition
>
	<x-forms.input
		class:label="text-heading-foreground"
		label="{{ __('WooCommerce Consumer Secret') }}"
		placeholder="{{ __('Enter consumer secret') }}"
		name="woocommerce_consumer_secret"
		size="lg"
		type="password"
		autocomplete="new-password"
	/>
</div>

<div
	class="flex flex-wrap items-center justify-between gap-2"
	x-cloak
	x-show="activeChatbot.is_shop && activeChatbot.shop_source === 'woocommerce'"
>
	<p class="m-0 text-2xs font-medium text-heading-foreground">
		{{ __('Shop Features') }}
	</p>
	<x-modal
		class:modal-head="border-b-0"
		class:modal-body="pt-0"
		class:modal-content="max-w-[600px]"
		class:modal-container="max-w-[600px]"
	>
		<x-slot:trigger
			variant="ghost-shadow"
			type="button"
		>
			{{ __('Edit') }}
		</x-slot:trigger>

		<x-slot:modal>
			<h3 class="mb-3.5">
				{{ __('Shop Features') }}
			</h3>
			<p class="mb-9 text-balance text-base font-medium opacity-50">
				{{ __('Select which features you want the API to provide. In some cases, using “train” may be more effective.') }}
			</p>

			<div class="mb-8 space-y-3">
				@foreach ($shop_features as $shop_feature)
					<x-forms.input
						class:label="text-heading-foreground border rounded-xl px-2.5 py-3"
						data-condition="{{ $shop_feature }}"
						label="{{ $shop_features_label[$shop_feature] }}"
						type="checkbox"
						custom
						::checked="activeChatbot.shop_features?.includes($el.getAttribute('data-condition'))"
						@change="onShopFeaturesChange"
					/>
				@endforeach
			</div>

			<x-button
				class="w-full"
				variant="secondary"
				@click.prevent="modalOpen = false"
			>
				{{ __('Save Features') }}
			</x-button>
		</x-slot:modal>
	</x-modal>

	<select
		class="hidden"
		id="shop_features"
		name="shop_features"
		multiple
		x-model="activeChatbot.shop_features"
	>
		@foreach ($shop_features as $shop_feature)
			<option value="{{ $shop_feature }}">
				{{ $shop_feature }}
			</option>
		@endforeach
	</select>
</div>
<div class="col-12 col-sm-6 space-y-5">
	<x-form.group
		class:container="mb-4"
		no-group-label
		error="plan.chatbot_channels.instagram"
	>
		<x-form.checkbox
			class:container="w-full mt-4"
			wire:model="plan.chatbot_channels.instagram"
			label="{{ __('Enable Instagram Channel') }}"
			tooltip="{{ __('Allow subscribers on this plan to connect their chatbot to Instagram.') }}"
			switcher
		/>
	</x-form.group>
</div>

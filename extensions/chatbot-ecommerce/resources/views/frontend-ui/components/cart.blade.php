<div
    class="lqd-ext-chatbot-cart"
    @if (!$is_editor) x-init="productGetCart(true)" @endif
    x-data="{
        dropdownOpen: false
    }"
    @click.outside="dropdownOpen = false"
    @if ($is_editor) x-show="activeChatbot.is_shop" @endif
>
    <button
        class="relative inline-grid aspect-square min-w-10 place-items-center transition active:scale-95"
        type="button"
        @click.prevent="dropdownOpen = !dropdownOpen"
    >
        <svg
            width="25"
            height="20"
            viewBox="0 0 25 20"
            fill="currentColor"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                d="M5.17309 19.7979C4.73869 19.7979 4.34891 19.6763 4.00373 19.433C3.65854 19.1897 3.42705 18.8598 3.30924 18.4431L0.0446214 7.74481C-0.0484203 7.4385 0.00404149 7.15545 0.202007 6.89566C0.399972 6.63607 0.665101 6.50627 0.997392 6.50627H7.63839L11.3797 0.576376C11.5207 0.362501 11.6902 0.212968 11.8884 0.127781C12.0868 0.0425932 12.289 0 12.495 0C12.701 0 12.8982 0.0445064 13.0865 0.13352C13.2746 0.222534 13.4391 0.37398 13.5801 0.587855L17.2679 6.50627H23.8856C24.2179 6.50627 24.485 6.63607 24.6868 6.89566C24.8886 7.15545 24.9391 7.4385 24.8384 7.74481L21.5505 18.4431C21.4327 18.8598 21.2012 19.1897 20.856 19.433C20.5109 19.6763 20.1212 19.7979 19.687 19.7979H5.17309ZM5.13805 18.2271H19.745C19.8302 18.2271 19.9038 18.2019 19.9658 18.1516C20.0276 18.1012 20.0702 18.0335 20.0936 17.9483L23.0749 8.0771H1.80818L4.78944 17.9483C4.81281 18.0335 4.8554 18.1012 4.91723 18.1516C4.97925 18.2019 5.05286 18.2271 5.13805 18.2271ZM12.4479 14.7229C12.8774 14.7229 13.2456 14.5675 13.5523 14.2565C13.859 13.9454 14.0124 13.5751 14.0124 13.1458C14.0124 12.7162 13.8569 12.3481 13.5459 12.0413C13.2348 11.7346 12.8645 11.5813 12.4352 11.5813C12.0056 11.5813 11.6375 11.7367 11.3308 12.0477C11.024 12.3588 10.8707 12.7291 10.8707 13.1584C10.8707 13.588 11.0262 13.9561 11.3371 14.2629C11.6482 14.5696 12.0185 14.7229 12.4479 14.7229ZM9.47415 6.50627H15.4207L12.453 1.81008L9.47415 6.50627Z"
            />
        </svg>

        <div class="absolute end-0 top-0 inline-grid place-items-center">
            <span
                class="lqd-ext-chatbot-cart-count motion-preset-bounce col-start-1 col-end-1 row-start-1 row-end-1 inline-grid size-4 grid-cols-1 grid-rows-1 place-items-center rounded-full bg-red-500 text-[10px] text-white"
                x-show="!updatingCart ?? true"
                x-transition
                x-text="cart.products?.length ?? 0"
            >0</span>
            <span
                class="col-start-1 col-end-1 row-start-1 row-end-1 inline-grid size-4 place-items-center"
                x-show="updatingCart ?? false"
                x-transition
                x-cloak
            >
                <x-tabler-loader-2
                    class="size-4 animate-spin"
                    stroke-width="3"
                />
            </span>
        </div>

    </button>

    <div
        class="lqd-ext-chatbot-cart-content absolute end-0 start-0 top-full mt-2 max-h-[calc(100vh-220px)] min-w-64 origin-top overflow-y-auto rounded-xl bg-background p-4 shadow-[0_55px_55px_hsl(0_0%_0%/10%)] shadow-black/5 transition duration-100"
        x-cloak
        x-show="dropdownOpen"
        x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
    >
        <template x-if="cart.products?.length">
            <div>
                <div class="flex items-center justify-between gap-2 px-3 pb-5 pt-1">
                    <h3 class="text-sm font-semibold">
                        @lang('Cart')
                        <span
                            class="opacity-25"
                            x-text="cart.products?.length ?? 0"
                        >0</span>
                    </h3>

                    <button
                        class="inline-grid size-[34px] place-items-center rounded-full border border-black/5 transition hover:bg-black hover:text-white active:scale-95"
                        type="button"
                        title="{{ __('Collapse the cart') }}"
                        @click.prevent="dropdownOpen = false"
                    >
                        <x-tabler-x class="size-4" />
                    </button>
                </div>

                <template
                    x-for="productId in Object.keys(cart.product_data).filter(id => cart.products.includes(id))"
                    :key="productId"
                >
                    <div
                        class="lqd-ext-chatbot-cart-item mb-8 flex justify-between gap-4 px-3 last:mb-0"
                        :class="{ 'pointer-events-none animate-pulse': updatingCart ?? false }"
                        :data-id="productId"
                    >
                        <template x-if="cart.product_data[productId].productImage">
                            <figure class="m-0 w-20 shrink-0 p-0">
                                <img
                                    class="m-0 h-auto w-full rounded"
                                    :src="cart.product_data[productId].productImage"
                                    :alt="cart.product_data[productId].productTitle"
                                >
                            </figure>
                        </template>

                        <div class="lqd-ext-chatbot-cart-item-details-left grow">
                            <h5
                                class="mb-1 mt-0 text-sm font-semibold"
                                x-text="cart.product_data[productId].productTitle"
                            ></h5>
                            <template x-if="cart.product_data[productId].productTitle !== cart.product_data[productId].variantTitle">
                                <p
                                    class="mb-3.5 mt-0 text-[12px] opacity-50"
                                    x-text="cart.product_data[productId].variantTitle"
                                ></p>
                            </template>

                            <div class="inline-flex select-none items-center justify-between rounded-full border border-black/10">
                                <button
                                    class="inline-grid size-11 select-none place-items-center rounded-s-full transition hover:bg-black hover:text-white"
                                    type="button"
                                    @click.prevent="productUpdateQuantity(cart.product_data[productId].qty === 1 ? 'removeAll' : 'remove', productId)"
                                    aria-label="{{ __('Decrease quantity') }}"
                                >
                                    <x-tabler-minus
                                        class="size-3.5"
                                        x-show="cart.products.filter(id => id === productId).length > 1"
                                    />
                                    <x-tabler-trash
                                        class="size-4"
                                        x-show="cart.product_data[productId].qty === 1"
                                    />
                                </button>

                                <span class="inline-grid size-11 cursor-default place-items-center text-center text-[12px] font-medium">
                                    <span
                                        class="lqd-ext-chatbot-cart-item-qty"
                                        x-text="cart.products.filter(id => id === productId).length"
                                    ></span>
                                </span>

                                <button
                                    class="inline-grid size-11 select-none place-items-center rounded-e-full transition hover:bg-black hover:text-white"
                                    type="button"
                                    @click.prevent="productUpdateQuantity('add', productId)"
                                    aria-label="{{ __('Decrease quantity') }}"
                                >
                                    <x-tabler-plus class="size-3.5" />
                                </button>

                            </div>
                        </div>

                        <div class="lqd-ext-chatbot-cart-item-details-right shrink-0 text-end">
                            <p
                                class="lqd-ext-chatbot-cart-item-price mb-1 text-sm font-medium"
                                x-text="(Number(cart.product_data[productId].variantPrice) * cart.products.filter(id => id === productId).length).toLocaleString('en-US', { style: 'currency', currency: cart.product_data[productId].currencyCode ?? 'USD' })"
                            ></p>
                            <a
                                class="text-[12px] underline underline-offset-2 transition hover:text-red-500"
                                href="#"
                                @click.prevent="productUpdateQuantity('removeAll', productId)"
                            >
                                @lang('Remove')
                            </a>
                        </div>
                    </div>
                </template>

                {{-- <div>
                    <div class="px-3">
                        <div class="border-b border-black/5 py-5">

                        </div>
                    </div>
                </div> --}}

                <div class="px-3 pb-3.5 pt-2.5">
                    <button
                        class="w-full rounded-full bg-primary p-5 text-center text-sm font-semibold text-primary-foreground transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-black/10"
                        :class="{ 'animate-pulse pointer-events-none': updatingCart ?? false }"
                        @click.prevent="productCartCheckout()"
                    >
                        @lang('Checkout')
                    </button>
                </div>
            </div>
        </template>
        <template x-if="!cart.products?.length">
            <div class="p-5 text-center">
                <div class="mx-auto mb-3 inline-grid size-24 place-items-center rounded-full bg-black/5">
                    <x-tabler-shopping-cart-exclamation class="size-10" />
                </div>
                <h3 class="mb-0.5 text-[18px] font-medium">
                    @lang('Cart is empty')
                </h3>
                <p class="text-xs opacity-50">
                    @lang('Add products to get started')
                </p>
            </div>
        </template>
    </div>
</div>

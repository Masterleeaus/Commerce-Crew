<div
    class="lqd-ext-chatbot-html-response lqd-ext-chatbot-product-carousel-wrap relative -mx-5 -mb-10 overflow-hidden px-5"
    x-data="{
        activeIndex: 0,
        init() {
            this.prev = this.prev.bind(this);
            this.next = this.next.bind(this);
        },
        prev() {
            const carousel = this.$el.querySelector('.lqd-ext-chatbot-product-carousel');
            const item = carousel.querySelector('.lqd-ext-chatbot-product');
            if (item) {
                carousel.scrollBy({ left: -(item.offsetWidth + 8), behavior: 'smooth' });
            }
        },
        next() {
            const carousel = this.$el.querySelector('.lqd-ext-chatbot-product-carousel');
            const item = carousel.querySelector('.lqd-ext-chatbot-product');
            if (item) {
                carousel.scrollBy({ left: item.offsetWidth + 8, behavior: 'smooth' });
            }
        }
    }"
>
    <div class="lqd-ext-chatbot-product-carousel -mx-5 flex snap-both snap-mandatory gap-2 overflow-x-auto px-5">
        @foreach ($products as $product)
            @php
                $product_id = $product['id'];
                $first_variant = collect($product['variants'])->first();
                $first_variant_id = $first_variant ? $first_variant['id'] : $product_id;
            @endphp

            <div
                class="lqd-ext-chatbot-product group mb-12 flex w-[72%] shrink-0 grow-0 basis-auto snap-start scroll-mx-5 flex-col rounded-xl bg-background shadow-xl shadow-black/5">
                @if (!empty($product['images']))
                    <a
                        href="{{ $product['url'] }}"
                        target="_blank"
                    >
                        <img
                            class="mb-3.5 mt-0 aspect-square rounded-t-xl object-cover object-center"
                            src="{{ $product['images'][0] }}"
                            alt="{{ $product['title'] }}"
                        >
                    </a>
                @endif

                <div class="mb-3.5 flex flex-col px-6 first:pt-8">
                    <h5 class="card-title group/title mb-2 w-full text-sm font-semibold hover:underline hover:underline-offset-2">
                        <a
                            class="block w-full no-underline"
                            href="{{ $product['url'] }}"
                            target="_blank"
                        >
                            {{ $product['title'] }}

                            <x-tabler-arrow-up-right class="ms-0.5 inline size-[18px] align-text-top opacity-0 transition group-hover/title:opacity-100" />
                        </a>
                    </h5>

                    {{-- @php
                        $words = preg_split('/\s+/', trim($product['description']));
                        $needsTruncate = count($words) > 12;
                        $shortDesc = $needsTruncate ? implode(' ', array_slice($words, 0, 12)) . '...' : $product['description'];
                    @endphp
                    <div
                        class="mb-3 mt-0 text-xs opacity-65"
                        x-data="{ expanded: false }"
                    >
                        <p
                            class="mb-0 mt-0"
                            x-show="!expanded"
                        >{{ $shortDesc }}</p>
                        <p
                            class="mb-0 mt-0"
                            x-show="expanded"
                            x-cloak
                        >{{ $product['description'] }}</p>
                        @if ($needsTruncate)
                            <button
                                class="mt-1 text-xs font-medium text-primary underline hover:underline"
                                type="button"
                                @click="expanded = !expanded"
                                x-text="expanded ? '{{ __('Show less') }}' : '{{ __('Show more') }}'"
                            ></button>
                        @endif
                    </div> --}}

                    @if (!empty($product['options']))
                        @foreach ($product['options'] as $option)
                            @if ($shop_source === 'woocommerce' && empty($product['variants']))
                                @continue
                            @endif

                            <div class="mb-4 last:mb-0">
                                <p class="mb-2 mt-0 text-xs opacity-70">
                                    {{ $option['name'] }}
                                </p>
                                <div class="flex gap-3 overflow-x-auto">
                                    @foreach ($option['values'] as $value)
                                        @php
                                            // Does any variant contain this option value?
                                            $variant = collect($product['variants'])->first(function ($variant) use ($value) {
                                                return str_contains($variant['title'], $value);
                                            });
                                        @endphp
                                        @if ($variant)
                                            <button
                                                class="lqd-ext-chatbot-product-variant-button {{ $loop->first ? 'selected' : '' }} min-w-fit rounded-lg border border-black/5 px-3 py-1.5 text-2xs font-medium transition-colors duration-200 [&.selected]:border-transparent [&.selected]:bg-primary [&.selected]:text-primary-foreground"
                                                type="button"
                                                @if ($shop_source === 'shopify') data-name="{{ $value }}"
												@else
													data-name="{{ $option['name'] }}:{{ $value }}" @endif
                                                aria-label="Select {{ $option['name'] }}: {{ $value }}"
                                                @click.prevent="productSelectVariant('{{ $variant['id'] }}')"
                                            >
                                                {{ $value }}
                                            </button>
                                        @else
                                            <div
                                                class="lqd-ext-chatbot-product-variant-button min-w-fit rounded-lg border border-black/5 px-3 py-2 text-xs font-medium opacity-35 transition-colors duration-200">
                                                {{ $value }}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="mt-auto flex flex-wrap justify-center gap-5 border-t px-4 pb-5 pt-4">
                    <a
                        class="lqd-ext-chatbot-product-add-to-cart-button relative cursor-pointer font-medium text-primary no-underline after:absolute after:inset-x-0 after:-bottom-1 after:h-px after:bg-primary after:opacity-20 after:transition hover:after:opacity-100"
                        data-id="{{ $product_id }}"
                        :class="{ 'opacity-50 pointer-events-none': updatingCart ?? false }"
                        href="#"
                        @click.prevent="productAddToCart('{{ $product_id }}')"
                    >
                        {{ __('Add to Cart') }}
                    </a>
                </div>
            </div>
        @endforeach

        {{-- blank space to compensate for the scroll snap --}}
        <div class="lqd-ext-chatbot-product-fill-compensate h-px w-[30%] shrink-0 grow-0 basis-auto"></div>
    </div>

    <button
        class="lqd-ext-chatbot-product-carousel-btn lqd-ext-chatbot-product-carousel-btn-prev absolute start-1 top-28 inline-grid size-[34px] place-items-center rounded-full border border-black/5 bg-background shadow-md shadow-black/5 transition hover:border-primary hover:bg-primary hover:text-primary-foreground active:scale-95"
        type="button"
        @click.prevent="prev"
    >
        <x-tabler-chevron-left class="size-4 rtl:rotate-180" />
    </button>
    <button
        class="lqd-ext-chatbot-product-carousel-btn lqd-ext-chatbot-product-carousel-btn-next absolute end-[calc(30%-17px)] top-28 inline-grid size-[34px] place-items-center rounded-full border border-black/5 bg-background shadow-md shadow-black/5 transition hover:border-primary hover:bg-primary hover:text-primary-foreground active:scale-95"
        type="button"
        @click.prevent="next"
    >
        <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
    </button>
</div>

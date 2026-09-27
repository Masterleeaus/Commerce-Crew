{{-- Voice Call Active Header - replaces normal header content during active call --}}
<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 flex w-full items-center justify-between gap-2 p-2"
    x-show="$store.voiceCall.status === 'connecting' || $store.voiceCall.status === 'active'"
    x-cloak
    x-transition
>
    <div class="w-1/3">
        {{-- <button
            class="inline-grid size-10 place-items-center rounded-full transition active:scale-[0.85]"
            type="button"
            title="{{ __('Back') }}"
            @click.prevent="toggleView('<')"
        >
            <x-tabler-chevron-left class="size-5" />
        </button> --}}
    </div>

    <div class="flex w-1/3 items-center justify-center gap-3">
        <span
            class="text-xs font-semibold"
            x-show="$store.voiceCall.status === 'connecting'"
        >{{ __('Connecting...') }}</span>
        <span
            class="text-xs font-semibold"
            x-show="$store.voiceCall.status === 'active'"
        >{{ __('Listening...') }}</span>

        {{-- Waveform animation --}}
        <div
            class="flex items-center gap-0.5"
            x-show="$store.voiceCall.status === 'active'"
        >
            <template
                x-for="(barValue, barIndex) in $store.voiceCall.waveformData"
                :key="barIndex"
            >
                <span
                    class="inline-block w-[3px] rounded-full transition-[height,opacity] duration-100"
                    :style="{
                        height: Math.max(3, barValue * 16) + 'px',
                        opacity: $store.voiceCall.waveformSource === 'idle' ? 0.25 : Math.max(0.3, barValue),
                        backgroundColor: $store.voiceCall.waveformSource === 'user' ? '#4ade80' : 'currentColor',
                    }"
                ></span>
            </template>
        </div>
    </div>

    <div class="flex w-1/3 justify-end">
        {{-- End call button --}}
        <button
            class="inline-grid size-10 place-items-center rounded-full bg-red-500 text-white transition hover:bg-red-600 active:scale-[0.85] disabled:pointer-events-none disabled:opacity-50"
            type="button"
            title="{{ __('End Call') }}"
            @click.prevent="$store.voiceCall.end()"
            :disabled="$store.voiceCall.status === 'connecting'"
        >
            <svg
                class="size-4"
                width="19"
                height="16"
                viewBox="0 0 19 16"
                fill="currentColor"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M3.58876 0.210233C3.53388 0.148363 3.46726 0.0980024 3.39276 0.0620693C3.31827 0.0261362 3.23739 0.00534556 3.1548 0.000902525C3.07221 -0.00354051 2.98956 0.00845229 2.91165 0.0361859C2.83373 0.0639196 2.76209 0.106842 2.70088 0.162467C2.63967 0.218091 2.59012 0.28531 2.55508 0.360229C2.52004 0.435148 2.50022 0.516275 2.49677 0.59891C2.49332 0.681545 2.5063 0.764043 2.53497 0.841623C2.56363 0.919203 2.60741 0.990322 2.66376 1.05086L4.81923 3.42273C3.497 3.97811 2.2957 4.78588 1.28251 5.80086C-0.279986 7.36336 -0.429986 9.66492 0.916108 11.4001C1.08128 11.6114 1.31005 11.764 1.56863 11.8354C1.82721 11.9067 2.10188 11.893 2.35205 11.7962L6.18017 10.4391L6.20283 10.4305C6.39594 10.3533 6.56674 10.2292 6.69975 10.0693C6.83276 9.90941 6.92377 9.71886 6.96454 9.51492L7.42548 7.20945C7.64454 7.13394 7.86784 7.07133 8.09423 7.02195L15.1653 14.8009C15.2202 14.8627 15.2868 14.9131 15.3613 14.949C15.4358 14.985 15.5167 15.0057 15.5993 15.0102C15.6819 15.0146 15.7645 15.0026 15.8424 14.9749C15.9204 14.9472 15.992 14.9043 16.0532 14.8486C16.1144 14.793 16.164 14.7258 16.199 14.6509C16.234 14.5759 16.2539 14.4948 16.2573 14.4122C16.2608 14.3295 16.2478 14.2471 16.2191 14.1695C16.1905 14.0919 16.1467 14.0208 16.0903 13.9602L3.58876 0.210233ZM7.00283 6.03211C6.79927 6.10464 6.618 6.2287 6.47669 6.39219C6.33538 6.55568 6.23887 6.75301 6.19658 6.96492L5.73564 9.26961L1.9247 10.6212C1.91689 10.6212 1.91142 10.6313 1.90361 10.6345C0.940327 9.39227 1.04423 7.80476 2.16611 6.68445C3.17174 5.67849 4.38376 4.90281 5.71845 4.41101L7.14814 5.98445C7.0997 6.00008 7.05126 6.01492 7.00283 6.03211ZM17.8349 11.4001C17.6697 11.6114 17.4409 11.764 17.1823 11.8354C16.9238 11.9067 16.6491 11.893 16.3989 11.7962L15.6763 11.5399C15.5989 11.5125 15.5277 11.4701 15.4667 11.4151C15.4058 11.3601 15.3562 11.2937 15.3209 11.2196C15.2497 11.07 15.2408 10.8981 15.2962 10.7419C15.3236 10.6645 15.366 10.5933 15.421 10.5324C15.476 10.4714 15.5424 10.4218 15.6165 10.3866C15.7662 10.3153 15.938 10.3064 16.0942 10.3618L16.8263 10.6212L16.8497 10.6305C17.8106 9.39227 17.7091 7.80476 16.5849 6.68445C14.5802 4.67976 11.7505 3.61648 8.82314 3.76961C8.74106 3.77387 8.65895 3.76192 8.58149 3.73444C8.50403 3.70696 8.43275 3.6645 8.3717 3.60948C8.31065 3.55445 8.26104 3.48794 8.2257 3.41374C8.19036 3.33954 8.16997 3.25911 8.16572 3.17703C8.16146 3.09495 8.17341 3.01284 8.20088 2.93539C8.22836 2.85793 8.27082 2.78664 8.32585 2.72559C8.38087 2.66455 8.44739 2.61493 8.52159 2.57959C8.59578 2.54425 8.67622 2.52387 8.75829 2.51961C12.0395 2.34851 15.2138 3.54305 17.4685 5.80086C19.0302 7.36258 19.181 9.66492 17.8349 11.4001Z"
                />
            </svg>
        </button>
    </div>
</div>

{{-- Editing Step 4 - Embed --}}
<div
    class="col-start-1 col-end-1 row-start-1 row-end-1 transition-all"
    data-step="4"
    x-show="editingStep === 4"
    x-transition:enter-start="opacity-0 -translate-x-3"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-3"
>
    <h2 class="mb-3.5">
        @lang('Test and Embed')
    </h2>
    <p class="mb-14 text-xs/5 opacity-60 lg:max-w-[360px]">
        @lang('Your external AI chatbot has been successfully created! You can now integrate it into your website and start engaging with your audience.')
    </p>

    <div class="mb-14">
        <label class="mb-4 block text-xs font-semibold text-heading-foreground">
            @lang('Embed Code')
        </label>
        <div
            class="mb-5 rounded-xl border bg-heading-foreground/5 p-7 font-mono text-[12px] [word-break:break-word;]"
            id="embed-code-wrapper"
            disabled
        >
            &lt;script
            defer
            src="{{ url('vendor/chatbot-voice/js/external-chatbot-voice.js') }}"
            data-chatbot-uuid="<span x-text="activeChatbot.uuid"></span>"
            data-language="en"
            &gt;&lt;/script&gt;
        </div>

        <x-button
            class="w-full"
            size="lg"
            variant="secondary"
            @click.prevent="navigator.clipboard.writeText(document.getElementById('embed-code-wrapper').innerText); toastr.success('{{ __('Embed code copied to clipboard!') }}');"
        >
            @lang('Copy to Clipboard')
            <x-tabler-copy class="size-5" />
        </x-button>
    </div>

    <div>
        <label
            class="mb-4 block text-xs font-semibold text-heading-foreground underline decoration-heading-foreground/10 decoration-dashed underline-offset-4"
        >
            @lang('Need help?')
        </label>
        <p class="mb-3 text-xs/5 opacity-60 lg:max-w-[360px]">
            @lang('Paste this code just before the closing &lt;/body&gt; tag in your HTML file, then save the changes. Refresh your site to ensure your chatbot works correctly.')
        </p>
    </div>
</div>

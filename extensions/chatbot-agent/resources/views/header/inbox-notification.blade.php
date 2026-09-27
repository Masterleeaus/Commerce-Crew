<x-button
    class="relative flex size-6 items-center justify-center hover:bg-transparent max-lg:hidden max-lg:size-10 max-lg:rounded-full max-lg:border max-lg:dark:bg-white/[3%]"
    size="none"
    href="{{ route('dashboard.chatbot-agent.index') }}"
    title="{{ __('Human Agent') }}"
    variant="link"
    x-data="{}"
>
    <span
        class="absolute -end-1.5 -top-1.5 hidden h-4 min-w-4 place-items-center rounded-full bg-red-500 px-1 text-3xs font-medium leading-none text-white [&.active]:inline-grid"
        id="inbox-notification-chatbot-agent"
    >0</span>
    <x-tabler-inbox
        class="size-5 dark:block"
        stroke-width="1.5"
    />
</x-button>

@push('script')
    @if (config('chatbot.notification_enabled', true))
        <script>
            $(document).ready(function() {
                // Function to fetch the notification count
                function fetchNotificationCount() {
                    $.ajax({
                        url: '{{ route('dashboard.chatbot-agent.notification.count') }}',
                        type: 'GET',
                        success: function(data) {
                            let inboxNotification = $('#inbox-notification-chatbot-agent');

                            if (data.count > 0) {
                                inboxNotification.text(data.count);
                                inboxNotification.addClass('active')
                            } else {
                                inboxNotification.removeClass('active')
                            }
                        },
                        error: function() {
                            console.error('Error fetching notification count');
                        }
                    });
                }

                // Fetch the notification count on page load
                fetchNotificationCount();

                // Optionally, you can set an interval to refresh the count periodically
                setInterval(fetchNotificationCount, 10000); // Refresh every minute
            });
        </script>
    @endif
@endpush

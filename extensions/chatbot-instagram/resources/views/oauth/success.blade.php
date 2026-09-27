<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Instagram Bağlantısı</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                background: #0f172a;
                color: #e2e8f0;
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .card {
                background: #1e293b;
                border-radius: 16px;
                padding: 32px;
                max-width: 460px;
                width: 100%;
                box-shadow: 0 20px 45px rgba(15, 23, 42, 0.45);
            }
            .card h1 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }
            .card p {
                color: #94a3b8;
                margin-bottom: 1.5rem;
            }
            .card ul {
                list-style: none;
                padding: 0;
                margin: 0 0 1.5rem;
            }
            .card li {
                margin-bottom: .4rem;
            }
            button {
                width: 100%;
                background: linear-gradient(135deg, #2563eb, #ec4899);
                border: none;
                border-radius: 12px;
                color: white;
                font-size: 1rem;
                padding: 0.85rem 1rem;
                cursor: pointer;
            }
            button:focus-visible {
                outline: 2px solid #94a3b8;
                outline-offset: 2px;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>{{ __('Instagram connection completed') }}</h1>
            <p>{{ __('The necessary access details have been saved to the system. You may close this window.') }}</p>

            <ul>
                <li><strong>{{ __('Instagram User') }}:</strong> {{ data_get($credentials, 'username') }}</li>
                <li><strong>{{ __('Name') }}:</strong> {{ data_get($credentials, 'name') }}</li>
                <li><strong>{{ __('Page') }}:</strong> {{ data_get($credentials, 'page_name') }}</li>
            </ul>

            <button type="button" onclick="window.close()">
                {{ __('Close the window') }}
            </button>
        </div>

        <script>
            (function () {
                const payload = @json($credentials);
                window.opener?.postMessage({
                    type: 'chatbot-instagram:authorized',
                    payload,
                }, window.location.origin);
            })();
        </script>
    </body>
</html>

<!doctype html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Atualização em andamento</title>
        <style>
            :root {
                color-scheme: light;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background: #f6f7fb;
                color: #1f2937;
            }

            * {
                box-sizing: border-box;
            }

            body {
                min-block-size: 100dvh;
                margin: 0;
                display: grid;
                place-items: center;
                padding: max(1.5rem, env(safe-area-inset-top)) max(1.5rem, env(safe-area-inset-right)) max(1.5rem, env(safe-area-inset-bottom)) max(1.5rem, env(safe-area-inset-left));
                background:
                    radial-gradient(circle at 12% 8%, rgb(171 38 70 / 14%), transparent 28rem),
                    radial-gradient(circle at 88% 92%, rgb(35 57 101 / 12%), transparent 30rem),
                    #f6f7fb;
            }

            .schema-unavailable {
                inline-size: min(100%, 34rem);
                padding: clamp(2rem, 7vw, 4rem);
                text-align: center;
                background: rgb(255 255 255 / 94%);
                border: 1px solid rgb(255 255 255 / 90%);
                border-radius: 1.75rem;
                box-shadow: 0 1.5rem 4rem rgb(31 41 55 / 14%);
            }

            .schema-unavailable__identity {
                display: grid;
                justify-items: center;
                gap: 1rem;
                margin-block-end: 2.25rem;
            }

            .schema-unavailable__crest {
                inline-size: clamp(4rem, 14vw, 6rem);
                block-size: auto;
                filter: drop-shadow(0 0.5rem 0.8rem rgb(31 41 55 / 16%));
            }

            .schema-unavailable__brand {
                inline-size: min(100%, 21rem);
                block-size: auto;
            }

            h1 {
                margin: 0;
                color: #1b2434;
                font-size: clamp(1.6rem, 4vw, 2.05rem);
                font-weight: 700;
                letter-spacing: -0.025em;
                line-height: 1.18;
            }

            .schema-unavailable__message {
                margin: 1rem auto 0;
                max-inline-size: 28rem;
                color: #526075;
                font-size: 1rem;
                line-height: 1.6;
            }

            .schema-unavailable__retry {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-block-size: 2.875rem;
                margin-block-start: 2rem;
                padding-inline: 1.35rem;
                border-radius: 0.75rem;
                background: #aa2646;
                box-shadow: 0 0.5rem 1rem rgb(170 38 70 / 24%);
                color: #fff;
                font-weight: 700;
                line-height: 1;
                text-decoration: none;
                transition: background-color 150ms ease, transform 150ms ease, box-shadow 150ms ease;
            }

            .schema-unavailable__retry:hover {
                background: #8f1d3a;
                box-shadow: 0 0.65rem 1.25rem rgb(170 38 70 / 30%);
                transform: translateY(-1px);
            }

            .schema-unavailable__retry:focus-visible {
                outline: 3px solid #1d4ed8;
                outline-offset: 4px;
            }

            @media (max-width: 30rem) {
                .schema-unavailable {
                    border-radius: 1.25rem;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .schema-unavailable__retry {
                    transition: none;
                }
            }
        </style>
    </head>
    <body>
        <main class="schema-unavailable" aria-labelledby="schema-incompatible-title">
            <div class="schema-unavailable__identity">
                <img class="schema-unavailable__crest" src="{{ asset('assets/brand/crest-192.png?v=20260930') }}" alt="" aria-hidden="true">
                <img class="schema-unavailable__brand" src="{{ asset('assets/brand/logo-768.png?v=20260930') }}" alt="Rinos One">
            </div>
            <h1 id="schema-incompatible-title" tabindex="-1">Atualização em andamento</h1>
            <p class="schema-unavailable__message">A plataforma está temporariamente indisponível para atualização.</p>
            <a class="schema-unavailable__retry" href="{{ url()->full() }}">Tentar novamente</a>
        </main>
    </body>
</html>

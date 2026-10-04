<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $code }} - POMS Report</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%2315803d'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle'%3E%F0%9F%8C%BF%3C/text%3E%3C/svg%3E">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --brand: #10b981;
            --brand-strong: #047857;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", Inter, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
            padding: 1rem;
            background-color: #eef7f1;
            background-image:
                radial-gradient(900px 620px at 6% -8%, rgba(16, 185, 129, 0.38), transparent 60%),
                radial-gradient(820px 560px at 102% 6%, rgba(56, 189, 248, 0.30), transparent 58%),
                radial-gradient(780px 560px at 46% 112%, rgba(132, 204, 22, 0.32), transparent 60%),
                linear-gradient(160deg, #f2fbf5 0%, #eefcfb 48%, #f4fbef 100%);
            background-attachment: fixed;
        }

        /* Aurora blobs behind the glass */
        body::before,
        body::after {
            content: "";
            position: fixed;
            z-index: 0;
            pointer-events: none;
            border-radius: 9999px;
            filter: blur(60px);
            opacity: 0.55;
        }

        body::before {
            width: 46vw; height: 46vw; top: -12vw; left: -8vw;
            background: radial-gradient(circle at 30% 30%, rgba(16, 185, 129, 0.55), transparent 70%);
        }

        body::after {
            width: 40vw; height: 40vw; bottom: -14vw; right: -6vw;
            background: radial-gradient(circle at 60% 40%, rgba(59, 130, 246, 0.42), transparent 70%);
        }

        .card {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 440px;
            width: 100%;
            padding: 3rem 2rem;
            border-radius: 1.75rem;
            border: 1px solid rgba(255, 255, 255, 0.6);
            background-image: linear-gradient(140deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.44));
            backdrop-filter: blur(24px) saturate(150%);
            -webkit-backdrop-filter: blur(24px) saturate(150%);
            box-shadow: 0 30px 60px -28px rgba(6, 60, 36, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }

        .icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 3.5rem;
            height: 3.5rem;
            margin-bottom: 1rem;
            border-radius: 1.25rem;
            font-size: 1.5rem;
            color: #fff;
            background-image: linear-gradient(135deg, #34d399, #0d9488);
            box-shadow: 0 12px 26px -12px rgba(5, 150, 105, 0.85), inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .code {
            font-size: 4.25rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #059669, #0f766e);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .title { font-size: 1.25rem; font-weight: 700; margin-top: 0.75rem; color: #1f2937; }

        .message { color: #4b5563; margin-top: 0.5rem; font-size: 0.95rem; line-height: 1.55; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            margin-top: 1.75rem;
            padding: 0.625rem 1.5rem;
            border-radius: 1rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            color: #fff;
            background-image: linear-gradient(135deg, #22c55e, #059669 60%, #047857);
            box-shadow: 0 12px 26px -12px rgba(5, 150, 105, 0.85), inset 0 1px 0 rgba(255, 255, 255, 0.4);
            transition: filter 0.15s ease;
        }

        .btn:hover { filter: brightness(1.05); }

        @media (prefers-reduced-transparency: reduce) {
            body::before, body::after { display: none; }
            .card { backdrop-filter: none; -webkit-backdrop-filter: none; background-image: none; background-color: #fff; }
        }

        @media (prefers-contrast: more) {
            .card { background-image: none; background-color: rgba(255, 255, 255, 0.96); border-color: rgba(15, 23, 42, 0.35); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon" aria-hidden="true">🌿</div>
        <div class="code">{{ $code }}</div>
        <div class="title">{{ $title }}</div>
        <p class="message">{{ $message }}</p>
        <a class="btn" href="{{ url('/') }}">Kembali ke Beranda</a>
    </div>
</body>
</html>
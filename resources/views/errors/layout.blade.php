<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $code }} - POMS Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f3f4f6; color: #1f2937; padding: 1rem;
        }
        .card {
            text-align: center; max-width: 420px; width: 100%;
            background: #fff; border-radius: 12px; padding: 3rem 2rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        .code { font-size: 4.5rem; font-weight: 800; color: #166534; line-height: 1; }
        .title { font-size: 1.25rem; font-weight: 700; margin-top: 1rem; }
        .message { color: #6b7280; margin-top: .5rem; font-size: .95rem; line-height: 1.5; }
        .btn {
            display: inline-block; margin-top: 1.75rem; padding: .625rem 1.5rem;
            background: #166534; color: #fff; border-radius: 8px;
            text-decoration: none; font-weight: 600; font-size: .9rem;
        }
        .btn:hover { background: #14532d; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">{{ $code }}</div>
        <div class="title">{{ $title }}</div>
        <p class="message">{{ $message }}</p>
        <a class="btn" href="{{ url('/') }}">Kembali ke Beranda</a>
    </div>
</body>
</html>

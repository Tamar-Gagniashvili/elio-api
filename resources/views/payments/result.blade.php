<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @if ($state === 'pending')
        <meta http-equiv="refresh" content="5">
    @endif
    <title>{{ $text[$state] }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f5fa; color: #1f2937;
        }
        .card { width: 100%; max-width: 400px; background: #fff; border-radius: 20px; padding: 36px 28px; text-align: center; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
        .icon { width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; }
        .paid .icon { background: #ccfbf1; color: #0d9488; }
        .failed .icon { background: #fee2e2; color: #dc2626; }
        .pending .icon { background: #e0e7ff; color: #4f46e5; }
        .pending .icon svg { animation: spin 1.2s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .pending .icon svg { animation: none; } }
        h1 { font-size: 22px; margin: 0 0 8px; font-weight: 600; }
        p { margin: 0; color: #6b7280; line-height: 1.5; font-size: 15px; }
        .amount { margin-top: 24px; padding-top: 20px; border-top: 1px solid #eef0f4; display: flex; justify-content: space-between; font-size: 15px; }
        .amount span:first-child { color: #6b7280; }
        .amount span:last-child { font-weight: 600; }
        .clinic { margin-top: 20px; font-size: 13px; color: #9ca3af; }
    </style>
</head>
<body>
    <main class="card {{ $state }}">
        <div class="icon" aria-hidden="true">
            @if ($state === 'paid')
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            @elseif ($state === 'failed')
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            @else
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.2-8.56"/></svg>
            @endif
        </div>
        <h1>{{ $text[$state] }}</h1>
        <p>{{ $text[$state.'_text'] }}</p>
        <div class="amount">
            <span>{{ $text['amount'] }}</span>
            <span>{{ $amount }} {{ $currency }}</span>
        </div>
        @if ($clinic)
            <div class="clinic">{{ $clinic }}</div>
        @endif
    </main>
</body>
</html>

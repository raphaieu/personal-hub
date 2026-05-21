<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Confirmação') — Events</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @include('layouts.partials.ga4')
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0a0a0f;
            color: #e8e6f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-font-smoothing: antialiased;
            line-height: 1.6;
        }
        .ambient { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .ambient-orb {
            position: absolute; border-radius: 50%; filter: blur(120px);
            animation: orbFloat 20s infinite ease-in-out;
        }
        .ambient-orb-1 {
            width: 500px; height: 500px; background: #6c5ce7;
            top: -15%; right: -10%; opacity: 0.08;
        }
        .ambient-orb-2 {
            width: 400px; height: 400px; background: #6c5ce7;
            bottom: 10%; left: -10%; opacity: 0.05; animation-delay: -10s;
        }
        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.05); }
            66% { transform: translate(-20px, 15px) scale(0.95); }
        }
        .container {
            position: relative; z-index: 1;
            width: 100%; max-width: 420px; margin: 2rem 1rem;
        }
        .card {
            background: #16161f; border: 1px solid rgba(232, 230, 240, 0.08);
            border-radius: 16px; padding: 2rem;
            text-align: center;
            position: relative; overflow: hidden;
        }
        .card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #6c5ce7, transparent);
            opacity: 0.6;
        }
        .icon-wrap {
            width: 56px; height: 56px; margin: 0 auto 1.25rem;
            background: rgba(108, 92, 231, 0.12);
            border: 1px solid rgba(108, 92, 231, 0.25);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
        }
        .icon-wrap svg { width: 26px; height: 26px; }
        .icon-success { color: #6c5ce7; }
        .icon-warning { color: #f0a020; }
        .icon-error { color: #e45a5a; }
        h1 {
            font-family: 'Outfit', sans-serif; font-weight: 700;
            font-size: 1.3rem; margin-bottom: 0.5rem;
        }
        p { font-size: 0.9rem; color: rgba(232, 230, 240, 0.6); line-height: 1.7; }
        p strong { color: #e8e6f0; font-weight: 600; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 1.5rem;
            padding: 0.7rem 1.5rem;
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
            border: none; border-radius: 100px;
            color: #fff; font-family: 'Outfit', sans-serif;
            font-weight: 600; font-size: 0.85rem;
            text-decoration: none; cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(108, 92, 231, 0.3);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(108, 92, 231, 0.4); }
    </style>
</head>
<body>
    <div class="ambient" aria-hidden="true">
        <div class="ambient-orb ambient-orb-1"></div>
        <div class="ambient-orb ambient-orb-2"></div>
    </div>
    <div class="container">
        <div class="card">
            @yield('content')
        </div>
    </div>
</body>
</html>

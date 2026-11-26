<!doctype html>
<html lang="en">
<head>
    @include('market.favicon')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} · Avelle</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 28px; color: #302438; background: #fbf7fa; font-family: Arial, Helvetica, sans-serif; }
        .error-card { width: min(100%, 850px); background: #fff; border: 1px solid #eadfe8; border-radius: 24px; overflow: hidden; box-shadow: 0 18px 60px #4c24480a; }
        header { padding: 25px 34px; border-bottom: 1px solid #f0e5ed; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .brand { font-size: 30px; font-weight: 700; letter-spacing: -1.5px; text-decoration: none; color: #302438; }
        .brand span { color: #db2777; }
        .label { font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #79657e; }
        main { padding: 48px 52px 52px; }
        .status { font-size: clamp(76px, 14vw, 128px); font-weight: 700; letter-spacing: -7px; line-height: 1; color: #d82778; margin: 0 0 24px; }
        h1 { font-size: clamp(26px, 4vw, 36px); letter-spacing: -1px; line-height: 1.2; margin: 0 0 16px; }
        p { max-width: 540px; font-size: 16px; line-height: 1.75; color: #6a596f; margin: 0; }
        .actions { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; margin-top: 30px; }
        .button { display: inline-block; padding: 14px 21px; border-radius: 9px; background: #d82778; color: #fff; text-decoration: none; font-size: 14px; font-weight: 700; }
        .button:hover { background: #b91e65; }
        .secondary { color: #6a4d73; background: #f4edf6; }
        .secondary:hover { background: #ebe0ef; }
        a:focus-visible { outline: 3px solid #34223d; outline-offset: 4px; }
        footer { padding: 20px 34px; background: #fcf9fc; color: #79657e; font-size: 12px; border-top: 1px solid #f0e5ed; line-height: 1.6; }
        @media (max-width: 560px) { body { padding: 16px; } header { padding: 22px; } main { padding: 34px 25px 38px; } footer { padding: 18px 22px; } .label { font-size: 9px; } }
    </style>
</head>
<body>
    <div class="error-card">
        <header><a class="brand" href="{{ url('/') }}" aria-label="Avelle home">avelle<span>.</span></a><span class="label">Your style. Your everyday.</span></header>
        <main>
            <div class="status" aria-hidden="true">{{ $status }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
            <div class="actions">
                <a class="button" href="{{ url('/') }}">Back to home →</a>
                @if (request()->isMethod('GET') && $status !== 404)
                    <a class="button secondary" href="{{ request()->fullUrl() }}">Try again</a>
                @endif
            </div>
        </main>
        <footer>Avelle · HTTP {{ $status }}<br>Thank you for your patience.</footer>
    </div>
</body>
</html>

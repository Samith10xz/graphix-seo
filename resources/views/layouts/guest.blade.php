<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'Sign in' }} · Graphix SEO</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="guest">
    <div class="card">
        <div class="brand" style="color:#0F172A;padding:0"><span class="logo">G</span>Graphix SEO</div>
        @yield('content')
    </div>
</div>
</body>
</html>

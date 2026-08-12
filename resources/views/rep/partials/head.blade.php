<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'DukaFlow — Rep' }}</title>

<link rel="manifest" href="/manifest.json">
<link rel="icon" href="/rep-icon.svg">
<link rel="apple-touch-icon" href="/rep-icon.svg">

@fonts
@vite(['resources/css/app.css'])

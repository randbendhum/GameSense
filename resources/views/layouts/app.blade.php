<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'GameSense AI') }} // REAL-TIME TACTICAL ANALYSIS PANEL</title>

    <!-- Neo-Brutalist Typography: Archivo Black (Display) + JetBrains Mono (Metrics/Terminal) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">

    <!-- Pure Structured CSS3 (Neo-Brutalism / Pure CSS Grid) -->
    <link rel="stylesheet" href="{{ asset('css/neo-brutalism.css') }}">
</head>
<body>
    @yield('content')

    <script src="{{ asset('js/gamesense.js') }}" defer></script>
</body>
</html>

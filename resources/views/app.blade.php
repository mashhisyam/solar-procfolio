<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Procfolio') }}</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <script>
        (function () { try { var t = localStorage.getItem('procfolio-theme'); if (t && t !== 'system') document.documentElement.setAttribute('data-theme', t); } catch (e) {} })();
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

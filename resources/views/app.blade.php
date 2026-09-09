<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <meta name="description" content="Sistem Booking Asisten Dosen — pembagian pasangan Asdos untuk setiap praktikum.">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <title inertia>{{ config('app.name', 'SIBADOS') }}</title>

    {{-- Memuat @font-face Instrument Sans beserta preload-nya. Tanpa direktif ini
         berkas font ikut ter-build tetapi tidak pernah dipakai browser. --}}
    @fonts

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="min-h-full bg-canvas font-sans text-ink antialiased">
    @inertia
</body>
</html>

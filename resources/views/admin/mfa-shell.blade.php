<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Keamanan Admin — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<main class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-10">
    <section class="w-full rounded-2xl bg-white p-6 shadow-xl sm:p-8">
        @yield('content')
    </section>
</main>
</body>
</html>

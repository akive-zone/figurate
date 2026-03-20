<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Figurate Desktop</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center gap-4 p-8 text-center">
        <p class="text-xs uppercase tracking-[0.25em] text-slate-400">Desktop Native</p>
        <h1 class="text-3xl font-semibold">Desktop runtime extension ready</h1>
        <p class="max-w-xl text-sm text-slate-300">
            This package is registered, but desktop runtime bootstrapping is intentionally deferred
            until the native command and config collision strategy is decided.
        </p>
    </div>
</body>
</html>

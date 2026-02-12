<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Figurate Launcher</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div class="mx-auto flex min-h-screen max-w-xl flex-col items-center justify-center gap-6 p-8">
        <div class="text-center">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-400">NativePHP</p>
            <h1 class="mt-3 text-3xl font-semibold">Choose your session</h1>
            <p class="mt-2 text-sm text-slate-300">Signal for requests. Studio for fulfillment.</p>
        </div>

        <div class="grid w-full gap-3">
            <a
                href="{{ url('/signal') }}"
                class="rounded-xl border border-slate-700 bg-slate-900 px-5 py-4 text-left transition hover:border-slate-500"
            >
                <p class="text-lg font-semibold">Signal</p>
                <p class="text-sm text-slate-400">Find a profile and make a request.</p>
            </a>

            <a
                href="{{ url('/studio') }}"
                class="rounded-xl border border-slate-700 bg-slate-900 px-5 py-4 text-left transition hover:border-slate-500"
            >
                <p class="text-lg font-semibold">Studio</p>
                <p class="text-sm text-slate-400">Accept requests and deliver services.</p>
            </a>

        </div>
    </div>
</body>
</html>

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Chat</h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                Open a space and start chatting. The chat flow runs in the Inertia interface.
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <a
                href="{{ url('/spaces') }}"
                class="rounded-xl border border-zinc-200 bg-white px-5 py-4 text-sm font-medium text-zinc-900 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:border-zinc-700 dark:hover:bg-zinc-800"
            >
                Open Spaces
            </a>
            <a
                href="{{ url('/spaces/new') }}"
                class="rounded-xl border border-transparent bg-zinc-900 px-5 py-4 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
            >
                Start Chat
            </a>
        </div>
    </div>
</x-filament-panels::page>

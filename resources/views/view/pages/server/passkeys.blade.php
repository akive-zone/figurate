<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Add, list, and delete your account passkeys.
            </p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            @livewire('passkeys')
        </div>
    </div>

    @vite('resources/js/passkeys.js')
</x-filament-panels::page>

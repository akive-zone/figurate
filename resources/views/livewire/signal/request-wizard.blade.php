<div>
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <button
                type="submit"
                class="inline-flex items-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-400"
            >
                Save Draft Request
            </button>
            @if ($draftRequestId)
                <button
                    type="button"
                    wire:click="startChannel"
                    class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                >
                    Start Channel
                </button>
            @endif
        </div>
    </form>

    @if ($draftRequestId)
        <p class="mt-4 text-sm text-slate-600">
            Draft #{{ $draftRequestId }} saved. Start Channel to begin fulfillment chat.
        </p>
    @endif

    <x-filament-actions::modals />
</div>

<div class="flex flex-wrap items-center gap-2">
    @foreach ($tabLabels as $key => $label)
        <button
            type="button"
            wire:click="setTab('{{ $key }}')"
            @class([
                'px-3 py-2 rounded-md text-sm font-medium transition',
                'bg-indigo-600 text-white' => $currentTab === $key,
                'bg-gray-100 text-gray-700 hover:bg-gray-200' => $currentTab !== $key,
            ])
        >
            {{ $label }}
        </button>
    @endforeach
</div>

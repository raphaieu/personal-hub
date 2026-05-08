<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Hub Threads
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                @include('livewire.threads.hub.tabs')
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                @include('livewire.threads.hub.flash')

                @if ($currentTab === 'sources')
                    @include('livewire.threads.hub.sources-tab')
                @elseif ($currentTab === 'review')
                    @include('livewire.threads.hub.review-tab')
                @else
                    @include('livewire.threads.hub.published-tab')
                @endif
            </div>
        </div>
    </div>
</div>

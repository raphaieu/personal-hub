@if (session('threads_hub_notice'))
    <div class="mb-4 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-700">
        {{ session('threads_hub_notice') }}
    </div>
@endif

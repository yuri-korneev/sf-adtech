<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Subscriptions</h2>
            <a
                href="{{ route('wm.offers') }}"
                class="px-3 py-2 rounded-md shadow
                       bg-white text-gray-900 border border-gray-300
                       hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300"
            >
                Find offers
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="bg-green-100 text-green-800 p-2 rounded mb-3">{{ session('status') }}</div>
            @endif

            <table class="w-full text-left border">
                <thead>
                    <tr>
                        <th class="p-2">Offer</th>
                        <th class="p-2">CPC</th>
                        <th class="p-2">Token</th>
                        <th class="p-2">Link</th>
                        <th class="p-2">Active</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($subs as $s)
                    <tr class="border-t">
                        <td class="p-2">{{ $s->offer?->name }}</td>
                        <td class="p-2">{{ $s->cpc }}</td>
                        <td class="p-2 font-mono text-xs break-all">{{ $s->token }}</td>
                        <td class="p-2">
                            <input
                                type="text"
                                readonly
                                class="border p-1 w-full font-mono text-xs"
                                value="{{ url('/r/'.$s->token) }}"
                                onclick="this.select();document.execCommand('copy');"
                            >
                        </td>
                        <td class="p-2">{{ $s->is_active ? 'Yes' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td class="p-2" colspan="5">No subscriptions yet</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="mt-4">{{ $subs->links() }}</div>
        </div>
    </div>
</x-app-layout>

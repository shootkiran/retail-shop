<x-filament-panels::page>
    <div class="flex flex-col gap-y-6">
        <x-filament::tabs>
            @foreach($this->accountCategories as $categoryValue => $payload)
                <x-filament::tabs.item
                    wire:key="coa-tab-{{ $categoryValue }}"
                    :active="$activeTab === $categoryValue"
                    wire:click="$set('activeTab', '{{ $categoryValue }}')"
                    :badge="$payload['subtypes']->sum(fn ($subtype) => $subtype->accounts->count())"
                >
                    {{ $payload['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        @foreach($this->accountCategories as $categoryValue => $payload)
            @if($activeTab === $categoryValue)
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    @foreach($payload['subtypes'] as $subtype)
                        <div class="border-b border-gray-200 dark:border-white/10">
                            <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-4 py-4 dark:border-white/5">
                                <div>
                                    <div class="text-sm font-semibold tracking-wide text-gray-800 dark:text-gray-100">
                                        {{ $subtype->name }}
                                    </div>
                                    @if(filled($subtype->description))
                                        <div class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                                            {{ $subtype->description }}
                                        </div>
                                    @endif
                                </div>

                                <div class="shrink-0">
                                    {{ ($this->createAccountAction)(['accountSubtype' => $subtype->id]) }}
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[60rem] divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
                                    <colgroup>
                                        <col span="1" style="width: 12%;">
                                        <col span="1" style="width: 22%;">
                                        <col span="1" style="width: 45%;">
                                        <col span="1" style="width: 11%;">
                                        <col span="1" style="width: 10%;">
                                    </colgroup>
                                    <thead class="bg-gray-50 dark:bg-white/5">
                                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            <th class="px-4 py-3">Code</th>
                                            <th class="px-4 py-3">Account</th>
                                            <th class="px-4 py-3">Description</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                        @forelse($subtype->accounts as $account)
                                            <tr>
                                                <td class="px-4 py-4 font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $account->code }}
                                                </td>
                                                <td class="px-4 py-4 text-gray-800 dark:text-gray-100">
                                                    {{ $account->name }}
                                                </td>
                                                <td class="px-4 py-4 text-gray-600 dark:text-gray-400">
                                                    {{ $account->description ?: '—' }}
                                                </td>
                                                <td class="px-4 py-4">
                                                    @if($account->archived)
                                                        <x-filament::badge color="gray">Archived</x-filament::badge>
                                                    @else
                                                        <x-filament::badge color="success">Active</x-filament::badge>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-4">
                                                    {{ ($this->editAccountAction)(['account' => $account->id]) }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-6 text-sm italic text-gray-500 dark:text-gray-400">
                                                    No accounts added in this subtype yet.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>
</x-filament-panels::page>

<section class="flex flex-col gap-y-4" id="call-messages" wire:ignore>
    @foreach($getRecord()->callMessages as $message)
        @php
            $isCustomer = $message->role === \App\Enums\CallRoles::CUSTOMER;
        @endphp
        <div @class([
            'flex max-w-[82%] items-end gap-x-3',
            'self-end' => $isCustomer
        ])>
            <div @class([
                'rounded-full bg-gray-200 text-gray-400 p-2 flex items-center justify-center dark:bg-gray-700 dark:text-gray-300',
                'order-1' => $isCustomer
            ])>
                @if($isCustomer)
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-icon lucide-user-round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-brain-circuit-icon lucide-brain-circuit"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M9 13a4.5 4.5 0 0 0 3-4"/><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"/><path d="M3.477 10.896a4 4 0 0 1 .585-.396"/><path d="M6 18a4 4 0 0 1-1.967-.516"/><path d="M12 13h4"/><path d="M12 18h6a2 2 0 0 1 2 2v1"/><path d="M12 8h8"/><path d="M16 8V5a2 2 0 0 1 2-2"/><circle cx="16" cy="13" r=".5"/><circle cx="18" cy="3" r=".5"/><circle cx="20" cy="21" r=".5"/><circle cx="20" cy="8" r=".5"/></svg>
                @endif
            </div>
            <div @class([
                'relative rounded-2xl px-4 pt-3 pb-7 text-sm leading-6 shadow-sm ring-1 ring-inset',
                'bg-sky-600 text-white ring-sky-500/20 rounded-br-md' => $isCustomer,
                'rounded-bl-md bg-white text-gray-900 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-700/80' => !$isCustomer
            ])>
                <p class="whitespace-pre-wrap break-words pr-18">{{ $message->content }}</p>
                <span @class([
                    'absolute bottom-2.5 right-3 text-[11px] font-medium',
                    'text-white/80' => $isCustomer,
                    'text-gray-500 dark:text-gray-400' => !$isCustomer,
                ])>
                    {{ $message->created_at?->format('M j, g:i A') }}
                </span>
            </div>
        </div>
    @endforeach
</section>

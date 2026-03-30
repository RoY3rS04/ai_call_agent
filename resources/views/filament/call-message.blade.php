<section class="space-y-5">
    @foreach($getRecord()->callMessages as $message)
        <div class="flex gap-x-2 items-start max-w-[50%]">
            @if($message->role === 'ASSISTANT')
                <div class="rounded-full p-2 bg-gray-200 text-black">
                    {{ $message->role }}
                </div>
                <div class="bg-gray-400 p-1 rounded-b-lg rounded-tr-lg">
                    <p class="text-white">{{ $message->content }}</p>
                </div>
            @else
                <div class="bg-blue-400 p-1 rounded-b-lg rounded-tl-lg">
                    <p class="text-white">{{ $message->content }}</p>
                </div>
                <div class="rounded-full p-2 bg-gray-200 text-black">
                    {{ $message->role }}
                </div>
            @endif
        </div>
    @endforeach
</section>

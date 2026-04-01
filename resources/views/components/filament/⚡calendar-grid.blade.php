<?php

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Component;

new class extends Component {

    public function getPeriodMonth(Carbon $date): CarbonPeriod
    {
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        return CarbonPeriod::create($start, $end);
    }

    public array $dayPositions = [
        'Sunday' => 0,
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6
    ];
}
?>

<div class="flex flex-col gap-y-4">
    <div class="grid grid-cols-7 gap-5">
        @foreach(Carbon::getDays() as $day)
            <div>
                {{ $day }}
            </div>
        @endforeach
    </div>
    <section class="grid grid-cols-7 gap-5">
        @foreach($this->getPeriodMonth(now()) as $idx => $date)
            @if($idx === 0 && ($pos = $this->dayPositions[$date->dayName]) !== 0)
                @for($i = 0; $i < $pos; $i++)
                    <div class="w-full p-4 h-[115px]">

                    </div>
                @endfor
            @endif
            <div class="w-full p-4 flex items-center justify-center h-[115px] bg-amber-200">
                {{ $date->format('Y-m-d') }}
            </div>
        @endforeach
    </section>
</div>

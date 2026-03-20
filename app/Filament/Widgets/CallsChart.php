<?php

namespace App\Filament\Widgets;

use App\Models\Call;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class CallsChart extends ChartWidget
{
    protected ?string $heading = 'Calls Chart';

    protected function getData(): array
    {
        $data = Trend::model(Call::class)
            ->between(
                start: \Illuminate\Support\now()->startOfYear(),
                end: \Illuminate\Support\now()->endOfYear()
            )->perMonth()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Calls',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ]
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date)
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

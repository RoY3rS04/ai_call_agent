<?php

namespace App\Filament\Widgets;

use App\Enums\LeadSource;
use App\Models\Customer;
use Filament\Widgets\ChartWidget;

class LeadSourcesChart extends ChartWidget
{
    protected ?string $heading = 'Customers Lead Sources';

    protected function getData(): array
    {

        $sources = collect(
            LeadSource::cases()
        )
            ->map(function ($source) {

            $rawColor = $source->getColor();

            return [
                'label' => $source->value,
                'color' => is_array($rawColor) ? $rawColor[300] : $rawColor,
                'border_color' => is_array($rawColor) ? $rawColor[700] : $rawColor,
            ];
        })->sortDesc();

        $customersCountByLeadSource = Customer::selectRaw('count(*) as count')
            ->groupBy('lead_source')
            ->orderByDesc('lead_source')
            ->pluck('count')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Customers lead sources',
                    'data' => $customersCountByLeadSource,
                    'backgroundColor' => $sources->map(fn ($source) => $source['color'])->values(),
                    'borderColor' => $sources->map(fn ($source) => $source['border_color'])->values(),
                ],
            ],
            'labels' => $sources->map(fn ($source) => $source['label'])->values(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

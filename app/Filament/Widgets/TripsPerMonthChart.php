<?php

namespace App\Filament\Widgets;

use App\Models\Trip;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

// Bar chart of trips per month (3 months back … 5 months ahead) for Admin/Superadmin.
class TripsPerMonthChart extends ChartWidget
{
    protected static ?int $sort = 2;

    public function getHeading(): ?string
    {
        return 'Výlety po měsících';
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin', 'Admin']) ?? false;
    }

    protected function getData(): array
    {
        $labels = [];
        $counts = [];
        $start = Carbon::today()->startOfMonth()->subMonths(3);

        for ($i = 0; $i < 9; $i++) {
            $month = $start->copy()->addMonths($i);
            $labels[] = $month->translatedFormat('M y');
            $counts[] = Trip::where('status', '!=', 'cancelled')
                ->whereYear('date_from', $month->year)
                ->whereMonth('date_from', $month->month)
                ->count();
        }

        return [
            'datasets' => [[
                'label' => 'Výlety',
                'data' => $counts,
                'backgroundColor' => '#09afe6',
                'borderRadius' => 4,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

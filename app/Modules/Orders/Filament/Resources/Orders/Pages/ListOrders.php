<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Pages;

use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Services\OrderWriter;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Scope;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

/**
 * Orders are opened through OrderWriter rather than a create form, because
 * the reference has to be issued in sequence and the currency normalised
 * before anything else touches the record.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $directory = app(ScopeDirectory::class);

        return [
            Action::make('open')
                ->label('New order')
                ->icon('heroicon-o-plus')
                ->schema([
                    Select::make('customer_id')
                        ->label('Outlet')
                        ->options(fn (): array => $directory->options(Scope::Customer->value))
                        ->searchable()
                        ->required(),
                    Select::make('sales_rep_id')
                        ->label('Rep')
                        ->options(fn (): array => $directory->options(Scope::SalesRep->value))
                        ->searchable()
                        ->placeholder('Taken in the office'),
                    Select::make('route_id')
                        ->label('Route')
                        ->options(fn (): array => $directory->options(Scope::Route->value))
                        ->searchable()
                        ->placeholder('None'),
                    DateTimePicker::make('placed_at')
                        ->label('Taken at')
                        ->default(now())
                        ->required()
                        ->helperText('The date prices the order.'),
                ])
                ->action(function (array $data): void {
                    $order = app(OrderWriter::class)->startDraft(
                        customerId: (int) $data['customer_id'],
                        salesRepId: $data['sales_rep_id'] === null ? null : (int) $data['sales_rep_id'],
                        routeId: $data['route_id'] === null ? null : (int) $data['route_id'],
                        placedAt: Carbon::parse($data['placed_at']),
                    );

                    $this->redirect(OrderResource::getUrl('edit', ['record' => $order]));
                }),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\RelationManagers;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Orders\Services\OrderWriter;
use App\Support\Contracts\ScopeDirectory;
use App\Support\Money;
use App\Support\Scope;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

/**
 * The lines on an order.
 *
 * Everything here goes through OrderWriter rather than writing rows directly,
 * because adding a line means three things at once: pricing it from the list
 * in force on the day the order was taken, copying the product's details onto
 * it, and rewriting the order's total. A plain relation form would do none of
 * them, and the order would end up with a line at price zero.
 */
class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Lines';

    protected static ?string $modelLabel = 'line';

    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('quantity')
                ->numeric()
                ->minValue(1)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_sku')->label('SKU')->searchable(),
                TextColumn::make('product_name')->label('Product')->searchable()->wrap(),
                TextColumn::make('unit_code')->label('Unit')->badge()->placeholder('—'),
                TextColumn::make('quantity')->alignEnd(),
                TextColumn::make('unit_price_minor')
                    ->label('Unit price')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::ofMinor($state, $this->currency())->format()),
                TextColumn::make('line_total_minor')
                    ->label('Line total')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Money::ofMinor($state, $this->currency())->format()),
            ])
            ->headerActions([$this->addLineAction()])
            ->recordActions([
                $this->changeQuantityAction(),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->order()->status->allowsEditingLines())
                    ->using(function (OrderLine $record): void {
                        app(OrderWriter::class)->removeLine($this->order(), $record);
                    }),
            ])
            ->emptyStateHeading('Nothing on this order yet')
            ->emptyStateDescription('An order with no lines cannot be submitted.');
    }

    private function addLineAction(): Action
    {
        return Action::make('addLine')
            ->label('Add a product')
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => $this->order()->status->allowsEditingLines())
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->options(fn (): array => app(ScopeDirectory::class)->options(Scope::Product->value))
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
            ])
            ->action(function (array $data): void {
                try {
                    app(OrderWriter::class)->addLine(
                        $this->order(),
                        (int) $data['product_id'],
                        (int) $data['quantity'],
                    );
                } catch (Throwable $failure) {
                    // Unpriced or discontinued products are refused by the
                    // writer. Say why rather than falling over.
                    Notification::make()
                        ->title('Could not add that product')
                        ->body($failure->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title('Line added')->success()->send();
            });
    }

    private function changeQuantityAction(): Action
    {
        return Action::make('changeQuantity')
            ->label('Change quantity')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (): bool => $this->order()->status->allowsEditingLines())
            ->schema([
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
            ])
            ->fillForm(fn (OrderLine $record): array => ['quantity' => $record->quantity])
            ->action(function (OrderLine $record, array $data): void {
                app(OrderWriter::class)->changeQuantity($this->order(), $record, (int) $data['quantity']);

                Notification::make()->title('Quantity updated')->success()->send();
            });
    }

    private function order(): Order
    {
        $order = $this->getOwnerRecord();

        // The relation manager is only ever mounted from an order page.
        assert($order instanceof Order);

        return $order;
    }

    private function currency(): string
    {
        return $this->order()->currency;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Pages;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Filament\Resources\Orders\OrderResource;
use App\Modules\Orders\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

/**
 * Status moves only through these, so the guards in the model are the only
 * way an order changes state. Each button is offered only when the current
 * state allows it, rather than shown and then refused.
 */
class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->statusAction('submit', 'Submit', 'heroicon-o-paper-airplane', OrderStatus::Submitted, 'info'),
            $this->statusAction('approve', 'Approve', 'heroicon-o-check', OrderStatus::Approved, 'warning'),
            $this->statusAction('fulfil', 'Fulfil', 'heroicon-o-truck', OrderStatus::Fulfilled, 'success'),
            $this->cancelAction(),
            DeleteAction::make(),
        ];
    }

    private function statusAction(
        string $name,
        string $label,
        string $icon,
        OrderStatus $to,
        string $colour,
    ): Action {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($colour)
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->status->canMoveTo($to))
            ->action(function (Order $record) use ($name, $label): void {
                try {
                    $record->{$name}();
                } catch (Throwable $failure) {
                    // Fulfilling can fail on stock the van does not have. The
                    // order stays where it was; say why rather than 500.
                    Notification::make()
                        ->title("Could not {$label} this order")
                        ->body($failure->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title("Order {$label}")->success()->send();
            });
    }

    private function cancelAction(): Action
    {
        return Action::make('cancelOrder')
            ->label('Cancel order')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')
                    ->label('Why')
                    ->rows(2)
                    ->required(),
            ])
            ->visible(fn (Order $record): bool => $record->status->canMoveTo(OrderStatus::Cancelled))
            ->action(function (Order $record, array $data): void {
                $record->cancel((string) $data['reason']);

                Notification::make()->title('Order cancelled')->success()->send();
            });
    }
}

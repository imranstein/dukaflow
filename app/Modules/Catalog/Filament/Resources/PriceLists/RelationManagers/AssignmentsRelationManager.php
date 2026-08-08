<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\PriceLists\RelationManagers;

use App\Modules\Catalog\Enums\PriceListScope;
use App\Modules\Catalog\Models\PriceListAssignment;
use App\Support\Contracts\ScopeDirectory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * Who a price list applies to.
 *
 * The outlets and routes on offer come from a ScopeDirectory rather than from
 * Distribution's models, because Catalog is not allowed to know that module
 * exists. See App\Support\Contracts\ScopeDirectory.
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Applies to';

    protected static ?string $modelLabel = 'assignment';

    // These tables hold a handful of rows each, so the extra round trip that
    // lazy loading costs buys nothing, and the panel renders in one pass.
    protected static bool $isLazy = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('scope')
                ->label('Attach to')
                ->options(collect(PriceListScope::cases())
                    ->mapWithKeys(fn (PriceListScope $scope): array => [$scope->value => $scope->label()])
                    ->all())
                ->required()
                ->live()
                // The list of things to pick from changes with the kind, so
                // clear a stale selection when the kind changes.
                ->afterStateUpdated(fn (Select $component) => $component
                    ->getContainer()
                    ->getComponent('scope_id')
                    ?->state(null)),

            Select::make('scope_id')
                ->key('scope_id')
                ->label(fn (Get $get): string => match ($get('scope')) {
                    PriceListScope::Route->value => 'Route',
                    default => 'Outlet',
                })
                ->options(fn (Get $get): array => $this->directory()->options((string) $get('scope')))
                ->searchable()
                ->required()
                // The table enforces one assignment per list per target. Say
                // so here, or the second attempt surfaces as a raw database
                // exception rather than a message on the field.
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('price_list_id', $this->getOwnerRecord()->getKey())
                        ->where('scope', (string) $get('scope')),
                )
                ->validationMessages(['unique' => 'This list is already attached to that one.'])
                ->helperText(fn (Get $get): ?string => $this->directory()->handles((string) $get('scope'))
                    ? null
                    : 'Nothing to attach to yet.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('scope')
                    ->label('Kind')
                    ->badge()
                    ->formatStateUsing(fn (PriceListScope $state): string => $state->label()),
                // One directory lookup per row. A price list is attached to a
                // handful of things, so that is fine; batching it would mean
                // adding a bulk method to the contract, which is the price of
                // not being allowed to join across the boundary.
                TextColumn::make('scope_id')
                    ->label('Name')
                    ->formatStateUsing(fn (int $state, PriceListAssignment $record): string => $this->directory()
                        ->label($record->scope->value, $state) ?? "Deleted record #{$state}"),
            ])
            ->emptyStateHeading('Not attached to anyone')
            ->emptyStateDescription('A list attached to nobody still applies if it is the default.')
            ->headerActions([CreateAction::make()])
            ->recordActions([DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    private function directory(): ScopeDirectory
    {
        return app(ScopeDirectory::class);
    }
}

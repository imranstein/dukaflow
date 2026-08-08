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

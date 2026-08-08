<?php

declare(strict_types=1);

namespace App\Modules\Distribution\Filament\Resources\Customers\RelationManagers;

use App\Modules\Distribution\Enums\DayOfWeek;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Which days this outlet is called on, and where it falls in the run.
 */
class VisitSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'visitSchedules';

    protected static ?string $title = 'Visit days';

    protected static ?string $modelLabel = 'visit day';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('day_of_week')
                ->label('Day')
                ->options(DayOfWeek::options())
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule
                    ->where('customer_id', $this->getOwnerRecord()->getKey()))
                ->validationMessages(['unique' => 'This outlet is already called on that day.']),

            TextInput::make('sequence')
                ->label('Position in the run')
                ->numeric()
                ->minValue(0)
                ->default(1)
                ->required()
                ->helperText('Lower numbers are visited earlier in the day.'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('Day')
                    ->badge()
                    ->formatStateUsing(fn (DayOfWeek $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('sequence')
                    ->label('Position')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('day_of_week')
            ->emptyStateHeading('No visit days set')
            ->emptyStateDescription('An outlet with no visit days never appears on a rep\'s round.')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}

<?php

namespace App\Filament\Resources\Meetings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MeetingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(function ($state, $record): ?string {
                        $fullName = trim($record->customer?->first_name.' '.$record->customer?->last_name);

                        return $fullName !== '' ? $fullName : null;
                    })
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('company.name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('marketingUser.name')
                    ->label('Marketing user')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('end_time')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('timezone')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('reason')
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('source')
                    ->badge()
                    ->searchable(),
                TextColumn::make('confirmed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

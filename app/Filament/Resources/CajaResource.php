<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CajaResource\Pages;
use App\Models\Caja;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\View\LegacyComponents\Widget;
use App\Filament\Resources\CajaResource\Widgets\StatsOverview as CajaStatsOverview;
use App\Filament\Resources\CajaResource\RelationManagers;

class CajaResource extends Resource
{
    protected static ?string $model = Caja::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DateTimePicker::make('apertura')
                    ->label('Fecha y Hora de Apertura')
                    ->required(),
                Forms\Components\DateTimePicker::make('cierre')
                    ->label('Fecha y Hora de Cierre')
                    ->required(),
                Forms\Components\TextInput::make('capital_inicial')
                    ->label('Capital Inicial')
                    ->numeric()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('apertura')->label('Apertura'),
                Tables\Columns\TextColumn::make('cierre')->label('Cierre'),
                Tables\Columns\TextColumn::make('capital_inicial')->label('Capital Inicial')->money('COP'),
                Tables\Columns\TextColumn::make('refuerzo')->label('Refuerzo')->money('COP')->default(0),
                Tables\Columns\TextColumn::make('capital_final')->label('Capital Final')->money('COP')->default(0),
            ])
            ->headerActions([
                Tables\Actions\Action::make('refuerzo')
                    ->label('Reforzar Caja')
                    ->action(function (array $data) {
                        // Obtener el último registro de caja
                        $caja = Caja::latest()->first();

                        if ($caja) {
                            // Calcular el capital final y actualizar el registro de caja
                            $refuerzo = $data['refuerzo'];
                            $caja->refuerzo = $refuerzo;
                            $caja->capital_final = $caja->capital_inicial + $refuerzo;

                            // Guardar los cambios en el mismo registro
                            $caja->save();
                        }
                        
                    })
                    ->modalHeading('Refuerzo de Caja')
                    ->form([
                        Forms\Components\TextInput::make('capital_anterior')
                            ->label('Capital Anterior')
                            ->default(fn () => static::getUltimoCapitalInicial())
                            ->disabled(),
                        Forms\Components\TextInput::make('refuerzo')
                            ->label('Monto de Refuerzo')
                            ->numeric()
                            ->required(),
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    // Obtener el capital inicial de la última caja registrada
    public static function getUltimoCapitalInicial()
    {
        $ultimaCaja = Caja::latest()->first();
        return $ultimaCaja ? $ultimaCaja->capital_inicial : 0;
    }

    public static function getRelations(): array
    {
        return [];
    }
    public static function getWidgets(): array
    {
        return [
            CajaStatsOverview::class,
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCajas::route('/'),
            'create' => Pages\CreateCaja::route('/create'),
            'edit' => Pages\EditCaja::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrestamoResource\Pages;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Prestamo;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade as PDF;
use Filament\Forms\Components\Actions;
use Filament\Notifications\Notification;

class PrestamoResource extends Resource
{
    protected static ?string $model = Prestamo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';




    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('cliente_id')
                    ->label('Cliente')
                    ->options(
                        Cliente::all()->mapWithKeys(function ($cliente) {
                            return [$cliente->id => $cliente->full_name];
                        })
                    )
                    ->searchable()
                    ->required(),

                TextInput::make('tasa_interes')
                    ->label('Tasa de Interés (%)')
                    ->numeric()
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        $monto = $get('monto') ?? 0;
                        $tasaInteres = $state ?? 0;
                        $numeroCuotas = $get('numero_cuotas') ?? 1; // Para evitar división por cero

                        $interesesGenerados = round(($monto * $tasaInteres) / 100, 2);
                        $montoTotal = round($monto + $interesesGenerados, 2);
                        $valorCuota = round($montoTotal / $numeroCuotas, 2);


                        $set('intereses_generados', $interesesGenerados);
                        $set('monto_total', $montoTotal);
                        $set('valor_cuota', $valorCuota);
                    }),

                TextInput::make('numero_cuotas')
                    ->label('Número de Cuotas')
                    ->numeric()
                    ->required(),

                Select::make('tipo_cuota')
                    ->label('Tipo de Cuota')
                    ->options([
                        'anual' => 'Anual',
                        'semestral' => 'Semestral',
                        'mensual' => 'Mensual',
                        'quincenal' => 'Quincenal',
                        'diario' => 'Diario',
                    ])
                    ->required(),

                    
                    TextInput::make('monto')
                    ->label('Monto Prestado')
                    ->numeric()
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        $caja = Caja::latest()->first();
                        
                        // Verificar el capital disponible usando capital_final o, si es cero, capital_inicial
                        $capitalDisponible = $caja && $caja->capital_final > 0 ? $caja->capital_final : $caja->capital_inicial;
                
                        if ($caja && $state > $capitalDisponible) {
                            // Envía la notificación sin poner el campo en null
                            Notification::make()
                                ->title('Capital insuficiente')
                                ->body('El monto del préstamo excede el capital actual de la caja, por favor refuerce la caja.')
                                ->warning()
                                ->send();
                                
                            // Devolver para evitar el cálculo adicional
                            return;
                        } else {
                            // Continua con la lógica de cálculo de intereses si el capital es suficiente
                            $tasaInteres = $get('tasa_interes') ?? 0;
                            $numeroCuotas = $get('numero_cuotas') ?? 1;
                            $interesesGenerados = round(($state * $tasaInteres) / 100, 2);
                            $montoTotal = round($state + $interesesGenerados, 2);
                            $valorCuota = round($montoTotal / $numeroCuotas, 2);
                
                            $set('intereses_generados', $interesesGenerados);
                            $set('monto_total', $montoTotal);
                            $set('valor_cuota', $valorCuota);
                        }
                    }),
                
                TextInput::make('intereses_generados')
                    ->label('Intereses Generados')
                    ->numeric()
                    ->disabled()
                    ->default(0)
                    ->reactive(),

                TextInput::make('monto_total')
                    ->label('Monto Total a Pagar')
                    ->numeric()
                    ->disabled()
                    ->default(0)
                    ->reactive(),

                TextInput::make('valor_cuota')
                    ->label('Valor Cuotas')
                    ->numeric()
                    ->disabled()
                    ->default(0)
                    ->reactive(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('cliente.numero_documento')->label('CC')->searchable(),
            Tables\Columns\TextColumn::make('cliente.nombres')->label('Nombres')->searchable(),
            Tables\Columns\TextColumn::make('cliente.apellidos')->label('Apellidos')->searchable(),
            Tables\Columns\TextColumn::make('monto')->label('Monto'),
            Tables\Columns\TextColumn::make('pdf')->label('Pagaré')
                ->url(fn($record) => $record->pdf ? Storage::url($record->pdf) : null) // Mostrar el enlace si hay pdf
                ->openUrlInNewTab() // Abrir en una nueva pestaña
                ->default('No hay pdf'), // Mostrar mensaje si no hay pdf
            Tables\Columns\TextColumn::make('estado')->label('Estado')->searchable(),

        ])->headerActions([
            Tables\Actions\Action::make('exportPdf')
                ->label('Exportar lista a PDF')
                ->url(route('exportar.prestamo.pdf')) // Llama a la ruta que exporta la lista
                ->openUrlInNewTab(true) // Abre el PDF en una nueva pestaña
                ->color('success'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrestamos::route('/'),
            'create' => Pages\CreatePrestamo::route('/create'),
            'edit' => Pages\EditPrestamo::route('/{record}/edit'),
        ];
    }

    public static function generarCuotas($monto, $tasa_interes, $numero_cuotas, $tipo_cuota)
    {
        $cuotas = [];
        $fechaInicial = Carbon::now();

        for ($i = 1; $i <= $numero_cuotas; $i++) {
            // Ajustar la fecha de vencimiento según el tipo de cuota
            switch ($tipo_cuota) {
                case 'anual':
                    $fechaVencimiento = $fechaInicial->copy()->addYears($i);
                    break;
                case 'semestral':
                    $fechaVencimiento = $fechaInicial->copy()->addMonths($i * 6);
                    break;
                case 'mensual':
                    $fechaVencimiento = $fechaInicial->copy()->addMonths($i);
                    break;
                case 'quincenal':
                    $fechaVencimiento = $fechaInicial->copy()->addWeeks($i * 2);
                    break;
                case 'diario':
                    $fechaVencimiento = $fechaInicial->copy()->addDays($i);
                    break;
                default:
                    throw new \InvalidArgumentException("Tipo de cuota no válido: $tipo_cuota");
            }

            // Cálculo del capital e interés
            $capital = $monto / $numero_cuotas;
            $interes = ($capital * $tasa_interes) / 100;
            $total = $capital + $interes;

            // Agregar la cuota al arreglo
            $cuotas[] = [
                'numero_cuota' => $i,
                'fecha_vencimiento' => $fechaVencimiento->toDateString(),
                'capital' => $capital,
                'interes' => $interes,
                'total' => $total,
            ];
        }

        return $cuotas;
    }
    public static function afterSave($record)
{
    $caja = Caja::latest()->first();

    if ($caja) {
        $caja->capital_final -= $record->monto;
        $caja->save();
    }
}

}

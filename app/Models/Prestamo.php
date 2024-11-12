<?php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'tasa_interes',
        'numero_cuotas',
        'tipo_cuota',
        'monto',
        'intereses_generados',
        'monto_total',
        'valor_cuota',
        'pdf',
        'estado',

    ];

    // Relación con Cliente
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    // Método para calcular los intereses generados y el monto total
    public static function calcularInteresesYMontos($monto, $tasa_interes)
    {
        $intereses_generados = ($monto * $tasa_interes) / 100;
        $monto_total = $monto + $intereses_generados;
        return [
            'intereses_generados' => $intereses_generados,
            'monto_total' => $monto_total,
        ];
    }


    public function calcularMontoCuota(int $numeroCuota)
{
    // Verificar que el número de cuota solicitado esté dentro del rango de cuotas pendientes
    if ($numeroCuota > $this->numero_cuotas || $numeroCuota <= 0) {
        return null; // Retorna null si el número de cuota es inválido
    }

    // Dividir el monto total entre el número de cuotas para obtener el valor de cada cuota
    $montoCuota = $this->monto_total / $this->numero_cuotas;

    // Aquí podrías hacer ajustes si tienes lógica adicional para calcular intereses o cargos por cuota.
    // En este ejemplo, estamos suponiendo que el valor es igual para cada cuota.

    return $montoCuota;
}
public function montoRestante()
{
    // Obtenemos la suma de todos los pagos realizados para este préstamo
    $pagosRealizados = $this->pagos()->sum('monto_abonado');
    
    // Calculamos el monto restante
    $montoRestante = $this->monto_total - $pagosRealizados;
    
    // Si el monto restante es 0 o menor, cambiamos el estado
    if ($montoRestante <= 0) {
        $this->update(['estado' => 'completado']);
    }

    return max($montoRestante, 0); // Asegura que el monto restante no sea negativo
}


//relacion de cuotas con prestamos

public function cuotas()
{
    return $this->hasMany(Cuota::class);
}

// Método afterSave o afterUpdate para verificar el estado
public static function afterSave($record)
{
    // Si el monto restante es 0, actualizamos el estado del préstamo
    if ($record->montoRestante() <= 0) {
        $record->estado = 'completado'; // O 'pagado'
        $record->save();
    }
}


    // Relación con el modelo Pago
    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }


    public function getCuotasPendientes()
    {
        // Obtén todas las cuotas del préstamo
        $cuotasTotales = range(1, $this->numero_cuotas);

        // Filtra las cuotas pagadas y elimina esas del total
        $cuotasPagadas = $this->pagos->pluck('numero_cuota')->toArray();

        // Devuelve solo las cuotas que aún están pendientes
        return array_diff($cuotasTotales, $cuotasPagadas);
    } 



    // Método para generar cuotas y calcular el valor de cada una
    public function generarCuotas()
    {
        $capital = $this->monto / $this->numero_cuotas;
        $interes = ($capital * $this->tasa_interes) / 100;
        $total = $capital + $interes;

        // Guardar el valor de cada cuota en el campo valor_cuota
        $this->valor_cuota = $total;
        $this->save();

        $cuotas = [];
        for ($i = 1; $i <= $this->numero_cuotas; $i++) {
            $fechaVencimiento = Carbon::now()->addMonths($i);
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


    // Obtener saldo pendiente basado en los pagos realizados
    public function getSaldoPendienteAttribute()
    {
        return $this->monto_total - $this->pagos->sum('monto_abonado');
    }

    // Obtener valor de cuota (ajusta según tu estructura)
    public function getValorCuota()
    {
        return $this->monto / $this->numero_cuotas;
    }

    
}

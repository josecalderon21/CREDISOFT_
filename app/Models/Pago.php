<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $fillable = [
        'prestamo_id',
        'cuota_id',
        'cliente_id',
        'monto_abonado',
        'tipo_pago',
        'modalidad_pago',
        'numero_comprobante',
        'saldo_pendiente',
    ];



// Pago.php
public function cliente()
{
    return $this->belongsTo(Cliente::class);
}

// Pago.php

public function cuota()
{
    return $this->belongsTo(Cuota::class, 'cuota_id'); // Ajusta el nombre de la columna si es diferente
}

// Pago.php

public function prestamo()
{
    return $this->belongsTo(Prestamo::class);
}

protected static function booted(){
    static::created(function ($pago) {
        $prestamo = Prestamo::find($pago->prestamo_id);

        // Verificar que se ha seleccionado una cuota y que el tipo de pago no sea total
        if ($pago->tipo_pago === 'cuota' && $pago->cuota) {
            if ($pago->monto_abonado >= $pago->cuota->total) {
                // Si el monto abonado es suficiente para cubrir la cuota completa
                $pago->cuota->update(['estado' => 'pagada']);
            }
        } elseif ($pago->tipo_pago === 'total') {
            // Cambiar el estado de todas las cuotas pendientes a "pagada" si es un pago total
            if ($prestamo) {
                Cuota::where('prestamo_id', $prestamo->id)
                     ->where('estado', 'pendiente')
                     ->update(['estado' => 'pagada']);
            }
        } elseif ($pago->tipo_pago === 'otro') {
            // Si se selecciona pagar "Otro Valor"
            if ($pago->cuota) {
                $saldoCuota = $pago->cuota->total - $pago->cuota->pagos()->sum('monto_abonado');
                if ($pago->monto_abonado >= $saldoCuota) {
                    // Marcar la cuota como pagada si el monto abonado cubre el saldo pendiente de la cuota
                    $pago->cuota->update(['estado' => 'pagada']);
                }
            }

            // Si el pago cubre la deuda total pendiente, marcar todas las cuotas como pagadas
            $saldoPrestamo = $prestamo->monto_total - $prestamo->pagos()->sum('monto_abonado');
            if ($pago->monto_abonado >= $saldoPrestamo) {
                Cuota::where('prestamo_id', $prestamo->id)
                     ->where('estado', 'pendiente')
                     ->update(['estado' => 'pagada']);
            }
        }
    });
}

}



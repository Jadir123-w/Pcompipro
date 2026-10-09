<?php

namespace Modules\Finance\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;

class SmallCash extends ModelTenant
{
  protected $table = 'small_cash';

  protected $fillable = [
    'user_id',
    'code',
    'date_opening',
    'date_closed',
    'beginning_balance',
    'final_balance',
    'income',
    'expense',
    'state',
    'reference_number',
    'description',
  ];

  protected $casts = [
    'date_opening' => 'date',
    'date_closed' => 'date',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function transactions()
  {
    return $this->hasMany(SmallCashTransaction::class);
  }

  /*
  |--------------------------------------------------------------------------
  | Datos para el encabezado del reporte de rendición
  |--------------------------------------------------------------------------
  */

  /**
   * Área a la que pertenece la caja. Se toma del tipo de usuario que la abrió,
   * que es el criterio que ya usa el listado (Administrador, Comercial,
   * Logística, Finanzas...).
   *
   * Se parte de User::TYPE_USER para no duplicar el catálogo y solo se corrige
   * la tilde de "Logistica", que en el reporte debe leerse "Logística".
   *
   * @return string
   */
  public function getAreaAttribute()
  {
    if (!$this->user || empty($this->user->type)) {
      return '';
    }

    $tipo = $this->user->type;

    if ($tipo === 'logistic') {
      return 'Logística';
    }

    return isset(User::TYPE_USER[$tipo]) ? User::TYPE_USER[$tipo] : ucfirst($tipo);
  }

  /**
   * Mes de apertura en palabras (ENERO, FEBRERO, ...), para el título y el
   * bloque PERIODO del reporte.
   *
   * @return string
   */
  public function getPeriodoAttribute()
  {
    if (empty($this->date_opening)) {
      return '';
    }

    $meses = [
      1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
      5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
      9 => 'SETIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
    ];

    $mes = (int) \Carbon\Carbon::parse($this->date_opening)->format('n');

    return isset($meses[$mes]) ? $meses[$mes] : '';
  }

  /**
   * Título del reporte: "RENDICIÓN DE CAJA CHICA LOGÍSTICA - ENERO 2026".
   * Si la caja no tuviera área, se omite para no dejar un guion suelto.
   *
   * @return string
   */
  public function getTituloRendicionAttribute()
  {
    $area = trim($this->area);
    $anio = $this->date_opening ? \Carbon\Carbon::parse($this->date_opening)->format('Y') : '';

    $titulo = 'RENDICIÓN DE CAJA CHICA';

    if ($area !== '') {
      $titulo .= ' ' . mb_strtoupper($area, 'UTF-8');
    }

    return trim($titulo . ' - ' . trim($this->periodo . ' ' . $anio), ' -');
  }

  /**
   * Identificación bajo el título: "(Logística - CC-000002)".
   *
   * @return string
   */
  public function getIdentificacionRendicionAttribute()
  {
    $area = trim($this->area);

    return $area !== ''
      ? '(' . $area . ' - ' . $this->code . ')'
      : '(' . $this->code . ')';
  }

  /**
   * Generar código único para caja chica
   */
  public static function generateCode()
  {
    $last = self::orderBy('id', 'desc')->first();
    $number = $last ? intval(substr($last->code, 3)) + 1 : 1;
    return 'CC-' . str_pad($number, 6, '0', STR_PAD_LEFT);
  }

  /**
   * Calcular balance actual
   */
  public function calculateBalance()
  {
    return $this->beginning_balance + $this->income - $this->expense;
  }

  /**
   * Actualizar totales
   */
  public function updateTotals()
  {
    $this->income = $this->transactions()->where('type', 'income')->sum('amount');
    $this->expense = $this->transactions()->where('type', 'expense')->sum('amount');
    $this->final_balance = $this->calculateBalance();
    $this->save();
  }

  /**
   * Scope para cajas abiertas
   */
  public function scopeOpen($query)
  {
    return $query->where('state', 'open');
  }

  /**
   * Scope para cajas cerradas
   */
  public function scopeClosed($query)
  {
    return $query->where('state', 'closed');
  }

  /**
   * Scope para usuario
   */
  public function scopeByUser($query, $userId)
  {
    return $query->where('user_id', $userId);
  }
}

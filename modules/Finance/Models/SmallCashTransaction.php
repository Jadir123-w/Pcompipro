<?php

namespace Modules\Finance\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\Person;
use App\Models\Tenant\User;
use App\Models\Tenant\PaymentMethodType;

class SmallCashTransaction extends ModelTenant
{
  protected $table = 'small_cash_transactions';

  protected $fillable = [
    'small_cash_id',
    'user_id',
    'date',
    'type',
    'payment_method_type_id',
    'amount',
    'description',
    'document_number',
    'supplier_customer',
    'supplier_id',
    'category',
    'ruta',
    'proyecto',
    'responsable',
  ];

  protected $casts = [
    'date' => 'date',
  ];

  /**
   * Campos descriptivos que se guardan con la primera letra de cada palabra en
   * mayúscula, sin importar cómo los haya escrito el usuario.
   *
   * No se incluye supplier_customer a propósito: es una razón social (muchas
   * veces tomada del maestro de proveedores) y alterarla desvirtuaría el nombre
   * legal de la empresa.
   */
  private static $camposTitleCase = [
    'description',
    'responsable',
    'proyecto',
    'ruta',
    'category',
  ];

  public function smallCash()
  {
    return $this->belongsTo(SmallCash::class);
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function paymentMethodType()
  {
    return $this->belongsTo(PaymentMethodType::class);
  }

  /**
   * Proveedor del maestro de personas. Es opcional: los gastos de movilidad,
   * taxis, pasajes o ferias se registran sin proveedor.
   */
  public function supplier()
  {
    return $this->belongsTo(Person::class, 'supplier_id');
  }

  /*
  |--------------------------------------------------------------------------
  | Normalización de textos
  |--------------------------------------------------------------------------
  */

  /**
   * Normaliza un texto a "Title Case": recorta espacios sobrantes y pone en
   * mayúscula la primera letra de cada palabra. Ejemplos:
   *   "pago de taxi"  -> "Pago De Taxi"
   *   "PAGO DE TAXI"  -> "Pago De Taxi"
   *
   * Se usa mb_convert_case (no ucwords) para respetar tildes y eñes.
   *
   * @param  mixed  $valor
   * @return string|null
   */
  public static function normalizarTexto($valor)
  {
    if ($valor === null) {
      return null;
    }

    // Colapsa espacios repetidos y recorta los de los extremos.
    $texto = trim(preg_replace('/\s+/u', ' ', (string) $valor));

    if ($texto === '') {
      return null;
    }

    return mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');
  }

  /**
   * Aplica la normalización al asignar cualquiera de los campos descriptivos.
   * Al hacerlo en el modelo, vale para el alta y para la edición, sin importar
   * desde dónde se guarde.
   *
   * @param  string  $key
   * @param  mixed   $value
   * @return mixed
   */
  public function setAttribute($key, $value)
  {
    if (in_array($key, self::$camposTitleCase, true)) {
      $value = self::normalizarTexto($value);
    }

    return parent::setAttribute($key, $value);
  }

  /**
   * Boot del modelo
   */
  protected static function boot()
  {
    parent::boot();

    static::created(function ($transaction) {
      $transaction->smallCash->updateTotals();
    });

    static::updated(function ($transaction) {
      $transaction->smallCash->updateTotals();
    });

    static::deleted(function ($transaction) {
      $transaction->smallCash->updateTotals();
    });
  }

  /**
   * Scope por tipo
   */
  public function scopeByType($query, $type)
  {
    return $query->where('type', $type);
  }
}

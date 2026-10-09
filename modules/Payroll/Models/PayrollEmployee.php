<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payroll\Traits\PayrollAuditable;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class PayrollEmployee extends ModelTenant
{
    use SoftDeletes, PayrollAuditable;

    protected $table = 'payroll_employees';

    protected $fillable = [
        'user_id',
        'codigo_empleado',
        'dni',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'direccion',
        'telefono',
        'email',
        'fecha_nacimiento',
        'fecha_ingreso',
        'fecha_cese',
        'tipo_regimen',
        'afp_id',
        'tipo_comision_afp',
        'cuspp',
        'sueldo_basico',
        'asignacion_familiar',
        'movilidad',
        'cargo',
        'area',
        'activo',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_cese' => 'date',
        'sueldo_basico' => 'decimal:2',
        'movilidad' => 'decimal:2',
        'asignacion_familiar' => 'boolean',
    ];

    protected $appends = ['nombres_completos'];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function afp()
    {
        return $this->belongsTo(PayrollAfp::class);
    }

    public function planillaDetalles()
    {
        return $this->hasMany(PayrollDetail::class, 'empleado_id');
    }

    public function payrolls()
    {
        return $this->belongsToMany(Payroll::class, 'payroll_detail', 'empleado_id', 'planilla_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function eliminador()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Accessors
    public function getNombresCompletosAttribute()
    {
        return $this->with(['user' => function ($query) {
            $query->select([
                DB::raw('CONCAT(COALESCE(name, ""), " ", COALESCE(paternal_lastname, ""), " " ,COALESCE(maternal_lastname, "")) as name')
            ]);
        }])->first()->name;
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true)->whereNull('fecha_cese');
    }

    public function scopePorAfp($query)
    {
        return $query->where('tipo_regimen', 'AFP');
    }

    public function scopePorOnp($query)
    {
        return $query->where('tipo_regimen', 'ONP');
    }

    // Métodos auxiliares
    public function calcularMontoAsignacionFamiliar()
    {
        if (!$this->asignacion_familiar) {
            return 0;
        }

        $rmv = PayrollConfiguration::obtenerValor('rmv');
        $porcentaje = PayrollConfiguration::obtenerValor('asignacion_familiar_porcentaje');
        
        return ($rmv * $porcentaje) / 100;
    }

    public function calcularEdad()
    {
        if (!$this->fecha_nacimiento) {
            return null;
        }
        
        return $this->fecha_nacimiento->age;
    }

    public function calcularAntiguedad()
    {
        if (!$this->fecha_ingreso) {
            return null;
        }
        
        $fechaFin = $this->fecha_cese ?? now();
        return $this->fecha_ingreso->diffInYears($fechaFin);
    }

    public function getCollectionData()
    {
        $user = User::query()->select([
            'id', 
            DB::raw('CONCAT(COALESCE(name, ""), " ", COALESCE(paternal_lastname, ""), " " ,COALESCE(maternal_lastname, "")) as name'), 
            'email', 'position', 'number'
        ])->where('id', $this->user_id)->first();

        $afp = PayrollAfp::query()->select([
            'id', 'nombre'
        ])->where('id', $this->afp_id)->first();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $user,
            'afp' => $afp,
            'codigo_empleado' => $this->codigo_empleado,
            'fecha_cese' => $this->fecha_cese,
            'tipo_regimen' => $this->tipo_regimen,
            'afp_id' => $this->afp_id,
            'tipo_comision_afp' => $this->tipo_comision_afp,
            'cuspp' => $this->cuspp,
            'sueldo_basico' => $this->sueldo_basico,
            'asignacion_familiar' => $this->asignacion_familiar,
            'movilidad' => $this->movilidad,
            'activo' => $this->activo,
        ];
    }
}
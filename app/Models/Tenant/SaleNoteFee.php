<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\CurrencyType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleNoteFee extends ModelTenant
{

    protected $fillable = [
        "sale_note_id",
        "date",
        "currency_type_id",
        "amount",
        "payment_method_type_id",
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
    ];

    public $timestamps = false;
    
    /**
     * @return mixed
     */
    public function getPaymentMethodTypeId() {
        return $this->payment_method_type_id;
    }

    /**
     * @param mixed $payment_method_type_id
     *
     * @return DocumentFee
     */
    public function setPaymentMethodTypeId($payment_method_type_id) {
        $this->payment_method_type_id = $payment_method_type_id;
        return $this;
    }

    public function sale_note(): BelongsTo 
    {
        return $this->belongsTo(SaleNote::class, 'sale_note_id');
    }

    public function currency_type()
    {
        return $this->belongsTo(CurrencyType::class, 'currency_type_id');
    }

    public function payment_method_type()
    {
        return $this->belongsTo(PaymentMethodType::class, 'payment_method_type_id');
    }
    /**
     * Devuelve el nombre del metodo de pago
     *
     * @return string
     */
    public function getStringPaymentMethodType(){

        $payment_method_type = PaymentMethodType::where('id',$this->payment_method_type_id)->first();
        $return = null;
        if(!empty($payment_method_type)){
            $return =  $payment_method_type->getDescription();
        }
        return $return;
    }

}


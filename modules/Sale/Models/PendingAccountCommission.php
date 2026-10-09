<?php

namespace Modules\Sale\Models;

use App\Models\Tenant\User;
use App\Models\Tenant\ModelTenant;

class PendingAccountCommission extends ModelTenant
{
    protected $fillable = [
        'id',
        'seller_id',
        'amount',
        'commission_type',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWhereTypeSeller($query)
    {
        $user = auth()->user();
        return ($user->type == 'seller') ? $query->where('seller_id', $user->id) : $query;
    }
}
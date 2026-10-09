<?php

namespace Modules\Hotel\Models;

use App\Models\Tenant\ModelTenant;

class HotelRentOrder extends ModelTenant
{
    protected $table = 'hotel_rent_orders';

	protected $fillable = [
		'hotel_rent_id',
		'order_number',
		'order_status',
		'sale_note_id',
		'payment_status',
		'establishment_id'
	];
	

}

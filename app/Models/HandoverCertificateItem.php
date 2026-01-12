<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandoverCertificateItem extends Model
{
    protected $fillable = [
        'handover_certificate_id',
        'procurement_item_id',
        'item_name_spec',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
    ];

    public function handoverCertificate()
    {
        return $this->belongsTo(HandoverCertificate::class);
    }

    public function procurementItem()
    {
        return $this->belongsTo(ProcurementItem::class);
    }
}

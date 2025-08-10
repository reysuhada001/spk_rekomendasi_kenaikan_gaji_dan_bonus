<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiUmumRealizationItem extends Model
{
    protected $fillable = [
        'realization_id','kpi_umum_id','tipe','satuan','target','realisasi','score'
    ];

    public function realization() { return $this->belongsTo(KpiUmumRealization::class, 'realization_id'); }
    public function kpi()         { return $this->belongsTo(KpiUmum::class, 'kpi_umum_id'); }
}

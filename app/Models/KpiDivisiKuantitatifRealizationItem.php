<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisiKuantitatifRealizationItem extends Model
{
    protected $table = 'kpi_divisi_kuantitatif_realization_items';

    protected $fillable = [
        'realization_id','kpi_divisi_id','target','realization','score'
    ];

    public function realization()
    {
        return $this->belongsTo(KpiDivisiKuantitatifRealization::class, 'realization_id');
    }

    public function kpi()
    {
        return $this->belongsTo(KpiDivisi::class, 'kpi_divisi_id');
    }
}

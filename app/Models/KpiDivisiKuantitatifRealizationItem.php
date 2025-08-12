<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisiKuantitatifRealizationItem extends Model
{
    // Izinkan mass assignment termasuk user_id
    protected $fillable = [
        'realization_id',
        'user_id',
        'kpi_divisi_id',
        'target',
        'realization',
        'score',
    ];

    // Relasi opsional
    public function realization()
    {
        return $this->belongsTo(KpiDivisiKuantitatifRealization::class, 'realization_id');
    }

    public function kpi()
    {
        return $this->belongsTo(KpiDivisi::class, 'kpi_divisi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

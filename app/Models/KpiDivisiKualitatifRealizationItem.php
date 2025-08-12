<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisiKualitatifRealizationItem extends Model
{
    protected $fillable = [
        'realization_id',
        'user_id',
        'kpi_divisi_id',
        'target',
        'realization',
        'score',
    ];

    public function realization()
    {
        return $this->belongsTo(KpiDivisiKualitatifRealization::class, 'realization_id');
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

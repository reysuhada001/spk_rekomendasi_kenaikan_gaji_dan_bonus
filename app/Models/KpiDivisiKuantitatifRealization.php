<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisiKuantitatifRealization extends Model
{
    protected $table = 'kpi_divisi_kuantitatif_realizations';

    protected $fillable = [
        'user_id','division_id','bulan','tahun',
        'total_score','status','hr_note','created_by','updated_by'
    ];

    public function items()
    {
        return $this->hasMany(KpiDivisiKuantitatifRealizationItem::class, 'realization_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}

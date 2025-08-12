<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisiPersentaseRealization extends Model
{
    protected $fillable = [
        'kpi_divisi_id','division_id','bulan','tahun',
        'target','realization','score',
        'status','hr_note','created_by','updated_by'
    ];

    public function kpi()      { return $this->belongsTo(KpiDivisi::class, 'kpi_divisi_id'); }
    public function division() { return $this->belongsTo(Division::class); }
}

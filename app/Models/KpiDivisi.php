<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisi extends Model
{
    protected $table = 'kpi_divisi';

    protected $fillable = [
        'division_id','nama','tipe','satuan','target','bobot','bulan','tahun'
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'bobot'  => 'decimal:8',
        'bulan'  => 'integer',
        'tahun'  => 'integer',
    ];
    
    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}

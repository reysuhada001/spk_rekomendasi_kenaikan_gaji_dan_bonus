<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiUmum extends Model
{
    protected $table = 'kpi_umum';

    protected $fillable = [
        'nama','tipe','satuan','target','bobot','bulan','tahun'
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'bobot'  => 'decimal:8',
        'bulan'  => 'integer',
        'tahun'  => 'integer',
    ];
}
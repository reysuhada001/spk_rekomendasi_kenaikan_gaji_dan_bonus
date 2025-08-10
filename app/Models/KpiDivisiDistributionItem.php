<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisiDistributionItem extends Model
{
    protected $fillable = [
        'distribution_id','kpi_divisi_id','user_id','target'
    ];

    protected $casts = [
        'target' => 'decimal:2',
    ];

    public function distribution()
    {
        return $this->belongsTo(KpiDivisiDistribution::class, 'distribution_id');
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

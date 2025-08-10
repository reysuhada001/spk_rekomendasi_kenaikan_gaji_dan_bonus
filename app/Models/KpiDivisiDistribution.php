<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisiDistribution extends Model
{
    protected $fillable = [
        'division_id','bulan','tahun','status','hr_note','created_by'
    ];

    protected $casts = [
        'bulan' => 'integer',
        'tahun' => 'integer',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function items()
    {
        return $this->hasMany(KpiDivisiDistributionItem::class, 'distribution_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

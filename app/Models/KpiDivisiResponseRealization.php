<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiDivisiResponseRealization extends Model
{
    protected $fillable = [
        'user_id','division_id','bulan','tahun','status','hr_note',
        'total_score','created_by','updated_by'
    ];

    public function user()     { return $this->belongsTo(User::class); }
    public function division() { return $this->belongsTo(Division::class); }
    public function items()    { return $this->hasMany(KpiDivisiResponseRealizationItem::class, 'realization_id'); }
}

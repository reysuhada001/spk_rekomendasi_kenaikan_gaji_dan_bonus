<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiUmumRealization extends Model
{
    protected $fillable = [
        'user_id','division_id','bulan','tahun','total_score','status','hr_note'
    ];

    protected $casts = ['total_score' => 'float'];

    public function user()      { return $this->belongsTo(User::class); }
    public function division()  { return $this->belongsTo(Division::class); }
    public function items()     { return $this->hasMany(KpiUmumRealizationItem::class, 'realization_id'); }
}

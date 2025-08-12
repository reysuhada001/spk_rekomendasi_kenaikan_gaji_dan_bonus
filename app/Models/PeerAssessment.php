<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeerAssessment extends Model
{
    protected $fillable = [
        'assessor_id','assessee_id','division_id','bulan','tahun','submitted_at'
    ];

    public function assessor(): BelongsTo { return $this->belongsTo(User::class,'assessor_id'); }
    public function assessee(): BelongsTo { return $this->belongsTo(User::class,'assessee_id'); }
    public function division(): BelongsTo { return $this->belongsTo(Division::class); }
    public function items(): HasMany { return $this->hasMany(PeerAssessmentItem::class,'assessment_id'); }
}

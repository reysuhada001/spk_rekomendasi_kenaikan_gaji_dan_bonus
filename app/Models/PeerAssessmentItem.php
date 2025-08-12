<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeerAssessmentItem extends Model
{
    protected $fillable = ['assessment_id','aspek_id','score'];

    public function assessment(): BelongsTo { return $this->belongsTo(PeerAssessment::class,'assessment_id'); }
    public function aspek(): BelongsTo { return $this->belongsTo(Aspek::class,'aspek_id'); }
}

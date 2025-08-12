<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AhpGlobalWeight extends Model
{
    protected $table = 'ahp_global_weights';

    protected $fillable = [
        'w_kpi_umum','w_kpi_divisi','w_peer',
        'lambda_max','ci','cr',
    ];
}

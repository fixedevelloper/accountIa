<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeneralLedger extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','account_id','period_start','period_end'];

    protected $casts = ['period_start' => 'date','period_end' => 'date'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function account() {
        return $this->belongsTo(Account::class);
    }
}

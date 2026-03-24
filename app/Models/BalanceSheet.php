<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BalanceSheet extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','generated_at'];

    protected $casts = ['generated_at' => 'date'];

    public function company() {
        return $this->belongsTo(Company::class);
    }
}

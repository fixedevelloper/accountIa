<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TrialBalance extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','generated_for'];

    protected $casts = ['generated_for' => 'date'];

    public function company() {
        return $this->belongsTo(Company::class);
    }
}

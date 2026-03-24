<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tax extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','name','rate'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function taxLines() {
        return $this->hasMany(TaxLine::class);
    }
}

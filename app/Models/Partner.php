<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','name','type','tax_number'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function documents() {
        return $this->hasMany(Document::class);
    }

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }
}

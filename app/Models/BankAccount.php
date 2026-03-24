<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','bank_name','iban'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function transactions() {
        return $this->hasMany(BankTransaction::class);
    }
}

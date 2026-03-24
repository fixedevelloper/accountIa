<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['bank_account_id','transaction_date','description','amount'];

    public function bankAccount() {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciliations() {
        return $this->hasMany(BankReconciliation::class,'transaction_id');
    }
}

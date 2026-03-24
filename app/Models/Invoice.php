<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','partner_id','invoice_number','invoice_date',
        'due_date','total','status'
    ];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function partner() {
        return $this->belongsTo(Partner::class);
    }

    public function lines() {
        return $this->hasMany(InvoiceLine::class);
    }
}

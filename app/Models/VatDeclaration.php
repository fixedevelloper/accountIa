<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VatDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','period_start','period_end','vat_collected','vat_deductible','vat_payable'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'vat_collected' => 'decimal:2',
        'vat_deductible' => 'decimal:2',
        'vat_payable' => 'decimal:2',
    ];

    public function company() {
        return $this->belongsTo(Company::class);
    }
}

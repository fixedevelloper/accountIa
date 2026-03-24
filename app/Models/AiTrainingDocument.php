<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiTrainingDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','document_id','expected_account','validated'
    ];

    protected $casts = ['validated' => 'boolean'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function document() {
        return $this->belongsTo(Document::class);
    }
}

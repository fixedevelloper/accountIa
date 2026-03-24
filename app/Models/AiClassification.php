<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'predicted_account',
        'confidence'
    ];

    public function document() {
        return $this->belongsTo(Document::class);
    }
}

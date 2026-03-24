<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentVersion extends Model
{
    use HasFactory;

    protected $fillable = ['document_id','file_path'];

    public function document() {
        return $this->belongsTo(Document::class);
    }
}

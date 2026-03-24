<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','partner_id','type','file_path','status'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function partner() {
        return $this->belongsTo(Partner::class);
    }

    public function versions() {
        return $this->hasMany(DocumentVersion::class);
    }

    public function extractions() {
        return $this->hasMany(DocumentExtraction::class);
    }

    public function aiClassifications() {
        return $this->hasMany(AiClassification::class);
    }
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }
    public function getLatestExtractionAttribute()
    {
        return $this->extractions()
            ->latest('created_at')
            ->first();
    }
}

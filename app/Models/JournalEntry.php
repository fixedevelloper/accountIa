<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','document_id','journal_id','reference','entry_date','status'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function journal() {
        return $this->belongsTo(Journal::class);
    }

    public function lines() {
        return $this->hasMany(JournalEntryLine::class,'entry_id');
    }
    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}

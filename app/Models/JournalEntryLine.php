<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = ['entry_id','account_id','debit','credit','description'];

    public function entry() {
        return $this->belongsTo(JournalEntry::class,'entry_id');
    }

    public function account() {
        return $this->belongsTo(Account::class);
    }

    public function taxLines() {
        return $this->hasMany(TaxLine::class,'entry_line_id');
    }
}

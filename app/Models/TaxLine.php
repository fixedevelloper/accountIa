<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxLine extends Model
{
    use HasFactory;

    protected $fillable = ['entry_line_id','tax_id','amount'];

    public function entryLine() {
        return $this->belongsTo(JournalEntryLine::class,'entry_line_id');
    }

    public function tax() {
        return $this->belongsTo(Tax::class);
    }
}

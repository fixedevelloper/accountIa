<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','code','name','type'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function journalLines() {
        return $this->hasMany(JournalEntryLine::class);
    }
}

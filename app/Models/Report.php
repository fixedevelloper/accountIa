<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $fillable = ['company_id','type','generated_at'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function files() {
        return $this->hasMany(ReportFile::class);
    }
}

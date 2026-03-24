<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReportFile extends Model
{
    use HasFactory;

    protected $fillable = ['report_id','file_path'];

    public function report() {
        return $this->belongsTo(Report::class);
    }
}

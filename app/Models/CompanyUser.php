<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyUser extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['company_id','user_id','role'];

    public function company() {
        return $this->belongsTo(Company::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}

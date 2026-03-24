<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name','country','currency'];

    public function users() {
        return $this->belongsToMany(User::class, 'company_users')->withPivot('role');
    }

    public function accounts() {
        return $this->hasMany(Account::class);
    }

    public function journals() {
        return $this->hasMany(Journal::class);
    }

    public function documents() {
        return $this->hasMany(Document::class);
    }

    public function partners() {
        return $this->hasMany(Partner::class);
    }

    public function bankAccounts() {
        return $this->hasMany(BankAccount::class);
    }

    public function invoices() {
        return $this->hasMany(Invoice::class);
    }

    public function taxes() {
        return $this->hasMany(Tax::class);
    }

    public function reports() {
        return $this->hasMany(Report::class);
    }

    public function vatDeclarations() {
        return $this->hasMany(VatDeclaration::class);
    }
}

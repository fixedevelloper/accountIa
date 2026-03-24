<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AiTrainingLog extends Model
{
    use HasFactory;

    protected $fillable = ['model_name','documents_used','trained_at'];

    protected $casts = ['trained_at' => 'datetime'];
}

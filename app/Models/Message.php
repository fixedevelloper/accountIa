<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_id',
        'type',
        'text',
    ];

    // Relation : message appartient à un projet
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // Relation : message appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'status',
        'last_message_at'
    ];

    // Relation : projet appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relation : projet a plusieurs messages
    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    // Dernier message
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}

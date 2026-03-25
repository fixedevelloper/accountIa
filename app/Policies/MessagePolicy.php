<?php


namespace App\Policies;


use App\Models\Message;
use App\Models\User;

class MessagePolicy
{

    public function view(User $user, Message $project)
    {
        return $user->id === $project->user_id;
    }

    public function update(User $user, Message $project)
    {
        return $user->id === $project->user_id;
    }

    public function delete(User $user, Message $project)
    {
        return $user->id === $project->user_id;
    }
}

<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Message;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Politiques associées aux modèles.
     */
    protected $policies = [
        Project::class => \App\Policies\ProjectPolicy::class,
        Message::class => \App\Policies\MessagePolicy::class,
    ];

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Gate pour vérifier si un utilisateur peut accéder à un projet
        Gate::define('view-project', function ($user, Project $project) {
            return $user->id === $project->user_id;
        });

        // Gate pour vérifier si un utilisateur peut envoyer un message
        Gate::define('send-message', function ($user, Project $project) {
            return $user->id === $project->user_id;
        });
    }
}

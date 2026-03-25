<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table des projets
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete(); // lien avec users
            $table->string('name');
            $table->enum('status', ['active', 'archived', 'pending'])->default('active');
            $table->timestamp('last_message_at')->nullable(); // dernier message du projet
            $table->timestamps();
            $table->softDeletes(); // suppression douce

            // Index pour recherche rapide
            $table->index('user_id');
            $table->index('status');
        });

        // Table des messages
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('project_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('type', ['user', 'ai']);
            $table->text('text');
            $table->timestamps();
            $table->softDeletes(); // optionnel, si tu veux pouvoir restaurer un message supprimé

            // Index pour accélérer les recherches par projet
            $table->index('project_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables_ia');
    }
};

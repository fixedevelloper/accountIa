<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        Role::create(['name' => 'owner']);
        Role::create(['name' => 'user']);
        Role::create(['name' => 'moderator']);
        // Ajoute d'autres rôles au besoin
    }
}


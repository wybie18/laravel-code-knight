<?php

namespace Database\Seeders;

use App\Models\CourseCategory;
use App\Models\Difficulty;
use App\Models\User;
use App\Models\UserRole;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        User::factory()->create([
            'username' => 'Administrator',
            'first_name' => 'admin',
            'last_name' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('qwe1234!')
        ]);

        // UserRole::create([
        //     'name' => 'admin',
        // ]);
        // UserRole::create([
        //     'name' => 'student',
        // ]);

        // CourseCategory::create([
        //     'name' => 'Programming',
        // ]);

        // CourseCategory::create([
        //     'name' => 'Cybersecurity',
        // ]);

        // Difficulty::create([
        //     'name' => 'Beginner',
        // ]);

        // Difficulty::create([
        //     'name' => 'Intermediate',
        // ]);

        // Difficulty::create([
        //     'name' => 'Advanced',
        // ]);
        
    }
}

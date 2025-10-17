<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    private const BASE_XP       = 100;
    private const XP_MULTIPLIER = 1.5;

    /**
     * Calculate XP required for the next level.
     */
    private function calculateXpForNextLevel(int $currentLevel): int
    {
        return (int) round(self::BASE_XP * pow($currentLevel, self::XP_MULTIPLIER));
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $milestones = [
            // Beginner Tier (1-10) - Learning the Basics
            [
                'level_number' => 1,
                'name' => 'Code Squire',
                'icon' => '⚔️',
                'description' => 'Your quest begins! Every knight starts with their first line of code.'
            ],
            [
                'level_number' => 5,
                'name' => 'Debug Apprentice',
                'icon' => '🐛',
                'description' => 'You\'ve learned to hunt bugs and fix errors. The path forward is clearer.'
            ],
            [
                'level_number' => 10,
                'name' => 'Syntax Warrior',
                'icon' => '⚡',
                'description' => 'Your code compiles cleanly. The fundamentals are your foundation.'
            ],
            
            // Intermediate Tier (11-30) - Building Skills
            [
                'level_number' => 15,
                'name' => 'Algorithm Knight',
                'icon' => '🎯',
                'description' => 'You wield algorithms with precision and grace.'
            ],
            [
                'level_number' => 20,
                'name' => 'Function Paladin',
                'icon' => '🛡️',
                'description' => 'Your functions are modular, reusable, and battle-tested.'
            ],
            [
                'level_number' => 25,
                'name' => 'Database Guardian',
                'icon' => '💾',
                'description' => 'Data flows through your queries like magic through runes.'
            ],
            [
                'level_number' => 30,
                'name' => 'API Architect',
                'icon' => '🏗️',
                'description' => 'You build bridges between systems with elegant interfaces.'
            ],
            
            // Advanced Tier (31-50) - Mastering the Craft
            [
                'level_number' => 40,
                'name' => 'Code Templar',
                'icon' => '⚜️',
                'description' => 'Your code is clean, efficient, and inspires others.'
            ],
            [
                'level_number' => 50,
                'name' => 'Stack Sage',
                'icon' => '📚',
                'description' => 'Full-stack mastery achieved. Frontend to backend, you command it all.'
            ],
            
            // Elite Tier (51-75) - Legendary Status
            [
                'level_number' => 60,
                'name' => 'Framework Sorcerer',
                'icon' => '🔮',
                'description' => 'You bend frameworks to your will and craft digital wonders.'
            ],
            [
                'level_number' => 75,
                'name' => 'DevOps Warlord',
                'icon' => '⚙️',
                'description' => 'CI/CD pipelines bow before you. Deployment is your domain.'
            ],
            
            // Mythic Tier (76-100) - Living Legend
            [
                'level_number' => 90,
                'name' => 'Code Crusader',
                'icon' => '👑',
                'description' => 'Your contributions shape the future of technology.'
            ],
            [
                'level_number' => 100,
                'name' => 'Grand CodeMaster',
                'icon' => '🌟',
                'description' => 'Legendary. Immortal. Your legacy in code will inspire generations.'
            ],
        ];

        foreach ($milestones as $milestone) {
            $level = $milestone['level_number'];

            Level::create([
                ...$milestone,
                'exp_required' => $this->calculateXpForNextLevel($level),
            ]);
        }
    }
}

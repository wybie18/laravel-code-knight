<?php
namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    private const BASE_XP       = 100;
    private const XP_MULTIPLIER = 1.5;

    /**
     * Calculate XP required for the next level (Incremental step).
     */
    private function calculateXpForNextLevel(int $currentLevel): int
    {
        return (int) round(self::BASE_XP * pow($currentLevel, self::XP_MULTIPLIER));
    }

    /**
     * Calculate Total XP required to reach a specific level (Cumulative).
     */
    private function calculateXpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        $totalXp = 0;
        for ($i = 1; $i < $level; $i++) {
            $totalXp += $this->calculateXpForNextLevel($i);
        }

        return $totalXp;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $levels = [
            [
                'level_number' => 1,
                'name'         => 'Hello World Novice',
                'description'  => 'The journey begins with a single print statement. You have taken the first step into a larger world.',
            ],
            [
                'level_number' => 6,
                'name'         => 'Syntax Squire',
                'description'  => 'Like a squire polishing armor, you ensure every semicolon and bracket is in its rightful place to please the compiler.',
            ],
            [
                'level_number' => 11,
                'name'         => 'Debugging Disciple',
                'description'  => 'With patience as your shield, you hunt down syntax errors and logical bugs that plague your code.',
            ],
            [
                'level_number' => 16,
                'name'         => 'Logic Legionnaire',
                'description'  => 'You wield the power of control flow. Conditionals and loops are your weapons to dictate program behavior.',
            ],
            [
                'level_number' => 21,
                'name'         => 'Binary Baron',
                'description'  => 'Moving past high-level abstractions, you understand the fundamental 0s and 1s that form the bedrock of the digital kingdom.',
            ],
            [
                'level_number' => 26,
                'name'         => 'Terminal Templar',
                'description'  => 'The mouse is no longer your crutch. You command the system through the Command Line Interface (CLI) with precision.',
            ],
            [
                'level_number' => 31,
                'name'         => 'Script Sentinel',
                'description'  => 'You automate the mundane. You build automated scripts to watch over your systems and execute tasks.',
            ],
            [
                'level_number' => 36,
                'name'         => 'Firewall Guardian',
                'description'  => 'You stand at the gate, understanding network traffic and filtering out the noise to block unauthorized packets.',
            ],
            [
                'level_number' => 41,
                'name'         => 'Cipher Cavalier',
                'description'  => 'You protect secrets with mathematics, understanding how to scramble data so only the intended recipient can read it.',
            ],
            [
                'level_number' => 46,
                'name'         => 'Protocol Paladin',
                'description'  => 'You are a diplomat of the network, understanding the strict rules of TCP/IP and HTTP that allow machines to communicate.',
            ],
            [
                'level_number' => 51,
                'name'         => 'Kernel Commander',
                'description'  => 'You operate deep within the core, understanding how the OS manages hardware, processes, and memory.',
            ],
            [
                'level_number' => 56,
                'name'         => 'Exploit Elite',
                'description'  => 'To defend the castle, you must think like the invader. You study vulnerabilities to understand how systems are broken.',
            ],
            [
                'level_number' => 61,
                'name'         => 'Stack Sovereign',
                'description'  => 'You are a master of memory. You understand the stack, the heap, and buffer overflows, manipulating memory addresses surgically.',
            ],
            [
                'level_number' => 66,
                'name'         => 'Root Regent',
                'description'  => 'You possess the keys to the kingdom. With full administrative privileges comes the responsibility to build or destroy.',
            ],
            [
                'level_number' => 71,
                'name'         => 'Algorithm Archmage',
                'description'  => 'You weave spells of efficiency, solving complex computational problems using advanced data structures.',
            ],
            [
                'level_number' => 76,
                'name'         => 'Vulnerability Vanguard',
                'description'  => 'You are the frontline defense, predicting zero-day threats and patching security holes before they are exploited.',
            ],
            [
                'level_number' => 81,
                'name'         => 'Cryptography King',
                'description'  => 'You are the architect of trust, implementing advanced encryption standards that form the foundation of security.',
            ],
            [
                'level_number' => 86,
                'name'         => 'Mainframe Emperor',
                'description'  => 'Your vision spans the entire infrastructure. You design and secure massive, scalable architectures.',
            ],
            [
                'level_number' => 91,
                'name'         => 'Grandmaster of Bits',
                'description'  => 'A living legend. You see the matrix of code, understanding the interplay between hardware and software with perfect clarity.',
            ],
            [
                'level_number' => 96,
                'name'         => 'The CodeKnight',
                'description'  => 'The ultimate rank. You have mastered the blade of logic and the shield of security. You are the protector of the digital realm.',
            ],
        ];

        foreach ($levels as $levelData) {
            $levelNum = $levelData['level_number'];

            Level::create([
                 ...$levelData,
                'exp_required' => $this->calculateXpForLevel($levelNum),
            ]);
        }
    }
}

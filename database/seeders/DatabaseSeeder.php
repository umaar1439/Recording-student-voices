<?php

namespace Database\Seeders;

use App\Models\Ayah;
use App\Models\User;
use App\Support\Quran;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'مدير المنصة',
                'email' => 'admin@admin.com',
                'role' => User::ROLE_ADMIN,
            ]
        ];

        foreach ($accounts as $account) {
            User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'admin',
                    'role' => $account['role'],
                    'email_verified_at' => now(),
                ],
            );
        }

        User::factory()
            ->count(6)
            ->student()
            ->create();

        $this->call(QuranDatasetSeeder::class);

        $this->command?->info('Ayahs: '.Ayah::count().' / '.Quran::TOTAL_AYAHS.' — Users: '.User::count());
        $this->command?->info('Login: admin@admin.com (password)');
    }
}

<?php

namespace Database\Seeders;

use App\Domain\User\Entities\User;
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
        $this->command->info('========================================');
        $this->command->info('Starting Database Seeding');
        $this->command->info('========================================');
        $this->command->newLine();

        // Seed tenant data (central database)
        $this->command->info('Step 1: Seeding central database (tenants)...');
        $this->call([
            TenantSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('Step 2: Running tenant migrations...');
        $this->command->call('tenants:migrate', ['--force' => true]);

        $this->command->newLine();
        $this->command->info('Step 3: Seeding tenant databases...');
        $this->command->call('tenants:seed', ['--class' => 'TenantDemoSeeder']);

        $this->command->newLine();
        $this->command->info('========================================');
        $this->command->info('Database Seeding Complete!');
        $this->command->info('========================================');
        $this->command->newLine();
        $this->command->info('You can now access the application at:');
        $this->command->line('  http://techco.vrtx.local:5173');
        $this->command->newLine();
        $this->command->info('Login credentials:');
        $this->command->line('  bob@techco.com / password123');
        $this->command->line('  sarah@techco.com / password123');
        $this->command->line('  mike@techco.com / password123');
    }
}

<?php

namespace Database\Seeders;

use App\Actions\RegisterUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(RegisterUser $registerUser): void
    {
        $this->call(CharacterSeeder::class);

        // demo@ulvi.test / password
        if (! User::query()->where('email', 'demo@ulvi.test')->exists()) {
            $registerUser->handle('Demo User', 'demo@ulvi.test', 'password');
        }
    }
}

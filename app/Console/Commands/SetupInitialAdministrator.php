<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdministratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SetupInitialAdministrator extends Command
{
    protected $signature = 'facultyconnect:setup-admin';

    protected $description = 'Interactively create the first administrator or deliberately promote an identified account';

    public function handle(AdministratorService $administrators): int
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            $this->error('An administrator already exists. Use facultyconnect:recover-admin.');

            return self::FAILURE;
        }
        $data = [
            'first_name' => trim((string) $this->ask('Actual first name')),
            'last_name' => trim((string) $this->ask('Actual last name')),
            'email' => strtolower(trim((string) $this->ask('Official email address'))),
            'faculty_id' => trim((string) $this->ask('Employee or faculty ID')),
            'department' => trim((string) $this->ask('Department')),
            'position' => trim((string) $this->ask('Position or designation')),
        ];
        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'], 'faculty_id' => ['required', 'string', 'max:50'],
            'department' => ['required', 'string', 'max:180'], 'position' => ['required', 'string', 'max:180'],
        ]);
        if ($validator->fails()) {
            $this->error('Required identity/profile details are invalid.');

            return self::FAILURE;
        }
        $existing = User::where('email', $data['email'])->orWhere('faculty_id', $data['faculty_id'])->exists();
        $promote = false;
        $password = null;
        if ($existing) {
            $promote = $this->confirm('Promote this exactly matched existing account belonging to Sir Olivo?');
            if (! $promote) {
                return self::FAILURE;
            }
        } else {
            $password = (string) $this->secret('Initial password (hidden)');
            $confirmation = (string) $this->secret('Confirm password (hidden)');
            $valid = Validator::make(['password' => $password, 'password_confirmation' => $confirmation], [
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            ]);
            if ($valid->fails()) {
                $this->error('Password must match confirmation and contain at least 12 characters, mixed case, numbers and symbols.');

                return self::FAILURE;
            }
            if (! $this->confirm('Create this first administrator for Sir Olivo?')) {
                return self::FAILURE;
            }
        }
        try {
            $user = $administrators->bootstrap($data, $password, $promote);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());

            return self::FAILURE;
        }
        $this->info('Administrator saved as record #'.$user->id.'. Sign in at the normal login page. Email verification and missing TOTP setup are required.');

        return self::SUCCESS;
    }
}

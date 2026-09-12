<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('demoAccounts')]
    public function test_demo_account_can_sign_in_and_reach_dashboard(string $email): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/lms/login', ['email' => $email, 'password' => 'Demo@123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_invalid_credentials_return_a_clear_error(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/lms/login', ['email' => 'student@greenfield.demo', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match an active account.']);

        $this->assertGuest();
    }

    public static function demoAccounts(): array
    {
        return [
            'principal' => ['principal@greenfield.demo'],
            'teacher' => ['teacher@greenfield.demo'],
            'student' => ['student@greenfield.demo'],
            'parent' => ['parent@greenfield.demo'],
        ];
    }
}

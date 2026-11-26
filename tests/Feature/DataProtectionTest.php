<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Services\DataProtectionService;
use App\Services\InvitationService;
use App\Services\TrustedDeviceService;
use App\Services\TwoFactorService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DataProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_profile_values_are_encrypted_and_null_stays_null(): void
    {
        $first = User::factory()->create([
            'contact_number' => '09171234567',
            'bio' => 'Private faculty profile information.',
        ]);
        $second = User::factory()->create([
            'contact_number' => '09171234567',
            'bio' => null,
        ]);

        $firstRaw = DB::table('users')->where('id', $first->id)->first();
        $secondRaw = DB::table('users')->where('id', $second->id)->first();

        $this->assertNotSame('09171234567', $firstRaw->contact_number);
        $this->assertNotSame('Private faculty profile information.', $firstRaw->bio);
        $this->assertSame('09171234567', $first->fresh()->contact_number);
        $this->assertSame('Private faculty profile information.', $first->fresh()->bio);
        $this->assertNotSame($firstRaw->contact_number, $secondRaw->contact_number);
        $this->assertNull($secondRaw->bio);
        $this->assertNull($second->fresh()->bio);

        $protectedContact = $firstRaw->contact_number;
        $first->update(['position' => 'Professor']);
        $this->assertSame($protectedContact, DB::table('users')->where('id', $first->id)->value('contact_number'));
        $this->assertSame('09171234567', $first->fresh()->contact_number);
    }

    public function test_payment_reference_and_notes_are_encrypted_with_a_blind_index(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_TREASURER]);
        $faculty = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $faculty->id,
            'recorded_by' => $admin->id,
            'reference_no' => 'FC-PAY-PRIVATE-001',
            'notes' => 'Confidential payment note.',
        ]);
        $raw = DB::table('payments')->where('id', $payment->id)->first();

        $this->assertNotSame('FC-PAY-PRIVATE-001', $raw->reference_no);
        $this->assertNotSame('Confidential payment note.', $raw->notes);
        $this->assertSame(
            app(DataProtectionService::class)->paymentReferenceLookup('FC-PAY-PRIVATE-001'),
            $raw->reference_no_lookup,
        );
        $this->assertSame('FC-PAY-PRIVATE-001', $payment->fresh()->reference_no);
        $this->assertSame('Confidential payment note.', $payment->fresh()->notes);
        $this->assertTrue(Payment::whereReference('fc-pay-private-001')->firstOrFail()->is($payment));

        $this->actingAs($admin)->post(route('payments.store'), [
            'user_id' => $faculty->id,
            'reference_no' => '  fc-pay-private-001  ',
            'description' => 'Duplicate reference',
            'amount' => 100,
            'status' => 'pending',
        ])->assertSessionHasErrors('reference_no');
    }

    public function test_conversion_is_dry_runnable_chunked_idempotent_and_verifiable(): void
    {
        $plainContact = '09179998888';
        $plainBio = 'Legacy private profile.';
        $plainReference = 'LEGACY-REFERENCE-001';
        $plainNotes = 'Legacy confidential payment note.';
        $legacyUser = User::factory()->create();
        $alreadyProtected = User::factory()->create(['contact_number' => '09170001111']);
        $payment = Payment::factory()->create(['user_id' => $legacyUser->id]);

        DB::table('users')->where('id', $legacyUser->id)->update([
            'contact_number' => $plainContact,
            'bio' => $plainBio,
        ]);
        DB::table('payments')->where('id', $payment->id)->update([
            'reference_no' => $plainReference,
            'reference_no_lookup' => null,
            'notes' => $plainNotes,
        ]);
        $alreadyEncrypted = DB::table('users')->where('id', $alreadyProtected->id)->value('contact_number');

        $this->artisan('facultyconnect:protect-data', ['--dry-run' => true, '--chunk' => 1])
            ->doesntExpectOutputToContain($plainContact)
            ->doesntExpectOutputToContain($plainBio)
            ->doesntExpectOutputToContain($plainReference)
            ->doesntExpectOutputToContain($plainNotes)
            ->assertSuccessful();

        $this->assertSame($plainContact, DB::table('users')->where('id', $legacyUser->id)->value('contact_number'));
        $this->assertSame($plainReference, DB::table('payments')->where('id', $payment->id)->value('reference_no'));

        $this->artisan('facultyconnect:protect-data', ['--chunk' => 1, '--allow-plaintext' => true])
            ->doesntExpectOutputToContain($plainContact)
            ->doesntExpectOutputToContain($plainBio)
            ->doesntExpectOutputToContain($plainReference)
            ->doesntExpectOutputToContain($plainNotes)
            ->assertSuccessful();

        $protectedContact = DB::table('users')->where('id', $legacyUser->id)->value('contact_number');
        $protectedReference = DB::table('payments')->where('id', $payment->id)->value('reference_no');
        $this->assertNotSame($plainContact, $protectedContact);
        $this->assertNotSame($plainReference, $protectedReference);
        $this->assertSame($plainContact, $legacyUser->fresh()->contact_number);
        $this->assertSame($plainBio, $legacyUser->fresh()->bio);
        $this->assertSame($plainReference, $payment->fresh()->reference_no);
        $this->assertSame($plainNotes, $payment->fresh()->notes);
        $this->assertSame($alreadyEncrypted, DB::table('users')->where('id', $alreadyProtected->id)->value('contact_number'));

        $this->artisan('facultyconnect:protect-data', ['--chunk' => 1])->assertSuccessful();
        $this->assertSame($protectedContact, DB::table('users')->where('id', $legacyUser->id)->value('contact_number'));
        $this->assertSame($protectedReference, DB::table('payments')->where('id', $payment->id)->value('reference_no'));
        $this->artisan('facultyconnect:protect-data', ['--verify' => true, '--chunk' => 1])->assertSuccessful();
    }

    public function test_corrupted_ciphertext_fails_verification_without_exposing_it(): void
    {
        $user = User::factory()->create(['contact_number' => '09173334444']);
        $encrypted = DB::table('users')->where('id', $user->id)->value('contact_number');
        $payload = json_decode(base64_decode($encrypted, true), true);
        $payload['mac'] = str_repeat('0', 64);
        $corrupted = base64_encode(json_encode($payload));
        DB::table('users')->where('id', $user->id)->update(['contact_number' => $corrupted]);

        $this->artisan('facultyconnect:protect-data', ['--verify' => true])
            ->doesntExpectOutputToContain($corrupted)
            ->assertFailed();

        $this->expectException(DecryptException::class);
        $user->fresh()->contact_number;
    }

    public function test_an_incorrect_key_cannot_silently_decrypt_protected_data(): void
    {
        $ciphertext = Crypt::encryptString('protected-value');
        $wrongEncrypter = new Encrypter(random_bytes(32), 'AES-256-CBC');

        $this->expectException(DecryptException::class);
        $wrongEncrypter->decryptString($ciphertext);
    }

    public function test_profile_data_remains_subject_to_server_side_authorization(): void
    {
        $faculty = User::factory()->create([
            'role' => User::ROLE_FACULTY,
            'contact_number' => '09175551234',
        ]);
        $other = User::factory()->create(['contact_number' => '09176662345']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($faculty)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('09175551234')
            ->assertDontSee('09176662345');
        $this->actingAs($faculty)->get(route('members.show', $other))->assertForbidden();
        $this->actingAs($admin)->get(route('members.show', $other))->assertOk()->assertSee('09176662345');
    }

    public function test_sensitive_validation_input_is_not_flashed_or_embedded_in_payment_updates(): void
    {
        $faculty = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payment = Payment::factory()->create([
            'user_id' => $faculty->id,
            'reference_no' => 'PRIVATE-REF-123',
            'notes' => 'Private note hidden from form fields.',
        ]);

        $response = $this->actingAs($faculty)->patch(route('profile.update'), [
            'first_name' => '',
            'middle_name' => $faculty->middle_name,
            'last_name' => $faculty->last_name,
            'email' => $faculty->email,
            'department' => $faculty->department,
            'position' => $faculty->position,
            'contact_number' => '09178889999',
            'bio' => 'Sensitive invalid form content.',
        ])->assertSessionHasErrors('first_name');

        $oldInput = $response->getSession()->getOldInput();
        $this->assertArrayNotHasKey('contact_number', $oldInput);
        $this->assertArrayNotHasKey('bio', $oldInput);

        $this->actingAs($admin)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertDontSee('type="hidden" name="reference_no"', false)
            ->assertDontSee('type="hidden" name="notes"', false)
            ->assertSee($payment->reference_no);
    }

    public function test_two_factor_secret_stays_encrypted_and_authenticator_login_still_works(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $user = User::factory()->create([
            'password' => Hash::make('Faculty!Pass123'),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('two_factor_secret'));
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'Faculty!Pass123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->post(route('two-factor.challenge.store'), [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_non_reversible_credentials_and_tokens_remain_hashed(): void
    {
        Notification::fake();
        $password = 'Faculty!Pass123';
        $user = User::factory()->create(['password' => $password]);
        $recoveryCode = app(TwoFactorService::class)->generateRecoveryCodes($user)[0];
        $rawRecoveryStorage = DB::table('users')->where('id', $user->id)->value('two_factor_recovery_codes');
        $decryptedRecoveryHashes = json_decode(Crypt::decryptString($rawRecoveryStorage), true);

        $this->assertNotSame($password, $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check($password, $user->getRawOriginal('password')));
        $this->assertStringNotContainsString($recoveryCode, $rawRecoveryStorage);
        $this->assertNotContains($recoveryCode, $decryptedRecoveryHashes);
        $this->assertContains(app(TwoFactorService::class)->hashRecoveryCode($recoveryCode), $decryptedRecoveryHashes);

        $pending = User::factory()->create(['status' => User::STATUS_PENDING, 'activated_at' => null]);
        app(InvitationService::class)->send($pending);
        $notification = Notification::sent($pending, AccountInvitation::class)->first();
        $mail = $notification->toMail($pending);
        $temporary = $mail->viewData['temporaryPassword'];
        $this->assertTrue(Hash::check($temporary, $pending->fresh()->password));
        $this->assertNull($pending->fresh()->invitation_token_hash);

        $resetToken = Password::createToken($user);
        $storedResetHash = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($resetToken, $storedResetHash);
        $this->assertTrue(Hash::check($resetToken, $storedResetHash));

        $issued = app(TrustedDeviceService::class)->issue(
            $user,
            Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => 'Data Protection Test Browser']),
        );
        [, $trustedToken] = explode('.', $issued->cookie->getValue(), 2);
        $this->assertNotSame($trustedToken, $issued->device->token_hash);
        $this->assertSame(hash_hmac('sha256', $trustedToken, config('app.key')), $issued->device->token_hash);
    }
}

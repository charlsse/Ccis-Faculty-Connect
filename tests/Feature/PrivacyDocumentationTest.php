<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\PrivacyDocumentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyDocumentationTest extends TestCase
{
    use RefreshDatabase;
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) if (is_file($file)) unlink($file);
        parent::tearDown();
    }

    private function approveFixtures(): array
    {
        foreach (['privacy-notice', 'terms-of-use'] as $slug) {
            $file = '__test-'.bin2hex(random_bytes(8)).'.md';
            $path = resource_path('privacy/'.$file);
            file_put_contents($path, '## Synthetic approved test document'.PHP_EOL.'Test policy only.');
            $this->files[] = $path;
            config(['privacy-documents.documents.'.$slug => [
                'title' => $slug, 'audience' => 'public', 'version' => '1.0', 'status' => 'published', 'file' => $file, 'open_items' => [],
                'approval' => ['reviewed_by' => 'Synthetic reviewer', 'evidence' => 'TEST-APPROVAL',
                    'approved_at' => now()->subDay()->toDateString(), 'effective_at' => now()->subDay()->toDateString(),
                    'sha256' => hash_file('sha256', $path), 'checks' => array_fill_keys(config('privacy-documents.required_reviews'), true)],
            ]]);
        }
        config(['privacy-documents.acknowledgment_enabled' => true]);
        return app(PrivacyDocumentation::class)->acknowledgmentPolicies();
    }

    private function pending(): User
    {
        return User::factory()->create(['status' => User::STATUS_PENDING, 'must_change_password' => true,
            'temporary_password_expires_at' => now()->addHour(), 'password' => 'Temporary!Password123',
            'password_replaced_at' => null, 'activated_at' => null, 'email_verified_at' => null,
            'two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
    }

    private function passwordData(): array
    {
        return ['password' => 'NewPermanent!Pass123', 'password_confirmation' => 'NewPermanent!Pass123'];
    }

    public function test_drafts_are_not_public_and_only_admin_can_read_internal_documents(): void
    {
        $this->get(route('privacy.public'))->assertOk()->assertSee('Awaiting policy approval')
            ->assertSee(config('privacy-documents.disclosure'))->assertDontSee('Local configuration observations');
        foreach (array_keys(config('privacy-documents.documents')) as $slug) {
            $this->get(route('privacy.published', $slug))->assertNotFound();
        }
        $this->get(route('privacy.index'))->assertRedirect(route('login'));
        $this->get(route('privacy.draft', 'data-inventory'))->assertRedirect(route('login'));
        foreach (['faculty','treasurer','secretary','staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('privacy.index'))->assertOk()->assertDontSee('Internal draft documentation')->assertDontSee('Verified Controls');
            $this->get(route('privacy.draft', 'data-inventory'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('privacy.index'))->assertOk()->assertSee('Internal draft documentation');
        foreach (array_keys(config('privacy-documents.documents')) as $slug) {
            $this->get(route('privacy.draft', $slug))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('approval required');
        }
        $this->get(route('privacy.draft', 'unknown'))->assertNotFound();
    }

    public function test_unapproved_documents_never_enable_required_acknowledgment_even_if_flag_is_on(): void
    {
        config(['privacy-documents.acknowledgment_enabled' => true]);
        $user = $this->pending();
        $this->actingAs($user)->get(route('onboarding.password'))->assertOk()->assertDontSee('name="privacy_acknowledged"', false)->assertDontSee('name="terms_accepted"', false);
        $this->post(route('onboarding.password.update'), $this->passwordData()+['privacy_acknowledged'=>1,'terms_accepted'=>1])
            ->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame(0, AuditLog::where('action','policy.acknowledged')->count());
    }

    public function test_publication_requires_approval_integrity_effective_date_and_public_audience(): void
    {
        $this->approveFixtures();
        $service = app(PrivacyDocumentation::class);
        $this->get(route('privacy.published','privacy-notice'))->assertOk()->assertSee('Synthetic approved test document');
        foreach (['approval'=>null,'open_items'=>['Not resolved'],'version'=>'0.1-draft','audience'=>'internal','status'=>'draft'] as $key=>$value) {
            $saved=config('privacy-documents.documents.privacy-notice.'.$key);
            config(['privacy-documents.documents.privacy-notice.'.$key=>$value]);
            $this->get(route('privacy.published','privacy-notice'))->assertNotFound();
            $this->assertNull($service->acknowledgmentPolicies());
            config(['privacy-documents.documents.privacy-notice.'.$key=>$saved]);
        }
        config(['privacy-documents.documents.privacy-notice.approval.checks.contact'=>false]);
        $this->get(route('privacy.published','privacy-notice'))->assertNotFound();
        config(['privacy-documents.documents.privacy-notice.approval.checks.contact'=>true,
            'privacy-documents.documents.privacy-notice.approval.effective_at'=>now()->addDay()->toDateString()]);
        $this->get(route('privacy.published','privacy-notice'))->assertNotFound();
        config(['privacy-documents.documents.privacy-notice.approval.effective_at'=>now()->subDay()->toDateString()]);
        file_put_contents($this->files[0], 'Modified after approval');
        $this->get(route('privacy.published','privacy-notice'))->assertNotFound();
    }

    public function test_approved_acknowledgment_is_separate_validated_versioned_and_recorded_on_success(): void
    {
        $policies=$this->approveFixtures();$user=$this->pending();$this->actingAs($user);
        $this->get(route('onboarding.password'))->assertOk()->assertSee('name="privacy_acknowledged"',false)->assertSee('name="terms_accepted"',false);
        $data=$this->passwordData();
        $this->post(route('onboarding.password.update'),$data)->assertSessionHasErrors(['privacy_acknowledged','terms_accepted','privacy_version']);
        $this->assertTrue($user->fresh()->must_change_password);
        foreach($policies as $kind=>$policy){$data[$kind.'_version']=$policy['version'];$data[$kind.'_sha256']=$policy['sha256'];}
        $data+=['privacy_acknowledged'=>1,'terms_accepted'=>1];
        $this->post(route('onboarding.password.update'),[...$data,'privacy_version'=>'old'])->assertSessionHasErrors('privacy_version');
        $this->assertSame(0,AuditLog::where('action','policy.acknowledged')->count());
        $this->post(route('onboarding.password.update'),$data)->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();
        $event=AuditLog::where('action','policy.acknowledged')->sole();
        $this->assertSame($user->id,$event->subject_user_id);
        $this->assertSame('1.0',$event->details['privacy_version']);
        $this->assertSame($policies['terms']['sha256'],$event->details['terms_sha256']);
        $this->assertFalse($event->details['purpose_specific_consent']);
    }

    public function test_stale_policy_choices_are_not_prechecked_against_new_versions(): void
    {
        $this->approveFixtures();$this->actingAs($this->pending());
        $this->withSession(['_old_input'=>['privacy_acknowledged'=>1,'terms_accepted'=>1,'privacy_version'=>'old','terms_version'=>'old','privacy_sha256'=>'old','terms_sha256'=>'old']])
            ->get(route('onboarding.password'))->assertOk()->assertDontSee('checked',false);
    }
}

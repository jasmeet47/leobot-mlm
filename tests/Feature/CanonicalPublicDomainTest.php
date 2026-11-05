<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CanonicalPublicDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_beta_apex_redirects_to_production_without_forwarding_identifiers_or_tokens(): void
    {
        config(['app.release_stage' => 'beta']);
        $this->get('https://lumiflowon.com/join?ref=BETA-ID&token=PRIVATE-RESET')
            ->assertRedirect('https://www.lumiflowon.com/join')->assertDontSee('PRIVATE-RESET');
        $this->get('https://lumiflowon.com/')->assertRedirect('https://www.lumiflowon.com/');
    }

    public function test_stale_beta_form_is_never_replayed_or_processed_on_either_environment(): void
    {
        config(['app.release_stage' => 'beta']);
        $this->postJson('https://lumiflowon.com/register', ['email' => 'synthetic@example.test'])
            ->assertStatus(409)->assertJsonPath('message', 'This form has moved. Open https://www.lumiflowon.com/join before submitting. No action was performed.');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('financial_ledger', 0);
    }

    public function test_actual_beta_render_url_keeps_the_separate_sandbox(): void
    {
        config(['app.release_stage' => 'beta']);
        $this->get('https://lumiflowon-beta.onrender.com/join')->assertOk()->assertSee('BETA TESTING');
    }

    public function test_production_www_login_is_not_redirected_by_beta_routing(): void
    {
        config(['app.release_stage' => 'production']);
        $this->get('https://www.lumiflowon.com/join')->assertOk()->assertDontSee('BETA TESTING');
    }
}

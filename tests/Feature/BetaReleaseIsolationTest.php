<?php

namespace Tests\Feature;

use Tests\TestCase;

final class BetaReleaseIsolationTest extends TestCase
{
    public function test_beta_startup_refuses_an_unidentified_service_before_any_database_access(): void
    {
        $this->artisan('lumiflowon:beta-prepare')->expectsOutput('Beta service identity guard failed.')->assertFailed();
    }

    public function test_beta_public_pages_identify_the_sandbox_without_claiming_live_finance(): void
    {
        config(['app.release_stage' => 'beta']);
        $this->get('/')->assertOk()->assertSee('BETA TESTING')->assertSee('Do not send real funds.');
        config(['app.release_stage' => 'production']);
        $this->get('/')->assertOk()->assertDontSee('BETA TESTING');
    }

    public function test_production_service_cannot_run_beta_migrations_even_with_beta_stage(): void
    {
        $priorService = getenv('RENDER_SERVICE_ID');
        $priorStage = getenv('LUMIFLOWON_RELEASE_STAGE');
        try {
            putenv('RENDER_SERVICE_ID=srv-db05ocad0e5s73a77rb0');
            putenv('LUMIFLOWON_RELEASE_STAGE=beta');
            $this->artisan('lumiflowon:beta-prepare')->expectsOutput('Beta service identity guard failed.')->assertFailed();
        } finally {
            putenv($priorService === false ? 'RENDER_SERVICE_ID' : 'RENDER_SERVICE_ID='.$priorService);
            putenv($priorStage === false ? 'LUMIFLOWON_RELEASE_STAGE' : 'LUMIFLOWON_RELEASE_STAGE='.$priorStage);
        }
    }
}

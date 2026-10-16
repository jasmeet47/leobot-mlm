<?php

namespace Tests\Feature;

use Tests\TestCase;

class EmailOtpPasswordResetTest extends TestCase
{
    public function test_request_is_disabled(): void
    {
        $this->get(route('password.otp.request'))
            ->assertNotFound();
    }

    public function test_send_is_disabled(): void
    {
        $this->post(route('password.otp.send'), [
            'login' => 'disabled',
        ])->assertNotFound();
    }

    public function test_form_is_disabled(): void
    {
        $this->get(route('password.otp.form'))
            ->assertNotFound();
    }

    public function test_reset_is_disabled(): void
    {
        $this->post(route('password.otp.reset'), [
            'login' => 'disabled',
            'otp' => '000000',
        ])->assertNotFound();
    }
}
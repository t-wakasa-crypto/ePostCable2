<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * ルート（ダッシュボード）は認証必須のため、未認証アクセスは
     * /login へリダイレクトされる（FR-16 / FR-17）。
     */
    public function test_the_root_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}

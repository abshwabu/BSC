<?php

use App\Models\User;

it('redirects unauthenticated users from root to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

it('allows authenticated users to access dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
});

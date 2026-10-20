<?php

use Database\Seeders\CharacterSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/*
| openapi.yaml is the contract. These tests fail the build when the code and the contract
| disagree: a route without a description, a description without a route, or a response
| whose status, headers or body differ from what the contract promises.
*/

function documentedOperations(): array
{
    $spec = Yaml::parseFile(base_path('openapi.yaml'));
    $operations = [];

    foreach ($spec['paths'] as $path => $methods) {
        foreach (array_keys($methods) as $method) {
            $operations[] = strtoupper($method).' '.$path;
        }
    }

    sort($operations);

    return $operations;
}

function implementedOperations(): array
{
    $operations = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/') && ! str_contains($route->uri(), 'broadcasting'))
        ->flatMap(fn (Route $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => "{$method} /{$route->uri()}"))
        ->sort()
        ->values()
        ->all();

    return $operations;
}

it('documents every API route, and every documented route exists', function () {
    expect(implementedOperations())->toBe(documentedOperations());
});

it('matches the contract for the account endpoints', function () {
    $register = $this->postJson('/api/v1/auth/register', [
        'name' => 'Lucas', 'email' => 'lucas@example.com', 'password' => 'correct horse',
    ]);
    expect($register)->toMatchContract('POST', '/api/v1/auth/register');

    $token = $register->json('data.token');

    expect($this->withToken($token)->getJson('/api/v1/me'))->toMatchContract('GET', '/api/v1/me');
    expect($this->postJson('/api/v1/auth/login', ['email' => 'lucas@example.com', 'password' => 'wrong']))->toMatchContract('POST', '/api/v1/auth/login');
    expect($this->postJson('/api/v1/auth/register', []))->toMatchContract('POST', '/api/v1/auth/register');
});

it('matches the contract for the conversation flow', function () {
    Queue::fake();
    $this->seed(CharacterSeeder::class);
    $user = userWithTokens();
    $this->actingAs($user);

    expect($this->getJson('/api/v1/characters'))->toMatchContract('GET', '/api/v1/characters');

    $conversation = $this->postJson('/api/v1/conversations', ['character' => 'sera']);
    expect($conversation)->toMatchContract('POST', '/api/v1/conversations');
    $id = $conversation->json('data.id');

    $body = ['message' => 'I keep replaying the meeting.', 'client_message_id' => (string) Str::uuid()];
    expect($this->postJson("/api/v1/conversations/{$id}/messages", $body))->toMatchContract('POST', '/api/v1/conversations/{conversation}/messages');
    expect($this->postJson("/api/v1/conversations/{$id}/messages", $body))->toMatchContract('POST', '/api/v1/conversations/{conversation}/messages');
    expect($this->postJson("/api/v1/conversations/{$id}/messages", ['message' => '']))->toMatchContract('POST', '/api/v1/conversations/{conversation}/messages');
    expect($this->getJson("/api/v1/conversations/{$id}/messages"))->toMatchContract('GET', '/api/v1/conversations/{conversation}/messages');
});

it('matches the contract when a message cannot be afforded', function () {
    $this->seed(CharacterSeeder::class);
    $user = userWithTokens(10);
    $conversation = conversationFor($user);

    $response = $this->actingAs($user)->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'message' => 'Hello', 'client_message_id' => (string) Str::uuid(),
    ]);

    expect($response->status())->toBe(402);
    expect($response)->toMatchContract('POST', '/api/v1/conversations/{conversation}/messages');
});

it('matches the contract for the wallet and top-up endpoints', function () {
    $this->actingAs(userWithTokens());

    expect($this->getJson('/api/v1/billing/wallet'))->toMatchContract('GET', '/api/v1/billing/wallet');
    expect($this->getJson('/api/v1/billing/ledger'))->toMatchContract('GET', '/api/v1/billing/ledger');
    expect($this->getJson('/api/v1/billing/top-ups'))->toMatchContract('GET', '/api/v1/billing/top-ups');
    expect($this->postJson('/api/v1/billing/top-ups/checkout', ['pack' => 'boost_m']))->toMatchContract('POST', '/api/v1/billing/top-ups/checkout');
    expect($this->postJson('/api/v1/billing/top-ups/checkout', ['pack' => 'nope']))->toMatchContract('POST', '/api/v1/billing/top-ups/checkout');
});

it('matches the contract for the webhook endpoint', function () {
    expect(deliverStripeEvent(stripeEvent('invoice.created', ['id' => 'in_1'])))->toMatchContract('POST', '/api/webhooks/stripe');
    expect(deliverStripeEvent(stripeEvent('invoice.created', ['id' => 'in_1']), secret: 'wrong'))->toMatchContract('POST', '/api/webhooks/stripe');
});

<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Services\StripeEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Event as StripeEvent;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_webhook_processes_event(): void
    {
        $event = StripeEvent::constructFrom([
            'type' => 'test.event',
            'data' => ['object' => []],
        ]);

        $processor = Mockery::mock(StripeEventProcessor::class);
        $processor->shouldReceive('constructEvent')->once()->andReturn($event);
        $processor->shouldReceive('handle')->once()->with($event);

        app()->instance(StripeEventProcessor::class, $processor);

        $response = $this->withHeaders(['Stripe-Signature' => 'sig'])->postJson(route('stripe.webhook'), []);

        $response->assertOk();
    }

    public function test_missing_signature_returns_error(): void
    {
        $response = $this->postJson(route('stripe.webhook'), []);

        $response->assertStatus(400);
    }
}
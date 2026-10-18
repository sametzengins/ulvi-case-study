<?php

namespace App\Http\Controllers;

use App\Billing\FakeGateway;
use App\Billing\PaymentGateway;
use App\Billing\StripeEvents;
use App\Billing\StripeSignature;
use App\Billing\StripeWebhookReceiver;
use Illuminate\Contracts\View\View;

/**
 * The demo page and a local checkout page. Off in production.
 */
class DemoController extends Controller
{
    public function checkout(string $session, PaymentGateway $gateway): View
    {
        return view('demo.checkout', ['session' => $this->session($gateway, $session)]);
    }

    /**
     * Marks the session paid and delivers the signed webhook three times, like Stripe can.
     */
    public function pay(string $session, PaymentGateway $gateway, StripeWebhookReceiver $receiver): View
    {
        /** @var FakeGateway $gateway */
        $checkout = $this->session($gateway, $session);
        $checkout['payment_status'] = 'paid';
        $gateway->putSession($checkout);

        $event = StripeEvents::make('checkout.session.completed', $checkout);
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $deliveries = [];

        foreach (range(1, 3) as $attempt) {
            $signature = StripeSignature::header($payload, (string) config('ulvi.stripe.webhook_secret'));

            $deliveries[] = [
                'type' => $event['type'],
                'event_id' => $event['id'],
                'attempt' => $attempt,
                'duplicate' => $receiver->receive($payload, $signature),
            ];
        }

        return view('demo.paid', ['session' => $checkout, 'deliveries' => $deliveries]);
    }

    /**
     * @return array<string, mixed>
     */
    private function session(PaymentGateway $gateway, string $id): array
    {
        abort_unless($gateway instanceof FakeGateway, 404);

        return $gateway->session($id) ?? abort(404);
    }
}

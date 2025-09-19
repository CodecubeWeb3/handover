<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Services\StripeEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeEventProcessor $processor): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! $signature) {
            abort(400, 'Missing signature header');
        }

        try {
            $event = $processor->constructEvent($payload, $signature);
        } catch (SignatureVerificationException $exception) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $processor->handle($event);

        return response()->json(['received' => true]);
    }
}
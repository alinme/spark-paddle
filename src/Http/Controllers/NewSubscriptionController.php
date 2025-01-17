<?php

namespace Spark\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spark\Contracts\Actions\GeneratesCheckoutSessions;
use Spark\Spark;
use Spark\ValidPlan;

class NewSubscriptionController
{
    use RetrievesBillableModels;

    /**
     * Generate a Paddle pay link for creating a new subscription.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $billable = $this->billable();

        $request->validate([
            'plan' => ['required', new ValidPlan($request->billableType)],
        ]);

        $subscription = $billable->subscription();

        if ($subscription && $subscription->valid()) {
            throw ValidationException::withMessages([
                'plan' => __('You are already subscribed.'),
            ]);
        }

        Spark::ensurePlanEligibility(
            $billable,
            Spark::plans($billable->sparkConfiguration('type'))
                ->where('id', $request->plan)
                ->first()
        );

        /** @var \Laravel\Paddle\Checkout */
        $checkout = app(GeneratesCheckoutSessions::class)->generateCheckoutSession(
            $billable,
            $request->plan
        );

        return response()->json([
            'checkout' => [
                'customer' => ['id' => $checkout->getCustomer()->paddle_id],
                'items' => $checkout->getItems(),
                'custom_data' => $checkout->getCustomData(),
            ],
        ]);
    }
}

<?php

namespace Spark\Http\Controllers;

use Illuminate\Validation\ValidationException;
use Spark\Spark;

class ResumeSubscriptionController
{
    use RetrievesBillableModels;

    /**
     * Resume the billable's canceled subscription.
     *
     * @return void
     */
    public function __invoke()
    {
        $billable = $this->billable();

        $subscription = $billable->subscription('default');

        if (! $subscription) {
            throw ValidationException::withMessages([
                '*' => __('This account does not have an active subscription.'),
            ]);
        }

        if (! $subscription->onGracePeriod()) {
            throw ValidationException::withMessages([
                '*' => __('This subscription has expired and cannot be resumed. Please create a new subscription.'),
            ]);
        }

        $subscription->stopCancelation();

        if (Spark::chargesPerSeat(request('billableType'))) {
            $subscription->updateQuantity(
                Spark::seatCount(request('billableType'), $billable)
            );
        }

        session(['spark.flash.success' => __('Your subscription was successfully resumed.')]);
    }
}

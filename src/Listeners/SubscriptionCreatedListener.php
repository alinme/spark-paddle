<?php

namespace Spark\Listeners;

use Laravel\Paddle\Events\SubscriptionCreated;

class SubscriptionCreatedListener
{
    /**
     * Handle the event.
     */
    public function handle(SubscriptionCreated $event): void
    {
        $billable = $event->billable;

        $billable->customer->update([
            'pending_checkout_id' => null,
        ]);

        $billable->subscriptions()
            ->where('paddle_id', '!=', $event->subscription->paddle_id)
            ->notCanceled()
            ->each(function ($subscription) {
                $subscription->cancelNow();
            });
    }
}

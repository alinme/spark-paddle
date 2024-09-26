<?php

namespace Spark;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\Subscription;
use Laravel\Paddle\Transaction;

class FrontendState
{
    /**
     * Get the data should be shared with the frontend.
     *
     * @param  string  $type
     * @return array
     */
    public function current($type, Model $billable)
    {
        /** @var \Laravel\Paddle\Subscription */
        $subscription = $billable->subscription();

        $plans = static::getPlans($type, $billable);

        $plan = $subscription && $subscription->valid()
            ? $plans->firstWhere('id', $subscription->items()->first()->price_id)
            : null;

        $lastPayment = self::subscriptionIsActive($subscription) || self::subscriptionIsPastDue($subscription)
            ? optional($subscription->lastPayment())->toArray()
            : null;

        if ($lastPayment) {
            $lastPayment['date'] = Carbon::make($lastPayment['date'])->format(config('spark.date_format', 'F j, Y'));
        }

        $nextPayment = self::subscriptionIsActive($subscription) || self::subscriptionIsPastDue($subscription)
            ? optional($subscription->nextPayment())->toArray()
            : null;

        if ($nextPayment) {
            $nextPayment['date'] = Carbon::make($nextPayment['date'])->translatedFormat(config('spark.date_format', 'F j, Y'));
        }

        $user = Auth::user();

        return [
            'appLogo' => static::logo(),
            'appName' => config('app.name', 'Laravel'),
            'sandbox' => config('cashier.sandbox'),
            'billableId' => (string) $billable->id,
            'billableName' => $billable->name,
            'billableType' => $type,
            'brandColor' => $this->brandColor(),
            'clientSideToken' => config('cashier.client_side_token'),
            'dashboardUrl' => static::dashboardUrl(),
            'defaultInterval' => config('spark.billables.'.$type.'.default_interval', 'monthly'),
            'genericTrialEndsAt' => $billable->onGenericTrial()
                ? $billable->customer->trial_ends_at->translatedFormat(config('spark.date_format', 'F j, Y'))
                : null,
            'invoices' => static::transactions($billable),
            'lastPayment' => $lastPayment,
            'message' => request('message', ''),
            'monthlyPlans' => $plans->where('interval', 'monthly')->where('active', true)->values(),
            'nextPayment' => $nextPayment,
            'paddleSellerId' => (int) config('cashier.seller_id'),
            'plan' => $plan,
            'pwAuth' => config('cashier.retain_key'),
            'pwCustomer' => ($customer = $billable->customer) ? $customer->paddle_id : null,
            'seatName' => Spark::seatName($type),
            'state' => static::state($billable, $subscription),
            'termsUrl' => $this->termsUrl(),
            'userAvatar' => isset($user['profile_photo_url']) ? $user->profile_photo_url : null,
            'userName' => $user->name,
            'yearlyPlans' => $plans->where('interval', 'yearly')->where('active', true)->values(),
        ];
    }

    /**
     * Get the logo that is configured for the billing portal.
     *
     * @return string|null
     */
    protected static function logo()
    {
        $logo = config('spark.brand.logo');

        if (! empty($logo) && file_exists(realpath($logo))) {
            return file_get_contents(realpath($logo));
        }

        return $logo;
    }

    /**
     * Get the brand color for the application.
     *
     * @return string
     */
    protected function brandColor()
    {
        $color = config('spark.brand.color', 'bg-gray-800');

        return strpos($color, '#') === 0 ? 'bg-custom-hex' : $color;
    }

    /**
     * Get the subscription plans.
     *
     * @param  string  $type
     * @param  \Illuminate\Database\Eloquent\Model  $billable
     * @return \Illuminate\Support\Collection
     */
    protected function getPlans($type, $billable)
    {
        $plans = Spark::plans($type);

        $previews = $this->getPricePreviews($plans);

        /** @var \Laravel\Paddle\PricePreview $preview */
        foreach ($previews as $preview) {
            if ($sparkPlan = $plans->where('id', $preview->price['id'])->first()) {
                $sparkPlan->priceIncludesVat = $preview->price['tax_mode'] !== 'external';

                $sparkPlan->price = $sparkPlan->priceIncludesVat ? $preview->total() : $preview->subtotal();

                $sparkPlan->currency = $preview->currency()->getCode();
            }
        }

        return $plans;
    }

    /**
     * Get the price previews from Paddle.
     *
     * @param  \Illuminate\Support\Collection  $plans
     * @return \Illuminate\Support\Collection
     */
    protected function getPricePreviews($plans)
    {
        return Cashier::previewPrices($plans->map->id->toArray(), [
            'customer_ip_address' => request()->ip(),
        ]);
    }

    /**
     * Get the current subscription state.
     *
     * @return string
     */
    protected static function state(Model $billable, ?Subscription $subscription)
    {
        if (static::pendingCheckout($billable, $subscription)) {
            return 'pending';
        }

        if ($subscription && $subscription->onGracePeriod()) {
            return 'onGracePeriod';
        }

        if (self::subscriptionIsPastDue($subscription)) {
            return 'past_due';
        }

        if (self::subscriptionIsActive($subscription)) {
            return 'active';
        }

        return 'none';
    }

    /**
     * Determine if the subscription is in an "active" state.
     *
     * @return bool
     */
    protected static function subscriptionIsActive(?Subscription $subscription)
    {
        return $subscription &&
               $subscription->active() &&
               ! $subscription->onGracePeriod();
    }

    /**
     * Determine if the subscription is in a "past_due" state.
     *
     * @return bool
     */
    protected static function subscriptionIsPastDue(?Subscription $subscription)
    {
        return $subscription &&
               $subscription->pastDue();
    }

    /**
     * Determine if we are waiting for webhooks to arrive.
     *
     * @return bool
     */
    protected static function pendingCheckout(Model $billable, ?Subscription $subscription)
    {
        if (self::subscriptionIsActive($subscription) || self::subscriptionIsPastDue($subscription)) {
            $billable->customer->update([
                'pending_checkout_id' => null,
            ]);

            return false;
        }

        return
            $billable->customer &&
            $billable->customer->pending_checkout_id;
    }

    /**
     * List all transactions of the given billable.
     *
     * @return array
     */
    protected static function transactions(Model $billable)
    {
        return $billable->transactions()
            ->where('total', '>', 0)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'total' => $transaction->total(),
                'billed_at' => $transaction->billed_at->translatedFormat(config('spark.date_format', 'F j, Y')),
                'invoice_url' => route('spark.invoices.download', [
                    $billable->sparkConfiguration()['type'],
                    $billable->id,
                    $transaction->id,
                ]),
            ]);
    }

    /**
     * Get the URL of the billing dashboard.
     *
     * @return string
     */
    protected static function dashboardUrl()
    {
        if ($dashboardUrl = config('spark.dashboard_url')) {
            return $dashboardUrl;
        }

        return app('router')->has('dashboard') ? route('dashboard') : '/';
    }

    /**
     * Get the URL of the "terms of service" page.
     *
     * @return string
     */
    protected function termsUrl()
    {
        if ($termsUrl = config('spark.terms_url')) {
            return $termsUrl;
        }

        return app('router')->has('terms.show') ? route('terms.show') : null;
    }
}

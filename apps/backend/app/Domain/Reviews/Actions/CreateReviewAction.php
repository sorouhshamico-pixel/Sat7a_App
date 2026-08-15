<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Drivers\Models\Driver;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Providers\Models\Provider;
use App\Domain\Reviews\Exceptions\ReviewException;
use App\Domain\Reviews\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * A customer rates their own completed order — one review per order (the
 * unique `order_id` constraint is the backstop; this is the friendlier
 * pre-check). Recalculates the provider's and (if one was assigned)
 * driver's cached `rating` column from every review on record — a simple
 * average, not weighted/decayed, kept as a stored aggregate (mirroring
 * `drivers.rating`'s existing shape from Phase 4) rather than computed on
 * every read, since it's read far more often than it changes.
 */
class CreateReviewAction
{
    /**
     * @throws ReviewException
     */
    public function handle(Order $order, int $rating, ?string $comment): Review
    {
        if ($order->status !== OrderStatus::Completed) {
            throw ReviewException::orderNotReviewable();
        }

        if (Review::query()->where('order_id', $order->id)->exists()) {
            throw ReviewException::alreadyReviewed();
        }

        return DB::transaction(function () use ($order, $rating, $comment): Review {
            $review = new Review([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'provider_id' => $order->assigned_provider_id,
                'driver_id' => $order->assigned_driver_id,
                'rating' => $rating,
                'comment' => $comment,
            ]);
            $review->save();

            $this->recalculateProviderRating($order->assigned_provider_id);

            if ($order->assigned_driver_id !== null) {
                $this->recalculateDriverRating($order->assigned_driver_id);
            }

            return $review;
        });
    }

    private function recalculateProviderRating(int $providerId): void
    {
        $average = Review::query()->where('provider_id', $providerId)->avg('rating');

        Provider::query()->where('id', $providerId)->update(['rating' => round((float) $average, 2)]);
    }

    private function recalculateDriverRating(int $driverId): void
    {
        $average = Review::query()->where('driver_id', $driverId)->avg('rating');

        Driver::query()->where('id', $driverId)->update(['rating' => round((float) $average, 2)]);
    }
}

"use client";

import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiGet } from "@/lib/api/client";
import { Card } from "@/components/ui/card";
import { Alert } from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";
import { Pagination } from "@/components/pagination";
import type { ReviewItem } from "@/lib/types/review";

const PAGE_SIZE = 20;

export default function ProviderReviewsPage() {
  const [page, setPage] = useState(1);

  const reviewsQuery = useQuery({
    queryKey: ["provider-reviews", page],
    queryFn: () =>
      apiGet<{ reviews: ReviewItem[] }>("providers/me/reviews", {
        page: String(page),
        per_page: String(PAGE_SIZE),
      }),
  });

  if (reviewsQuery.isLoading) return <Spinner />;
  if (reviewsQuery.isError) return <Alert>تعذّر تحميل التقييمات.</Alert>;

  const reviews = reviewsQuery.data?.data.reviews ?? [];

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-bold text-gray-900">التقييمات</h1>

      <Card>
        {reviews.length === 0 && <p className="text-sm text-gray-500">لا توجد تقييمات بعد.</p>}
        <div className="flex flex-col gap-3">
          {reviews.map((review) => (
            <div
              key={review.id}
              className="border-t border-gray-100 pt-3 first:border-t-0 first:pt-0"
            >
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-gray-900">{review.rating} / 5</p>
                <p className="text-xs text-gray-500">
                  {new Date(review.created_at).toLocaleDateString("ar-SA")}
                </p>
              </div>
              {review.comment && <p className="mt-1 text-sm text-gray-600">{review.comment}</p>}
            </div>
          ))}
        </div>
      </Card>

      <Pagination
        page={page}
        onPageChange={setPage}
        total={Number(reviewsQuery.data?.meta.total ?? 0)}
        itemCount={reviews.length}
        pageSize={PAGE_SIZE}
      />
    </div>
  );
}

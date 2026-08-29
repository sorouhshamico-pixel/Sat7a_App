"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { apiGet } from "@/lib/api/client";
import { Badge } from "@/components/ui/badge";
import { Spinner } from "@/components/ui/spinner";
import { Alert } from "@/components/ui/alert";
import { orderStatusLabel, orderStatusTone } from "@/lib/orders";
import type { OrderListItem } from "@/lib/types/order";

export default function CustomerOrdersPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["customer-orders"],
    queryFn: () => apiGet<{ orders: OrderListItem[] }>("customers/me/orders"),
  });

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">
      <h1 className="text-xl font-bold text-gray-900">طلباتي</h1>

      {isLoading && <Spinner />}
      {isError && <Alert>تعذّر تحميل الطلبات.</Alert>}

      {data && data.data.orders.length === 0 && (
        <p className="text-sm text-gray-500">
          لا توجد طلبات بعد.{" "}
          <Link href="/" className="text-blue-600 underline">
            اطلب سطحة الآن
          </Link>
          .
        </p>
      )}

      <div className="flex flex-col gap-3">
        {data?.data.orders.map((order) => (
          <Link
            key={order.id}
            href={`/orders/${order.id}`}
            className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
          >
            <div className="flex items-center justify-between">
              <span className="font-medium text-gray-900">{order.pickup.formatted_address}</span>
              <Badge tone={orderStatusTone(order.status)}>{orderStatusLabel(order.status)}</Badge>
            </div>
            <p className="mt-1 text-sm text-gray-500">إلى {order.dropoff.formatted_address}</p>
            <p className="mt-1 text-xs text-gray-400">
              {new Date(order.created_at).toLocaleString("ar-SA")}
            </p>
          </Link>
        ))}
      </div>
    </div>
  );
}

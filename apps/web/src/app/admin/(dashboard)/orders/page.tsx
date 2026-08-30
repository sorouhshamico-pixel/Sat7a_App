"use client";

import Link from "next/link";
import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { apiGet } from "@/lib/api/client";
import { Badge } from "@/components/ui/badge";
import { Spinner } from "@/components/ui/spinner";
import { Alert } from "@/components/ui/alert";
import { Pagination } from "@/components/pagination";
import { orderStatusLabel, orderStatusTone, ORDER_STATUS_LABELS } from "@/lib/orders";
import type { OrderListItem } from "@/lib/types/order";

interface OrdersResponse {
  orders: OrderListItem[];
}

const PAGE_SIZE = 20;

export default function OrdersListPage() {
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);

  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin-orders", status, page],
    queryFn: () =>
      apiGet<OrdersResponse>("admin/orders", {
        ...(status ? { status } : {}),
        page: String(page),
        per_page: String(PAGE_SIZE),
      }),
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">الطلبات</h1>

        <select
          value={status}
          onChange={(event) => {
            setStatus(event.target.value);
            setPage(1);
          }}
          className="rounded-md border border-gray-300 px-3 py-2 text-sm"
        >
          <option value="">كل الحالات</option>
          {Object.entries(ORDER_STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      {isLoading && <Spinner />}
      {isError && <Alert>تعذّر تحميل الطلبات.</Alert>}

      {data && (
        <div className="overflow-x-auto rounded-lg border border-gray-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-right text-gray-600">
              <tr>
                <th className="px-4 py-2 font-medium">رقم الطلب</th>
                <th className="px-4 py-2 font-medium">الحالة</th>
                <th className="px-4 py-2 font-medium">نوع الخدمة</th>
                <th className="px-4 py-2 font-medium">من</th>
                <th className="px-4 py-2 font-medium">إلى</th>
                <th className="px-4 py-2 font-medium">تاريخ الإنشاء</th>
              </tr>
            </thead>
            <tbody>
              {data.data.orders.map((order) => (
                <tr key={order.id} className="border-t border-gray-100 hover:bg-gray-50">
                  <td className="px-4 py-2">
                    <Link
                      href={`/admin/orders/${order.id}`}
                      className="font-medium text-blue-600 hover:underline"
                    >
                      {order.id.slice(0, 8)}
                    </Link>
                  </td>
                  <td className="px-4 py-2">
                    <Badge tone={orderStatusTone(order.status)}>
                      {orderStatusLabel(order.status)}
                    </Badge>
                  </td>
                  <td className="px-4 py-2">{order.service_type}</td>
                  <td className="px-4 py-2 text-gray-600">{order.pickup.formatted_address}</td>
                  <td className="px-4 py-2 text-gray-600">{order.dropoff.formatted_address}</td>
                  <td className="px-4 py-2 text-gray-600">
                    {new Date(order.created_at).toLocaleString("ar-SA")}
                  </td>
                </tr>
              ))}
              {data.data.orders.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-center text-gray-500">
                    لا توجد طلبات مطابقة.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {data && (
        <Pagination
          page={page}
          onPageChange={setPage}
          total={Number(data.meta.total ?? 0)}
          itemCount={data.data.orders.length}
          pageSize={PAGE_SIZE}
        />
      )}
    </div>
  );
}

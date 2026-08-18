import Link from "next/link";
import { Card, CardTitle } from "@/components/ui/card";

export default function DashboardHomePage() {
  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-bold text-gray-900">لوحة التحكم</h1>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Link href="/admin/orders">
          <Card className="transition-shadow hover:shadow-md">
            <CardTitle>الطلبات</CardTitle>
            <p className="text-sm text-gray-600">
              عرض جميع الطلبات، حالة كل طلب، وإعادة توزيعها يدوياً عند الحاجة.
            </p>
          </Card>
        </Link>

        <Link href="/admin/disputes">
          <Card className="transition-shadow hover:shadow-md">
            <CardTitle>النزاعات</CardTitle>
            <p className="text-sm text-gray-600">مراجعة نزاعات العملاء وحلّها أو رفضها.</p>
          </Card>
        </Link>
      </div>
    </div>
  );
}

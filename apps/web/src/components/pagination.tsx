"use client";

import { Button } from "@/components/ui/button";

// Shared control for every admin/provider list backed by a paginated
// backend endpoint (`current_page`/`per_page`/`total` in the response
// envelope's `meta`, see docs/API_SPECIFICATION.md). Extracted in Phase
// 24 (docs/PERFORMANCE.md) — several list pages already had the backend
// support for this but no UI to reach page 2+, a real usability gap as
// data grows, not just a cosmetic one. `hasNextPage` is derived from
// whether the current page came back full, not a strict `total` math
// comparison — correct even if `total` is an approximation and cheaper
// than computing `Math.ceil(total / perPage)` at every call site.
export function Pagination({
  page,
  onPageChange,
  total,
  itemCount,
  pageSize,
}: {
  page: number;
  onPageChange: (page: number) => void;
  total: number;
  itemCount: number;
  pageSize: number;
}) {
  const hasNextPage = itemCount >= pageSize;

  return (
    <div className="flex items-center justify-between text-sm text-gray-600">
      <span>
        الإجمالي: {total} — صفحة {page}
      </span>
      <div className="flex gap-2">
        <Button
          variant="secondary"
          onClick={() => onPageChange(Math.max(1, page - 1))}
          disabled={page === 1}
        >
          السابق
        </Button>
        <Button variant="secondary" onClick={() => onPageChange(page + 1)} disabled={!hasNextPage}>
          التالي
        </Button>
      </div>
    </div>
  );
}

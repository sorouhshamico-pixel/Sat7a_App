"use client";

import { ApiRequestError, type ApiEnvelope } from "./types";

// Client components only ever call our own /api/backend/* proxy — never
// the Laravel API directly (see src/app/api/backend/[...path]/route.ts) —
// so the session token never has to reach browser JS.
async function request<T>(
  path: string,
  init?: RequestInit,
): Promise<{ data: T; meta: Record<string, unknown> }> {
  const response = await fetch(`/api/backend/${path.replace(/^\/+/, "")}`, {
    ...init,
    headers: { "Content-Type": "application/json", ...init?.headers },
  });

  const envelope = (await response.json()) as ApiEnvelope<T>;

  if (!response.ok || envelope.errors) {
    throw new ApiRequestError(
      response.status,
      envelope.errors ?? [{ code: "UNKNOWN", message: "Request failed" }],
    );
  }

  return { data: envelope.data as T, meta: envelope.meta };
}

export function apiGet<T>(path: string, searchParams?: Record<string, string>) {
  const query = searchParams ? `?${new URLSearchParams(searchParams).toString()}` : "";

  return request<T>(`${path}${query}`, { method: "GET" });
}

export function apiPost<T>(path: string, body?: unknown) {
  return request<T>(path, { method: "POST", body: body ? JSON.stringify(body) : undefined });
}

import "server-only";
import type { ApiEnvelope } from "./types";

// Every call to Laravel goes through here — never `fetch()`'d directly from
// a route handler — so the base URL and JSON headers stay in one place
// (see docs/OPERATIONS_COMMAND_CENTER.md).
function backendUrl(path: string): string {
  const base = process.env.BACKEND_API_URL ?? "http://localhost:8000";

  return `${base}/api/v1/${path.replace(/^\/+/, "")}`;
}

interface CallOptions {
  method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE";
  token?: string | null;
  body?: unknown;
  searchParams?: URLSearchParams;
}

export interface BackendResult<T> {
  status: number;
  envelope: ApiEnvelope<T>;
}

export async function callBackend<T = unknown>(
  path: string,
  options: CallOptions = {},
): Promise<BackendResult<T>> {
  const url = new URL(backendUrl(path));

  if (options.searchParams) {
    url.search = options.searchParams.toString();
  }

  const headers: Record<string, string> = {
    Accept: "application/json",
  };

  if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  if (options.token) {
    headers.Authorization = `Bearer ${options.token}`;
  }

  const response = await fetch(url, {
    method: options.method ?? "GET",
    headers,
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    cache: "no-store",
  });

  const envelope = (await response.json()) as ApiEnvelope<T>;

  return { status: response.status, envelope };
}

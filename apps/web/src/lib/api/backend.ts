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

  const isFormData = options.body instanceof FormData;

  // A FormData body (document upload — see
  // src/lib/api/client.ts's apiUpload) must NOT get a JSON Content-Type:
  // fetch needs to set its own multipart boundary, and passing FormData
  // straight through here re-encodes it with a fresh one.
  if (options.body !== undefined && !isFormData) {
    headers["Content-Type"] = "application/json";
  }

  if (options.token) {
    headers.Authorization = `Bearer ${options.token}`;
  }

  const response = await fetch(url, {
    method: options.method ?? "GET",
    headers,
    body:
      options.body === undefined
        ? undefined
        : isFormData
          ? (options.body as FormData)
          : JSON.stringify(options.body),
    cache: "no-store",
  });

  const envelope = (await response.json()) as ApiEnvelope<T>;

  return { status: response.status, envelope };
}

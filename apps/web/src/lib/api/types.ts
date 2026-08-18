// Mirrors App\Http\Responses\ApiResponse (apps/backend) — every /api/v1
// response uses this envelope, success or error, so the frontend never has
// to branch on shape (see docs/API_SPECIFICATION.md).

export interface ApiErrorItem {
  code: string;
  message: string;
}

export interface ApiEnvelope<T> {
  data: T | null;
  meta: Record<string, unknown>;
  errors: ApiErrorItem[] | null;
}

export class ApiRequestError extends Error {
  constructor(
    public readonly status: number,
    public readonly errors: ApiErrorItem[],
  ) {
    super(errors[0]?.message ?? "Request failed");
    this.name = "ApiRequestError";
  }

  get code(): string | undefined {
    return this.errors[0]?.code;
  }
}

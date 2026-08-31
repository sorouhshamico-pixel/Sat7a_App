# Provider Web App

## Status

Phase 20 — implemented (`apps/web/src/app/provider`).

## Scope

The provider-facing counterpart to Phase 19's customer app, covering the rest of
`docs/PRODUCT_REQUIREMENTS.md`'s "Core provider journey" (steps 6 onward — registration itself,
steps 1-5, stays a backend-only/sales-assisted flow, not a self-serve web form; see Not yet in
this phase). One app serves every provider-staff role from a single login — owner, fleet
manager, and driver all authenticate identically and land on the same shell; the backend's own
permission gates are the real boundary, and a denied action surfaces its 403 inline, the same
decision Phase 17 made for the admin app. Built: business profile (view/edit), fleet (list, add,
assign driver, change status), drivers (list, add, toggle availability), documents (list,
upload), bank account (view/update), balance + settlements (read-only), reviews (read-only), and
a driver-specific "My Trips" screen (dispatch-offer inbox, accept/reject, trip-status advance,
live location sharing).

## Authentication

Provider-staff accounts (owner, fleet manager, driver) all authenticate via the same
single-step phone+OTP flow as customers (`App\Domain\Authentication\Actions\VerifyOtpAction`,
`user_type: "provider_staff"`) — a fully-privileged 30-day Sanctum token comes back directly from
OTP verify, no MFA step. Unlike the customer flow, an unrecognized phone is a **hard failure**
here, never a silent signup: provider-staff accounts are always provisioned in advance (by their
provider owner through the Drivers screen, or by an admin), matching
`VerifyOtpAction`'s own doc comment about avoiding impersonation. `src/app/provider/login/`
mirrors `src/app/(customer)/login/` almost exactly, posting to a separate
`/api/auth/provider/otp/{send,verify}` pair that writes a `provider_session` cookie instead of
`customer_session`.

## Four-tier backend proxy

Phase 19 built a three-tier proxy (admin / customer / public). This phase extended it to four:

- **Admin** (`admin/...`) — `admin_session` cookie, unchanged.
- **Provider-staff** (`providers/...`, `drivers/...`) — new `provider_session` cookie.
- **Customer** (`customers/...`) — `customer_session` cookie, unchanged.
- **Shared** (`documents/...`, `notifications/...`) — reachable by more than one account type
  (a provider downloads their own compliance documents, any account type reads its own
  notification inbox), so the proxy tries whichever of the three session cookies is actually
  present, in that order.
- **Public** — unchanged.

`src/proxy.ts` (edge middleware) gained a third independent gate for `/provider/*`, structurally
identical to the existing `/admin/*` and customer-route-tree gates: redirect to
`/provider/login?next=...` when the cookie is missing, redirect away from the login page when
it's already present.

The proxy also gained **PATCH, PUT, and DELETE** handlers (previously GET/POST only — Phase 19
never needed them) and **multipart passthrough**: a request whose Content-Type is
`multipart/form-data` is read via `request.formData()` instead of `request.json()`, and
`src/lib/api/backend.ts`'s `callBackend` skips JSON-stringifying (and skips forcing a JSON
Content-Type header) when the body is a `FormData` instance, letting `fetch` set its own
multipart boundary. This is what makes document upload (`src/components` — actually
`src/app/provider/(dashboard)/documents/page.tsx`, via a new `apiUpload()` client helper) work
without a bespoke route handler.

Any future phase adding a fifth session type should extend this same tiered-proxy pattern.

## Driver "my active order" gap

There is no `GET` endpoint for a driver to fetch their own currently-assigned order — only
`POST drivers/me/dispatch-offers/{id}/accept` and `POST drivers/me/orders/{id}/status`, each of
which happens to return the full `OrderResource` as a side effect of the action it performs. This
is a real backend gap (the same class Phase 11 hit — "nothing had built yet," not a bug), not a
frontend shortcut. `src/app/provider/(dashboard)/trips/page.tsx` works around it by caching the
last-known active order client-side (`localStorage`, key `riyadh_tow_active_order`), updated
every time accept/advance returns a fresh copy. This means:

- A page reload correctly restores the in-progress trip (verified live — see below).
- The cache goes **stale** if the order advances through any channel other than this same
  browser's own UI (an admin manual override, a different device) — there is no way for the page
  to notice and resync until the driver's own UI performs another mutation. Acceptable for this
  phase; a real fix needs the missing GET endpoint.
- It never appears on a different browser/device — this is client-local, not server state.

## Fleet status transitions

`src/lib/fleet.ts`'s `TOW_TRUCK_NEXT_STATUSES` mirrors `App\Domain\Fleet\Enums\
TowTruckStatus::allowedTransitions()`, same "client-side mirror of the backend's state machine"
approach as Phase 18's `SETTLEMENT_NEXT_STATUS`. It's deliberately a **strict subset** of what
the enum nominally allows: only the `offline`/`available`/`maintenance`/`unavailable` subgraph is
offered as buttons, since `reserved`/`en_route`/`arrived`/`loading`/`on_trip` are set by the
dispatch/trip system and `suspended` is compliance-only (per the enum's own doc comment) — a
provider is never offered a transition the backend would reject.

## Location sharing

The driver's location-sharing toggle uses the browser Geolocation API (`getCurrentPosition` on a
10-second interval, not `watchPosition`) posting to `drivers/me/location`, well under the
backend's 60/min rate limit for that endpoint. `RecordLocationPingAction` always updates the tow
truck's last-known position regardless of whether the driver has an active order — it's also
what feeds dispatch's nearby-candidate search — so the toggle is offered independent of trip
state.

## Real-UI verification (no new bugs found)

Every mutation was exercised against a live backend and a real rendered page (Playwright), not
just typechecked or unit-tested, continuing the standing practice from Phases 17-19:

- Provider profile edit (PATCH) — value persisted and re-rendered.
- Fleet: assign a driver (PATCH) — confirmed via a full page reload that the assignment
  persisted server-side, not just in local component state.
- Fleet: change tow-truck status (PATCH) — `available → offline` transition, verified the next
  page load showed the new status and the new (different) set of next-status buttons.
- Document upload (multipart POST) — first attempt used a fake `.png` (plain text renamed),
  correctly rejected by Laravel's content-based `mimes:` validation ("The file field must be a
  file of type: pdf, jpg, jpeg, png") with the error surfaced inline; a real 1x1 PNG then
  succeeded, appearing in the list with a "قيد المراجعة" (pending) badge. This exercised the new
  multipart proxy path end to end.
- Bank account save (PUT) — value persisted, "بانتظار التوثيق" (pending verification) badge
  appeared.
- Driver flow: created a real order near the test truck's location via the API, watched a
  dispatch offer appear in the driver's inbox, accepted it through the UI, advanced the trip
  through two real status transitions (buttons correctly changed at each step), enabled location
  sharing (a mocked Playwright geolocation position was posted and landed in the database — the
  tow truck's `current_latitude`/`current_longitude`/`last_location_at` columns were checked
  directly), then reloaded the page and confirmed the active-trip card survived via the
  `localStorage` cache described above.

Two apparent failures during this process turned out to be test-authoring mistakes, not app
bugs: a locator matching a `<option>` tag's text (which Playwright correctly reports as
"hidden") instead of checking the select's resolved value, and a locator for a button that
doesn't exist because the truck's current status is shown as a read-only `Badge`, not a
button — only the *next* legal statuses render as buttons.

## Not yet in this phase

- No self-serve provider **registration** web form — `POST /providers/register` exists and is
  public, but onboarding a brand-new provider through the web app itself wasn't built; this phase
  only covers an already-provisioned provider's staff logging in.
- No document preview/download in the provider app (same gap Phase 18 left on the admin side —
  the backend endpoint returns raw bytes, not JSON, and wasn't wired through a byte-streaming
  proxy).
- No settlement-batch generation from the provider side (that's a finance-staff action from the
  admin app, per `docs/FINANCE_COMPLIANCE_ADMIN.md`) — a provider only views progress.
- ~~No pagination on any list screen~~ — closed for Settlements and Reviews in Phase 24
  (`docs/PERFORMANCE.md`), the two screens here with genuinely unbounded lists; this section was
  never updated to reflect it. Fleet/Drivers/Documents remain unpaginated deliberately — small,
  bounded lists (one provider's own fleet/staff/documents) that don't need it.
- No self-service driver "go online/offline" toggle distinct from location sharing — availability
  is still only settable by the owner/fleet manager (`drivers/{id}/availability`), matching what
  the backend actually exposes.
- No real-time WebSocket updates on the dispatch-offer inbox — 10-second polling only, same
  reasoning as Phase 19's order tracking.

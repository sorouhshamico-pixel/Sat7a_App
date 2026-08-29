import Link from "next/link";

// Served by the service worker (public/sw.js) when a navigation request
// fails with no cached copy of the requested page — see docs/PWA.md
// §Offline fallback. Deliberately standalone (not nested under the
// (customer) route group) since it must render correctly for ANY
// installed app on this origin (customer or provider) and must not
// depend on anything beyond what the service worker precaches at
// install time.
export default function OfflinePage() {
  return (
    <div className="mx-auto flex min-h-screen max-w-sm flex-col items-center justify-center gap-4 p-6 text-center">
      <h1 className="text-xl font-bold text-gray-900">أنت غير متصل بالإنترنت</h1>
      <p className="text-sm text-gray-600">
        تعذّر تحميل هذه الصفحة. تحقق من اتصالك بالإنترنت ثم أعد المحاولة.
      </p>
      <Link
        href="/"
        className="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        إعادة المحاولة
      </Link>
    </div>
  );
}

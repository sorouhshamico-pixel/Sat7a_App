import type { Metadata } from "next";
import { Tajawal } from "next/font/google";
import { ServiceWorkerRegistration } from "@/components/service-worker-registration";
import { SITE_URL } from "@/lib/site";
import "./globals.css";

// Arabic is the primary locale for this app (see docs/ARCHITECTURE.md §8);
// Tajawal covers both Arabic and Latin glyphs so English strings/numbers
// inside an RTL layout still render cleanly.
const tajawal = Tajawal({
  variable: "--font-tajawal",
  subsets: ["arabic", "latin"],
  weight: ["400", "500", "700"],
});

// title.template applies to every page under this layout that sets its
// own title (none currently do — every screen but the homepage is
// transactional/behind a login and excluded from search via
// src/app/robots.ts, so a distinct <title> per screen wasn't judged
// worth the client-component refactor it would need; see
// docs/MARKETING_SEO.md). The default is what the homepage — the one
// page actually meant to rank — and every social-share unfurl uses.
const SITE_NAME = "منصة سطحات الرياض";
const SITE_DESCRIPTION =
  "اطلب سطحة في الرياض خلال دقائق — خدمة نقل وانتشال مركبات في جميع أنحاء الرياض، بأسعار واضحة وتتبع مباشر للطلب.";

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  title: { default: SITE_NAME, template: `%s | ${SITE_NAME}` },
  description: SITE_DESCRIPTION,
  openGraph: {
    title: SITE_NAME,
    description: SITE_DESCRIPTION,
    url: "/",
    siteName: SITE_NAME,
    locale: "ar_SA",
    type: "website",
  },
  twitter: {
    card: "summary_large_image",
    title: SITE_NAME,
    description: SITE_DESCRIPTION,
  },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ar" dir="rtl" className={`${tajawal.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col font-sans">
        <ServiceWorkerRegistration />
        {children}
      </body>
    </html>
  );
}

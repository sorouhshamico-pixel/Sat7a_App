import type { Metadata } from "next";
import { Tajawal } from "next/font/google";
import "./globals.css";

// Arabic is the primary locale for this app (see docs/ARCHITECTURE.md §8);
// Tajawal covers both Arabic and Latin glyphs so English strings/numbers
// inside an RTL layout still render cleanly.
const tajawal = Tajawal({
  variable: "--font-tajawal",
  subsets: ["arabic", "latin"],
  weight: ["400", "500", "700"],
});

export const metadata: Metadata = {
  title: "منصة سطحات الرياض",
  description: "اطلب سطحة في الرياض خلال دقائق",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="ar" dir="rtl" className={`${tajawal.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col font-sans">{children}</body>
    </html>
  );
}

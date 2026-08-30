import { ImageResponse } from "next/og";

export const alt = "لوحة مزودي الخدمة — سطحات الرياض";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

// Distinct color (emerald) mirroring the provider app's own manifest and
// icons — see docs/PWA.md and docs/MARKETING_SEO.md. Same Latin-wordmark
// trade-off as the root opengraph-image.tsx (no Arabic font bundled).
export default function ProviderOpengraphImage() {
  return new ImageResponse(
    <div
      style={{
        width: "100%",
        height: "100%",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        gap: 32,
        background: "#059669",
        color: "white",
      }}
    >
      <div
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          width: 160,
          height: 160,
          borderRadius: 32,
          background: "rgba(255,255,255,0.15)",
          fontSize: 96,
          fontWeight: 700,
        }}
      >
        P
      </div>
      <div style={{ fontSize: 56, fontWeight: 700 }}>Riyadh Tow — Provider Portal</div>
    </div>,
    { ...size },
  );
}

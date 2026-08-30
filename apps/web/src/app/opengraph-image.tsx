import { ImageResponse } from "next/og";

export const alt = "منصة سطحات الرياض";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

// Latin wordmark, not Arabic text — Satori (the engine behind
// ImageResponse) needs an explicit font supplied via its `fonts` option
// to render Arabic glyphs, and fetching one at request time would add a
// runtime dependency on an external font host for a route that's rarely
// hit (a social-share unfurl bot, not a real user), which this project
// avoids elsewhere too (see docs/PWA.md §Icon design for the same
// reasoning applied to the app icons, and docs/MARKETING_SEO.md for the
// trade-off written out in full). The brand mark and color carry the
// identity; a future pass can revisit this once bundling a real Arabic
// font file is worth the size cost.
export default function OpengraphImage() {
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
        background: "#2563eb",
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
        R
      </div>
      <div style={{ fontSize: 56, fontWeight: 700 }}>Riyadh Tow Platform</div>
    </div>,
    { ...size },
  );
}

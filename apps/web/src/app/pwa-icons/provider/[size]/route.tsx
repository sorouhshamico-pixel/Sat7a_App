import { ImageResponse } from "next/og";
import { NextRequest } from "next/server";

// Distinct color (emerald, vs. the customer app's blue) so the provider
// app reads as its own installed app in a home screen / task switcher —
// see docs/PWA.md §Two installable apps, one origin.
export async function GET(
  _request: NextRequest,
  { params }: { params: Promise<{ size: string }> },
) {
  const { size } = await params;
  const dimension = Number(size) || 192;

  return new ImageResponse(
    <div
      style={{
        width: "100%",
        height: "100%",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        background: "#059669",
        color: "white",
        fontSize: dimension * 0.55,
        fontWeight: 700,
      }}
    >
      P
    </div>,
    { width: dimension, height: dimension },
  );
}

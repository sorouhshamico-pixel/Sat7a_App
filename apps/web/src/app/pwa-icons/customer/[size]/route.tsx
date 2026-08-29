import { ImageResponse } from "next/og";
import { NextRequest } from "next/server";

// Generated (not a static asset) so the manifest's icon list never drifts
// out of sync with the brand color, and so there's no external
// image-generation dependency for a dev box that may not always have
// network access at build time (Satori's default font covers plain Latin
// glyphs without needing an Arabic font file supplied — see
// docs/PWA.md §Icon design).
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
        background: "#2563eb",
        color: "white",
        fontSize: dimension * 0.55,
        fontWeight: 700,
      }}
    >
      R
    </div>,
    { width: dimension, height: dimension },
  );
}

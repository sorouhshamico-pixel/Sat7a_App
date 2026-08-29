import { ImageResponse } from "next/og";

export const size = { width: 32, height: 32 };
export const contentType = "image/png";

export default function ProviderIcon() {
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
        fontSize: 20,
        fontWeight: 700,
      }}
    >
      P
    </div>,
    size,
  );
}

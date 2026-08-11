import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import HomePage from "./page";

describe("HomePage", () => {
  it("renders the platform heading", () => {
    render(<HomePage />);

    expect(
      screen.getByRole("heading", { name: "منصة سطحات الرياض" }),
    ).toBeInTheDocument();
  });
});

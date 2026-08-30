import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { Pagination } from "./pagination";

describe("Pagination", () => {
  it("disables the previous button on page 1", () => {
    render(<Pagination page={1} onPageChange={vi.fn()} total={50} itemCount={20} pageSize={20} />);

    expect(screen.getByRole("button", { name: "السابق" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "التالي" })).toBeEnabled();
  });

  it("disables the next button when the current page came back short of a full page", () => {
    render(<Pagination page={2} onPageChange={vi.fn()} total={27} itemCount={7} pageSize={20} />);

    expect(screen.getByRole("button", { name: "التالي" })).toBeDisabled();
  });

  it("calls onPageChange with page - 1 / page + 1", async () => {
    const onPageChange = vi.fn();
    const user = userEvent.setup();
    render(
      <Pagination page={2} onPageChange={onPageChange} total={50} itemCount={20} pageSize={20} />,
    );

    await user.click(screen.getByRole("button", { name: "التالي" }));
    expect(onPageChange).toHaveBeenCalledWith(3);

    await user.click(screen.getByRole("button", { name: "السابق" }));
    expect(onPageChange).toHaveBeenCalledWith(1);
  });

  it("shows the total and current page", () => {
    render(<Pagination page={3} onPageChange={vi.fn()} total={123} itemCount={20} pageSize={20} />);

    expect(screen.getByText(/123/)).toBeInTheDocument();
    expect(screen.getByText(/3/)).toBeInTheDocument();
  });
});

// Renders structured data as a <script> tag, following Next.js's own
// recommended JSON-LD pattern (node_modules/next/dist/docs/01-app/02-guides/json-ld.md)
// — a native <script> tag, not next/script, since this is data, not
// executable code. The `<` escape guards against XSS if this component
// is ever reused with anything beyond this app's own static, trusted
// content (see the same guide's own warning about JSON.stringify).
export function JsonLd({ data }: { data: Record<string, unknown> }) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, "\\u003c") }}
    />
  );
}

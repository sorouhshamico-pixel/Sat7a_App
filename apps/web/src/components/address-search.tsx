"use client";

import { useEffect, useRef, useState } from "react";
import { apiGet } from "@/lib/api/client";
import { Input } from "@/components/ui/input";

export interface SelectedAddress {
  formatted_address: string;
  latitude: number;
  longitude: number;
}

interface Suggestion {
  place_id: string;
  description: string;
}

// Uses the existing public maps endpoints (App\Http\Controllers\Api\V1\
// Maps\MapsController) rather than an embedded map SDK — no real Google
// Maps API key exists yet (config('services.google_maps.api_key') is
// empty, so the backend itself falls back to a fake adapter — see
// docs/CUSTOMER_WEB_APP.md). A pin-dropping map picker is deferred until a
// real vendor is configured; text-based address search is the standard
// fallback UX in the meantime.
export function AddressSearch({
  label,
  placeholder,
  onSelect,
}: {
  label: string;
  placeholder: string;
  onSelect: (address: SelectedAddress) => void;
}) {
  const [query, setQuery] = useState("");
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const sessionToken = useRef(crypto.randomUUID());
  // Selecting a suggestion sets `query` to its full description (for
  // display) — without this flag, that state change re-triggers the
  // search effect below, immediately reopening the dropdown with a fresh
  // (and different) set of suggestions matching the now-longer text right
  // after the user just picked one.
  const skipNextSearch = useRef(false);

  useEffect(() => {
    if (skipNextSearch.current) {
      skipNextSearch.current = false;
      return;
    }

    const timeout = setTimeout(async () => {
      if (query.length < 3) {
        setSuggestions([]);
        return;
      }

      setLoading(true);
      try {
        const result = await apiGet<{ suggestions: Suggestion[] }>("maps/places/autocomplete", {
          query,
          session_token: sessionToken.current,
        });
        setSuggestions(result.data.suggestions);
        setOpen(true);
      } catch {
        setSuggestions([]);
      } finally {
        setLoading(false);
      }
    }, 300);

    return () => clearTimeout(timeout);
  }, [query]);

  async function handleSelect(suggestion: Suggestion) {
    skipNextSearch.current = true;
    setQuery(suggestion.description);
    setSuggestions([]);
    setOpen(false);

    const result = await apiGet<{
      formatted_address: string;
      coordinates: { latitude: number; longitude: number };
    }>(`maps/places/${encodeURIComponent(suggestion.place_id)}`, {
      session_token: sessionToken.current,
    });

    onSelect({
      formatted_address: result.data.formatted_address,
      latitude: result.data.coordinates.latitude,
      longitude: result.data.coordinates.longitude,
    });
    sessionToken.current = crypto.randomUUID();
  }

  return (
    <div className="relative flex flex-col gap-1">
      <label className="text-sm text-gray-600">{label}</label>
      <Input
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        onFocus={() => suggestions.length > 0 && setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 150)}
        placeholder={placeholder}
        required
      />
      {loading && <span className="absolute top-8 left-2 text-xs text-gray-400">...</span>}
      {open && suggestions.length > 0 && (
        <ul className="absolute top-full z-10 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-md">
          {suggestions.map((suggestion) => (
            <li key={suggestion.place_id}>
              <button
                type="button"
                className="block w-full px-3 py-2 text-right text-sm hover:bg-gray-50"
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => handleSelect(suggestion)}
              >
                {suggestion.description}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

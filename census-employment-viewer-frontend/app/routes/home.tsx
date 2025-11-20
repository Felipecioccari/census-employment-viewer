import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { fetchEmployments } from "../api/employments";
import { fetchStates } from "../api/states";
import { AppShell } from "../components/AppShell";
import { QUARTERS } from "../data/quarters";
import { ALL_STATES_CODE, type StateOption } from "../data/states";
import type { EmploymentError, EmploymentRow } from "../types/employment";
import type { Route } from "./+types/home";

type FormState = {
  states: string[];
  quarter: string;
  breakdownSex: boolean;
};

type SortDirection = "asc" | "desc";

const numberFormatter = new Intl.NumberFormat("en-US");
const defaultFilters: FormState = {
  states: [ALL_STATES_CODE],
  quarter: QUARTERS[0],
  breakdownSex: false,
};

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Employment Viewer" },
    {
      name: "description",
      content:
        "Search U.S. employment counts by state and quarter with optional sex breakdown.",
    },
  ];
}

export default function Home() {
  const [filters, setFilters] = useState<FormState>(defaultFilters);
  const [appliedFilters, setAppliedFilters] = useState<FormState>(defaultFilters);
  const [rows, setRows] = useState<EmploymentRow[]>([]);
  const [partialErrors, setPartialErrors] = useState<EmploymentError[]>([]);
  const [hasPartialErrors, setHasPartialErrors] = useState(false);
  const [status, setStatus] = useState<"idle" | "loading" | "success" | "error">(
    "idle",
  );
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [stateOptions, setStateOptions] = useState<StateOption[]>([]);
  const [statesStatus, setStatesStatus] = useState<
    "idle" | "loading" | "success" | "error"
  >("idle");
  const [statesError, setStatesError] = useState<string | null>(null);
  const [stateMenuOpen, setStateMenuOpen] = useState(false);
  const [sortDirection, setSortDirection] = useState<SortDirection>("asc");
  const stateDropdownRef = useRef<HTMLDivElement>(null);

  const stateNameByCode = useMemo(() => {
    const lookup = new Map<string, string>();

    for (const state of stateOptions) {
      lookup.set(state.code, state.name);
    }

    return lookup;
  }, [stateOptions]);

  const loadStates = useCallback(async () => {
    setStatesStatus("loading");
    setStatesError(null);

    try {
      const data = await fetchStates();
      const options = data
        .map((item: StateOption) => ({
          code: String(item.code ?? "").padStart(2, "0"),
          name: String(item.name ?? ""),
        }))
        .filter((option: StateOption) => option.code && option.name)
        .sort((a: StateOption, b: StateOption) => a.name.localeCompare(b.name));

      setStateOptions(options);
      setStatesStatus("success");
    } catch (error) {
      setStatesStatus("error");
      setStatesError(error instanceof Error ? error.message : "Failed to load states.");
    }
  }, []);

  useEffect(() => {
    if (!stateMenuOpen) return;

    const handleClickOutside = (event: MouseEvent) => {
      if (
        stateDropdownRef.current &&
        !stateDropdownRef.current.contains(event.target as Node)
      ) {
        setStateMenuOpen(false);
      }
    };

    document.addEventListener("mousedown", handleClickOutside);

    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, [stateMenuOpen]);

  useEffect(() => {
    void loadStates();
  }, [loadStates]);

  const appliedStateLabel =
    appliedFilters.states.includes(ALL_STATES_CODE) || appliedFilters.states.length === 0
      ? "All States"
      : [...appliedFilters.states]
          .map((code) => stateNameByCode.get(code) ?? code)
          .sort((a, b) => a.localeCompare(b))
          .join(", ");

  async function handleSearch(event?: React.FormEvent) {
    event?.preventDefault();
    const resolvedStates =
      filters.states.length === 0 ? [ALL_STATES_CODE] : filters.states;
    const stateParam = resolvedStates.includes(ALL_STATES_CODE)
      ? "ALL"
      : resolvedStates.join(",");

    setAppliedFilters({
      ...filters,
      states: resolvedStates,
    });

    setStatus("loading");
    setErrorMessage(null);
    setHasPartialErrors(false);
    setPartialErrors([]);
    setRows([]);

    try {
      const { rows: nextRows, errors, hasErrors, message } = await fetchEmployments({
        quarter: filters.quarter,
        states: stateParam,
        breakdownSex: filters.breakdownSex,
      });
      const sortedRows = sortRows(nextRows, sortDirection);

      setRows(sortedRows);
      setPartialErrors(errors);
      setHasPartialErrors(hasErrors);
      setStatus("success");
    } catch (error) {
      setStatus("error");
      setRows([]);
      setPartialErrors([]);
      setHasPartialErrors(false);
      setErrorMessage(error instanceof Error ? error.message : "Request failed.");
    }
  }

  function toggleState(code: string) {
    setFilters((prev) => {
      if (code === ALL_STATES_CODE) {
        return { ...prev, states: [ALL_STATES_CODE] };
      }

      const next = new Set(prev.states);

      if (next.has(code)) {
        next.delete(code);
      } else {
        next.add(code);
      }

      next.delete(ALL_STATES_CODE);

      if (next.size === 0) {
        next.add(ALL_STATES_CODE);
      }

      return { ...prev, states: Array.from(next) };
    });
  }

  function selectedStatesLabel() {
    if (filters.states.includes(ALL_STATES_CODE) || filters.states.length === 0) {
      return "All States";
    }

    const names = filters.states
      .map((code) => stateNameByCode.get(code) ?? code)
      .sort((a, b) => a.localeCompare(b));

    if (names.length <= 2) {
      return names.join(", ");
    }

    return `${names.slice(0, 2).join(", ")} +${names.length - 2} more`;
  }

  function renderValue(value?: number | null) {
    if (value === null || value === undefined) {
      return "—";
    }

    return numberFormatter.format(value);
  }

  useEffect(() => {
    if (!rows.length) return;
    setRows((prev) => sortRows(prev, sortDirection));
  }, [sortDirection, rows.length]);

  return (
    <AppShell>
      <div className="space-y-8">
        <div className="flex flex-col gap-3">
          <p className="text-sm uppercase tracking-wide text-blue-800">Employment</p>
          <h1 className="text-3xl font-bold text-slate-900">
            Employment for {appliedStateLabel} on {appliedFilters.quarter}
          </h1>
          <p className="text-slate-700">
            Choose states, a quarter, and whether to break down results by sex. The
            table below updates after each search and defaults to all states for 2023-Q4.
          </p>
        </div>

        <form
          onSubmit={handleSearch}
          className="grid grid-cols-1 items-end gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-12"
        >
          <div className="md:col-span-6" ref={stateDropdownRef}>
            <label className="text-sm font-medium text-slate-700">State(s)</label>
            <div className="relative mt-2">
              <button
                type="button"
                className="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-slate-800 shadow-sm transition hover:border-slate-400"
                onClick={() => setStateMenuOpen((open) => !open)}
                aria-haspopup="listbox"
                aria-expanded={stateMenuOpen}
              >
                <span className="truncate">{selectedStatesLabel()}</span>
                <span className="text-slate-400">▾</span>
              </button>

              {stateMenuOpen && (
                <div className="absolute z-10 mt-2 w-full rounded-lg border border-slate-200 bg-white shadow-lg">
                  <div className="flex items-center gap-2 border-b border-slate-200 px-3 py-2">
                    <input
                      id="all-states"
                      type="checkbox"
                      className="h-4 w-4"
                      checked={filters.states.includes(ALL_STATES_CODE)}
                      onChange={() => toggleState(ALL_STATES_CODE)}
                    />
                    <label htmlFor="all-states" className="text-sm font-medium">
                      All States
                    </label>
                  </div>
                  <div className="max-h-64 overflow-y-auto">
                    {statesStatus === "loading" && (
                      <div className="px-3 py-2 text-sm text-slate-600">
                        Loading states…
                      </div>
                    )}
                    {statesStatus === "error" && (
                      <div className="space-y-2 px-3 py-3 text-sm text-rose-700">
                        <p>{statesError ?? "Failed to load states."}</p>
                        <button
                          type="button"
                          className="text-blue-700 underline"
                          onClick={() => void loadStates()}
                        >
                          Retry
                        </button>
                      </div>
                    )}
                    {statesStatus === "success" && stateOptions.length === 0 && (
                      <div className="px-3 py-2 text-sm text-slate-600">
                        No states available.
                      </div>
                    )}
                    {statesStatus === "success" &&
                      stateOptions.map((state) => (
                        <label
                          key={state.code}
                          className="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50"
                        >
                          <input
                            type="checkbox"
                            className="h-4 w-4"
                            checked={filters.states.includes(state.code)}
                            onChange={() => toggleState(state.code)}
                          />
                          <span>{state.name}</span>
                          <span className="ml-auto text-xs text-slate-500">
                            {state.code}
                          </span>
                        </label>
                      ))}
                  </div>
                </div>
              )}
            </div>
          </div>

          <div className="flex flex-col gap-2 md:col-span-3">
            <label className="text-sm font-medium text-slate-700">Quarter</label>
            <select
              className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-800 shadow-sm hover:border-slate-400 focus:border-blue-500 focus:outline-none"
              value={filters.quarter}
              onChange={(event) =>
                setFilters((prev) => ({ ...prev, quarter: event.target.value }))
              }
            >
              {QUARTERS.map((quarter) => (
                <option key={quarter} value={quarter}>
                  {quarter}
                </option>
              ))}
            </select>
          </div>

          <div className="flex flex-col justify-between gap-2 md:col-span-2">
            <label className="text-sm font-medium text-slate-700">
              Breakdown by sex
            </label>
            <div className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
              <span className="text-sm text-slate-700">
                {filters.breakdownSex ? "On" : "Off"}
              </span>
              <input
                type="checkbox"
                className="h-5 w-5 accent-blue-600"
                checked={filters.breakdownSex}
                onChange={(event) =>
                  setFilters((prev) => ({ ...prev, breakdownSex: event.target.checked }))
                }
              />
            </div>
          </div>

          <div className="flex justify-end md:col-span-1 md:justify-end">
            <button
              type="submit"
              disabled={status === "loading"}
              className="inline-flex items-center justify-center rounded-lg bg-blue-700 px-4 py-2 font-medium text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-blue-300"
            >
              Search
            </button>
          </div>
        </form>

        <section className="space-y-4">
          {status === "loading" && (
            <div className="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <div className="h-5 w-5 animate-spin rounded-full border-2 border-blue-700 border-t-transparent" />
              <div className="space-y-1">
                <p className="text-sm font-medium text-slate-900">
                  Loading employment data…
                </p>
                <p className="text-xs text-slate-600">This may take a moment.</p>
              </div>
            </div>
          )}

          {status === "idle" && rows.length === 0 && (
            <div className="rounded-lg border border-dashed border-slate-200 bg-white p-4 text-slate-700 shadow-sm">
              Run a search to load employment data.
            </div>
          )}

          {status === "error" && (
            <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-rose-800">
              <p className="font-semibold">Failed to load data</p>
              <p className="text-sm mt-1">{errorMessage}</p>
            </div>
          )}

          {hasPartialErrors && partialErrors.length > 0 && (
            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900">
              <p className="font-semibold">Some requests returned errors</p>
              <ul className="mt-2 space-y-1 text-sm">
                {partialErrors.map((error, index) => (
                  <li key={`${error.stateCode ?? "unknown"}-${index}`}>
                    {error.stateCode ? `State ${error.stateCode}: ` : ""}
                    {error.sex ? `${error.sex} ` : ""}
                    {error.message ?? "Unexpected error"}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {status === "success" && rows.length === 0 && (
            <div className="rounded-lg border border-slate-200 bg-white p-4 text-slate-700 shadow-sm">
              No employment data returned for this selection.
            </div>
          )}

          {rows.length > 0 && (
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200">
                  <thead className="bg-slate-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-sm font-semibold text-slate-700">
                        <button
                          type="button"
                          className="flex items-center gap-1 hover:text-blue-700"
                          onClick={() =>
                            setSortDirection((prev) => (prev === "asc" ? "desc" : "asc"))
                          }
                        >
                          State
                          <span className="text-xs text-slate-500">
                            {sortDirection === "asc" ? "▲" : "▼"}
                          </span>
                        </button>
                      </th>
                      {appliedFilters.breakdownSex && (
                        <>
                          <th className="px-4 py-3 text-left text-sm font-semibold text-slate-700">
                            Male
                          </th>
                          <th className="px-4 py-3 text-left text-sm font-semibold text-slate-700">
                            Female
                          </th>
                        </>
                      )}
                      <th className="px-4 py-3 text-left text-sm font-semibold text-slate-700">
                        Total employment
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {rows.map((row) => (
                      <tr key={row.stateCode} className="hover:bg-slate-50">
                        <td className="px-4 py-3 text-sm font-medium text-slate-900">
                          {row.stateName}
                        </td>
                        {appliedFilters.breakdownSex && (
                          <>
                            <td className="px-4 py-3 text-sm text-slate-800">
                              {renderValue(row.male)}
                            </td>
                            <td className="px-4 py-3 text-sm text-slate-800">
                              {renderValue(row.female)}
                            </td>
                          </>
                        )}
                        <td className="px-4 py-3 text-sm text-slate-900">
                          {renderValue(row.total)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </section>
      </div>
    </AppShell>
  );
}

function sortRows(rows: EmploymentRow[], direction: SortDirection): EmploymentRow[] {
  return [...rows].sort((a, b) =>
    direction === "asc"
      ? a.stateName.localeCompare(b.stateName)
      : b.stateName.localeCompare(a.stateName),
  );
}

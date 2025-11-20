import { AppShell } from "../components/AppShell";
import type { Route } from "./+types/about";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "About | Census Employment Viewer" },
    {
      name: "description",
      content: "Learn how the Census Employment Viewer summarizes employment data.",
    },
  ];
}

export default function About() {
  return (
    <AppShell>
      <div className="space-y-6">
        <div className="space-y-2">
          <p className="text-sm uppercase tracking-wide text-blue-800">
            About
          </p>
          <h1 className="text-3xl font-bold text-slate-900">
            How this viewer works
          </h1>
          <p className="text-slate-700">
            This frontend calls the Laravel backend in this repository to
            request employment counts from the Census Quarterly Workforce
            Indicators (QWI) API. You can review the backend in{" "}
            <code className="rounded bg-slate-100 px-2 py-px">
              census-employment-viewer-backend
            </code>{" "}
            to see the API contract and logging.
          </p>
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">
              Endpoints
            </h2>
            <p className="mt-2 text-sm text-slate-700">
              Data is sourced from <code>/api/employments</code> with the
              following parameters:
            </p>
            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
              <li>
                <span className="font-medium">quarter</span> – Quarter in
                <code className="mx-1 rounded bg-slate-100 px-1">YYYY-QN</code>{" "}
                format (2020-Q1 through 2023-Q4).
              </li>
              <li>
                <span className="font-medium">states</span> – Comma separated
                FIPS codes or <code className="px-1">ALL</code>.
              </li>
              <li>
                <span className="font-medium">breakdownSex</span> –{" "}
                <code className="px-1">true</code> to request male/female
                breakdown.
              </li>
            </ul>
              <p className="mt-2 text-sm text-slate-700">
              Data for states are sourced from <code>/api/states</code>.
            </p>
          </div>
          <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Display</h2>
            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
              <li>Default view shows total employment per state.</li>
              <li>
                When breakdown is on, male, female, and total columns are
                shown.
              </li>
              <li>Rows are sorted alphabetically by state name.</li>
            </ul>
          </div>
        </div>
        <div className="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">
            Usage notes
          </h2>
          <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
            <li>Requests always include at least “All States” if none are selected.</li>
            <li>Quarter selections are limited to the latest available range.</li>
            <li>Partial API errors are surfaced inline without blocking results.</li>
          </ul>
        </div>
      </div>
    </AppShell>
  );
}

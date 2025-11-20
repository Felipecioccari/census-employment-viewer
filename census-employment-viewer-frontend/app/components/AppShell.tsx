import { Link, NavLink } from "react-router";

type AppShellProps = {
  children: React.ReactNode;
};

export function AppShell({ children }: AppShellProps) {
  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <header className="border-b border-slate-200 bg-white/90 backdrop-blur">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
          <Link to="/" className="flex items-center gap-2 text-lg font-semibold">
            <span className="h-8 w-8 rounded-lg bg-blue-600 text-white grid place-items-center text-sm font-bold shadow-sm">
              CE
            </span>
            <span>Census Employment Viewer</span>
          </Link>
          <nav className="flex items-center gap-4 text-sm font-medium">
            <NavLink
              to="/"
              prefetch="intent"
              className={({ isActive }) =>
                [
                  "rounded-full px-3 py-2 transition-colors",
                  isActive
                    ? "bg-blue-100 text-blue-800"
                    : "text-slate-700 hover:bg-slate-100",
                ].join(" ")
              }
            >
              Dashboard
            </NavLink>
            <NavLink
              to="/about"
              prefetch="intent"
              className={({ isActive }) =>
                [
                  "rounded-full px-3 py-2 transition-colors",
                  isActive
                    ? "bg-blue-100 text-blue-800"
                    : "text-slate-700 hover:bg-slate-100",
                ].join(" ")
              }
            >
              About
            </NavLink>
          </nav>
        </div>
      </header>
      <main className="mx-auto max-w-6xl px-4 py-10">{children}</main>
    </div>
  );
}

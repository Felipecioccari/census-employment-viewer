import type { Config } from "@react-router/dev/config";

export default {
  // Config options...
  // Server-side render by default, to enable SPA mode set this to `false`
  ssr: true,
  // Pre-render static pages to HTML at build time
  prerender: ["/about"],
} satisfies Config;

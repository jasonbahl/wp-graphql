/**
 * Generate WordPress.org plugin assets (icon-128/256, banner-772x250/1544x500)
 * for the sibling-branded extensions, rendered from the brand marks + fonts and
 * screenshotted at exact pixel sizes with Playwright.
 *
 * Run from the website workspace (so `playwright` resolves):
 *   cd websites/wpgraphql.com && node ../../design/brand/generate-wporg-assets.mjs
 */
import { chromium } from "@playwright/test"
import { fileURLToPath } from "node:url"
import path from "node:path"

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../")
const out = (slug, file) =>
  path.join(ROOT, "plugins", slug, ".wordpress-org", file)

const FONTS =
  "https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=DM+Mono:wght@400;500&display=swap"

// Marks transcribed from src/components/<Product>/*Logo.tsx (default variant).
const marks = {
  ide: `<svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect width="160" height="160" rx="36" fill="#0C1220"/>
    <rect x="14" y="14" width="132" height="18" rx="4" fill="url(#ideGrad)"/>
    <defs><linearGradient id="ideGrad" x1="14" y1="14" x2="146" y2="32" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#9B72FF" stop-opacity="0.35"/><stop offset="100%" stop-color="#6B3FD0" stop-opacity="0"/>
    </linearGradient></defs>
    <rect x="14" y="14" width="132" height="18" rx="4" fill="#8B5CF6"/>
    <circle cx="25" cy="23" r="3.5" fill="rgba(255,255,255,0.55)"/>
    <circle cx="35" cy="23" r="3.5" fill="rgba(255,255,255,0.35)"/>
    <circle cx="45" cy="23" r="3.5" fill="rgba(255,255,255,0.2)"/>
    <circle cx="136" cy="23" r="7" fill="rgba(255,255,255,0.12)"/>
    <path d="M 133.5 20.5 L 139 23 L 133.5 25.5 Z" fill="rgba(255,255,255,0.7)"/>
    <rect x="79.5" y="38" width="1" height="108" fill="#1A2540"/>
    <rect x="19" y="44" width="36" height="2.5" rx="1.25" fill="#A78BFA" opacity="0.7"/>
    <rect x="24" y="51" width="44" height="2.5" rx="1.25" fill="#A78BFA" opacity="0.55"/>
    <rect x="29" y="58" width="32" height="2.5" rx="1.25" fill="#C4B5FD" opacity="0.45"/>
    <rect x="34" y="65" width="22" height="2.5" rx="1.25" fill="#6578A0" opacity="0.5"/>
    <rect x="29" y="72" width="30" height="2.5" rx="1.25" fill="#A78BFA" opacity="0.4"/>
    <rect x="24" y="79" width="36" height="2.5" rx="1.25" fill="#C4B5FD" opacity="0.35"/>
    <rect x="19" y="86" width="20" height="2.5" rx="1.25" fill="#6578A0" opacity="0.4"/>
    <rect x="86" y="44" width="20" height="2.5" rx="1.25" fill="#50FA7B" opacity="0.8"/>
    <rect x="91" y="51" width="44" height="2.5" rx="1.25" fill="#96A8C8" opacity="0.55"/>
    <rect x="91" y="58" width="34" height="2.5" rx="1.25" fill="#96A8C8" opacity="0.45"/>
    <rect x="96" y="65" width="38" height="2.5" rx="1.25" fill="#50FA7B" opacity="0.4"/>
    <rect x="96" y="72" width="28" height="2.5" rx="1.25" fill="#96A8C8" opacity="0.4"/>
    <rect x="91" y="79" width="40" height="2.5" rx="1.25" fill="#96A8C8" opacity="0.35"/>
    <rect x="14" y="134" width="132" height="12" rx="3" fill="#5E2EC4" opacity="0.75"/>
    <rect x="19" y="137" width="40" height="2" rx="1" fill="rgba(255,255,255,0.4)"/>
    <rect x="106" y="137" width="34" height="2" rx="1" fill="rgba(255,255,255,0.25)"/>
  </svg>`,
  acf: `<svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect width="160" height="160" rx="36" fill="#0C1220"/>
    <rect x="14" y="14" width="132" height="14" rx="3.5" fill="#10B981" opacity="0.9"/>
    <rect x="18" y="17.5" width="14" height="7" rx="1.5" fill="rgba(255,255,255,0.25)"/>
    <rect x="14" y="34" width="132" height="10" fill="#131B30"/>
    <rect x="19" y="37" width="18" height="3" rx="1.5" fill="#435678" opacity="0.8"/>
    <rect x="52" y="37" width="28" height="3" rx="1.5" fill="#435678" opacity="0.8"/>
    <rect x="94" y="37" width="20" height="3" rx="1.5" fill="#435678" opacity="0.8"/>
    <rect x="14" y="44.75" width="132" height="16" fill="rgba(16,185,129,0.08)"/>
    <rect x="14" y="44.75" width="3" height="16" fill="#10B981"/>
    <rect x="19" y="50.5" width="22" height="3" rx="1.5" fill="#34D399" opacity="0.9"/>
    <rect x="52" y="50.5" width="34" height="3" rx="1.5" fill="#96A8C8" opacity="0.65"/>
    <rect x="94" y="50.5" width="24" height="3" rx="1.5" fill="#96A8C8" opacity="0.5"/>
    <rect x="14" y="60.75" width="132" height="0.75" fill="#1A2540"/>
    <rect x="19" y="66.5" width="18" height="3" rx="1.5" fill="#96A8C8" opacity="0.55"/>
    <rect x="52" y="66.5" width="28" height="3" rx="1.5" fill="#96A8C8" opacity="0.45"/>
    <rect x="94" y="66.5" width="20" height="3" rx="1.5" fill="#96A8C8" opacity="0.4"/>
    <rect x="14" y="76.75" width="132" height="0.75" fill="#1A2540"/>
    <rect x="19" y="82.5" width="20" height="3" rx="1.5" fill="#96A8C8" opacity="0.45"/>
    <rect x="52" y="82.5" width="32" height="3" rx="1.5" fill="#96A8C8" opacity="0.35"/>
    <rect x="14" y="92.75" width="132" height="0.75" fill="#1A2540"/>
    <rect x="19" y="98.5" width="16" height="3" rx="1.5" fill="#96A8C8" opacity="0.35"/>
    <rect x="52" y="98.5" width="24" height="3" rx="1.5" fill="#96A8C8" opacity="0.28"/>
    <rect x="14" y="130" width="60" height="16" rx="4" fill="rgba(16,185,129,0.15)" stroke="rgba(16,185,129,0.3)" stroke-width="1"/>
    <rect x="90" y="132" width="56" height="12" rx="3" fill="#10B981" opacity="0.75"/>
    <rect x="95" y="135.5" width="40" height="2.5" rx="1.25" fill="rgba(255,255,255,0.6)"/>
  </svg>`,
  smartCache: `<svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect width="160" height="160" rx="36" fill="#0C1220"/>
    <circle cx="80" cy="80" r="60" stroke="#F43F5E" stroke-width="1.5" fill="none" opacity="0.18"/>
    <circle cx="80" cy="80" r="44" stroke="#F43F5E" stroke-width="2" fill="none" opacity="0.40"/>
    <circle cx="80" cy="80" r="28" stroke="#F43F5E" stroke-width="2.5" fill="none" opacity="0.72"/>
    <path d="M 80 80 L 130 30 A 71 71 0 0 0 151 80 Z" fill="#F43F5E" opacity="0.05"/>
    <line x1="80" y1="80" x2="130" y2="30" stroke="#F43F5E" stroke-width="1" stroke-linecap="round" opacity="0.2"/>
    <circle cx="116" cy="44" r="2.5" fill="#FB7185" opacity="0.75"/>
    <circle cx="80" cy="80" r="9" fill="#F43F5E" opacity="0.93"/>
    <circle cx="80" cy="80" r="4.5" fill="#FFF1F2" opacity="0.87"/>
  </svg>`,
  // WPGraphQL core: navy circle + orange elephant (official logo, circle container).
  wpgraphql: `<svg viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="256" cy="256" r="256" fill="#0E1628"/>
    <path fill-rule="nonzero" fill="#FF8C1A" d="m117.592 300.896c0-35.138.58-39.429 7.074-52.301 5.682-11.133 20.758-25.05 30.732-28.065 2.203-.696 2.899.348 6.726 9.858 12.408 31.195 37.11 54.505 69.349 65.29l8.465 2.899.348 16.815c.116 9.394-.116 16.932-.58 16.816-.58 0-2.899-3.131-5.45-6.958-11.945-18.671-35.718-30.036-59.724-28.645-21.802 1.276-40.589 12.061-52.765 30.152l-4.175 6.147zm25.165 85.353c10.09-3.015 17.743-13.568 17.743-24.47 0-7.77 9.51-16.699 17.627-16.699 10.321 0 17.396 6.958 18.787 18.44 1.276 10.32 5.567 16.815 14.032 21.337 4.407 2.436 6.147 2.552 32.471 2.552 26.441 0 28.065-.116 32.588-2.552 5.566-3.015 11.712-9.51 12.872-14.032.58-1.74.928-25.049.928-51.838v-48.706l-2.9-5.103c-4.87-8.582-10.437-11.597-24.469-13.452-19.019-2.436-30.036-7.538-41.053-18.787-8.117-8.118-14.96-21.57-16.815-33.051-3.71-21.918 7.19-46.503 26.325-59.26 11.48-7.654 20.526-10.437 33.979-10.437 8.813 0 12.64.58 19.25 2.9 14.728 5.218 25.745 14.031 33.515 27.02 8.234 13.916 8.002 10.205 8.698 94.514.58 68.885.928 76.539 2.783 82.337 6.146 19.02 18.903 34.559 34.443 42.097 21.338 10.437 42.212 11.133 60.767 2.087 19.019-9.393 33.747-30.615 37.69-54.389 2.435-14.612-1.16-23.193-11.83-28.528-10.32-5.219-21.917-3.827-29.107 3.479-4.639 4.639-6.262 8.118-8.234 17.86-2.551 12.06-8.118 17.394-18.323 17.394-6.378 0-12.524-3.247-15.424-8.233-2.203-3.827-2.319-6.61-2.899-78.743-.58-66.566-.812-75.727-2.667-82.801-12.409-47.895-49.403-80.366-98.69-86.513-24.584-3.015-56.94 6.843-78.858 24.354-17.627 13.916-29.108 30.615-36.53 52.997l-3.479 9.974-11.944 4.29c-19.02 6.727-28.645 12.641-42.909 26.441-12.872 12.525-21.802 26.441-27.6 43.14-5.335 15.772-5.799 21.339-5.799 75.844v51.374l2.668 5.102c3.015 5.683 10.089 11.25 16.003 12.64 2.204.465 14.38.929 27.253 1.044 17.511.116 24.701-.347 29.108-1.623zm132.204-172.793c6.03-2.551 8.35-4.87 11.48-11.597 4.523-9.625 3.248-20.526-3.362-28.064-4.755-5.45-9.51-7.306-18.555-7.306-6.03 0-8.234.58-12.64 3.363-15.077 9.51-14.265 34.79 1.39 42.792 6.147 3.016 15.425 3.363 21.687.812z"/>
  </svg>`,
}

const products = [
  {
    slug: "wp-graphql",
    mark: marks.wpgraphql,
    accent: "#FF8C1A",
    accentRgb: "255,140,26",
    name: "WPGraphQL",
    accentWord: "",
    tagline: "GraphQL for WordPress",
    minimal: true,
    graph: true, // constellation banner: graph of nodes radiating from the logo
  },
  {
    slug: "wp-graphql-ide",
    mark: marks.ide,
    accent: "#8B5CF6",
    accentRgb: "139,92,246",
    name: "WPGraphQL",
    accentWord: "IDE",
    tagline: "A modern GraphQL IDE for WordPress",
    minimal: true, // text-free banner: centered mark + glow
  },
  {
    slug: "wp-graphql-acf",
    mark: marks.acf,
    accent: "#10B981",
    accentRgb: "16,185,129",
    name: "WPGraphQL",
    accentWord: "ACF",
    tagline: "Advanced Custom Fields, in GraphQL",
    minimal: true, // text-free banner: centered mark + glow
  },
  {
    slug: "wp-graphql-smart-cache",
    mark: marks.smartCache,
    accent: "#F43F5E",
    accentRgb: "244,63,94",
    name: "WPGraphQL",
    accentWord: "Smart Cache",
    tagline: "Caching & invalidation for WPGraphQL",
    minimal: true, // text-free banner: centered mark + glow
  },
]

const iconHtml = (p, size) => `<!doctype html><html><head><meta charset="utf-8">
<style>html,body{margin:0;padding:0;background:transparent}
.m{width:${size}px;height:${size}px}.m svg{display:block;width:100%;height:100%}</style></head>
<body><div class="m">${p.mark}</div></body></html>`

const bannerHtml = (p, w, h) => {
  const mark = Math.round(h * 0.56)
  const nameSize = Math.round(h * 0.18)
  const tagSize = Math.round(h * 0.05)
  const pad = Math.round(h * 0.13)
  const gap = Math.round(h * 0.07)
  return `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="${FONTS}">
<style>
  html,body{margin:0;padding:0}
  .banner{width:${w}px;height:${h}px;display:flex;align-items:center;gap:${gap}px;
    padding:0 ${pad}px;box-sizing:border-box;background:#080D18;overflow:hidden;position:relative}
  .glow{position:absolute;inset:0;pointer-events:none;background:
    radial-gradient(ellipse ${Math.round(w * 0.5)}px ${Math.round(h * 1.3)}px at 82% 0%, rgba(${p.accentRgb},0.16) 0%, rgba(${p.accentRgb},0.05) 42%, transparent 72%),
    radial-gradient(ellipse ${Math.round(w * 0.3)}px ${Math.round(h * 0.9)}px at 5% 110%, rgba(${p.accentRgb},0.07) 0%, transparent 65%)}
  .mark{width:${mark}px;height:${mark}px;flex:0 0 auto;position:relative;
    filter:drop-shadow(0 0 ${Math.round(h * 0.06)}px rgba(${p.accentRgb},0.45))}
  .mark svg{display:block;width:100%;height:100%}
  .txt{position:relative;display:flex;flex-direction:column;justify-content:center}
  .name{font-family:'Bricolage Grotesque',system-ui,sans-serif;font-weight:800;
    font-size:${nameSize}px;line-height:1;letter-spacing:-0.03em;color:#F0F4FF}
  .name .accent{color:${p.accent}}
  .tag{font-family:'DM Mono',ui-monospace,monospace;font-weight:500;
    font-size:${tagSize}px;letter-spacing:0.04em;color:#96A8C8;margin-top:${Math.round(h * 0.05)}px}
</style></head>
<body><div class="banner"><div class="glow"></div>
  <div class="mark">${p.mark}</div>
  <div class="txt">
    <div class="name">${p.name} <span class="accent">${p.accentWord}</span></div>
    <div class="tag">${p.tagline}</div>
  </div>
</div></body></html>`
}

// CLI: pass plugin slugs to limit which products are generated, and `--banners`
// to skip icons. e.g. `node generate-wporg-assets.mjs wp-graphql-acf --banners`.
const argv = process.argv.slice(2)
const flags = argv.filter((a) => a.startsWith("--"))
const slugs = argv.filter((a) => !a.startsWith("--"))
const wantIcons = !flags.includes("--banners")
const selected = slugs.length
  ? products.filter((p) => slugs.includes(p.slug))
  : products

// Text-free banner: the brand mark centered on the navy field with an accent
// glow halo and faint ambient cards bleeding in from the sides for depth.
const minimalBannerHtml = (p, w, h) => {
  const mark = Math.round(h * 0.6)
  return `<!doctype html><html><head><meta charset="utf-8">
<style>
  html,body{margin:0;padding:0}
  .banner{width:${w}px;height:${h}px;background:#080D18;position:relative;overflow:hidden;
    display:flex;align-items:center;justify-content:center}
  .amb{position:absolute;top:50%;width:${Math.round(h * 0.95)}px;height:${Math.round(h * 1.5)}px;
    border-radius:${Math.round(h * 0.12)}px;background:#0C1220;opacity:0.55;
    filter:blur(${Math.round(h * 0.03)}px)}
  .amb.l{left:${-Math.round(h * 0.35)}px;transform:translateY(-50%) rotate(-8deg)}
  .amb.r{right:${-Math.round(h * 0.35)}px;transform:translateY(-50%) rotate(8deg)}
  .glow{position:absolute;inset:0;pointer-events:none;background:
    radial-gradient(ellipse ${Math.round(w * 0.28)}px ${Math.round(h * 0.95)}px at 50% 50%,
      rgba(${p.accentRgb},0.22) 0%, rgba(${p.accentRgb},0.07) 42%, transparent 70%)}
  .mark{position:relative;width:${mark}px;height:${mark}px;
    filter:drop-shadow(0 0 ${Math.round(h * 0.09)}px rgba(${p.accentRgb},0.5))
           drop-shadow(0 0 ${Math.round(h * 0.03)}px rgba(${p.accentRgb},0.35))}
  .mark svg{display:block;width:100%;height:100%}
</style></head>
<body><div class="banner">
  <div class="amb l"></div><div class="amb r"></div>
  <div class="glow"></div>
  <div class="mark">${p.mark}</div>
</div></body></html>`
}

// Seeded PRNG (mulberry32) so the constellation layout is deterministic.
const mulberry32 = (seed) => () => {
  seed |= 0
  seed = (seed + 0x6d2b79f5) | 0
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed)
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296
}

// A graph of accent nodes/edges radiating from the centered logo (the "entry
// point" to the graph). Returns an inline SVG sized to the banner.
const constellationSvg = (w, h, accentRgb, seed = 7) => {
  const rnd = mulberry32(seed)
  const cx = w / 2
  const cy = h / 2
  const clear = h * 0.4 // keep the area around the logo clear
  const target = Math.round((w / 1544) * 78)
  const nodes = []
  let attempts = 0
  while (nodes.length < target && attempts < target * 30) {
    attempts++
    const x = rnd() * w
    const y = cy + (rnd() - 0.5) * h * 1.06
    if (y < 4 || y > h - 4) continue
    if (Math.hypot(x - cx, y - cy) < clear) continue
    nodes.push({ x, y, r: 1.1 + rnd() * rnd() * 5, glow: rnd() < 0.14 })
  }
  const maxLen = w * 0.085
  const edges = []
  for (let i = 0; i < nodes.length; i++) {
    const near = []
    for (let j = 0; j < nodes.length; j++) {
      if (i === j) continue
      const d = Math.hypot(nodes[i].x - nodes[j].x, nodes[i].y - nodes[j].y)
      if (d < maxLen) near.push([d, j])
    }
    near.sort((a, b) => a[0] - b[0])
    const k = 1 + Math.floor(rnd() * 2)
    for (let n = 0; n < Math.min(k, near.length); n++) {
      const j = near[n][1]
      if (i < j) edges.push([i, j, near[n][0]])
    }
  }
  // "entry" edges from the logo center to the nearest nodes
  const entry = nodes
    .map((nd, i) => [Math.hypot(nd.x - cx, nd.y - cy), i])
    .sort((a, b) => a[0] - b[0])
    .slice(0, 8)
    .map((d) => d[1])

  const line = (x1, y1, x2, y2, op) =>
    `<line x1="${x1.toFixed(1)}" y1="${y1.toFixed(1)}" x2="${x2.toFixed(1)}" y2="${y2.toFixed(1)}" stroke="rgba(${accentRgb},${op})" stroke-width="1"/>`

  let glow = ""
  let dots = ""
  for (const nd of nodes) {
    if (nd.glow)
      glow += `<circle cx="${nd.x.toFixed(1)}" cy="${nd.y.toFixed(1)}" r="${(nd.r * 3.4).toFixed(1)}" fill="rgba(${accentRgb},0.5)"/>`
    const op = (0.45 + rnd() * 0.55).toFixed(2)
    dots += `<circle cx="${nd.x.toFixed(1)}" cy="${nd.y.toFixed(1)}" r="${nd.r.toFixed(1)}" fill="rgba(${accentRgb},${op})"/>`
  }
  let lines = ""
  for (const [i, j, d] of edges)
    lines += line(
      nodes[i].x,
      nodes[i].y,
      nodes[j].x,
      nodes[j].y,
      (0.26 * (1 - d / maxLen) + 0.05).toFixed(3)
    )
  for (const idx of entry)
    lines += line(cx, cy, nodes[idx].x, nodes[idx].y, "0.2")

  return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs><filter id="cg" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="5"/></filter></defs>
    ${lines}
    <g filter="url(#cg)">${glow}</g>
    ${dots}
  </svg>`
}

const graphBannerHtml = (p, w, h) => {
  const mark = Math.round(h * 0.56)
  return `<!doctype html><html><head><meta charset="utf-8">
<style>
  html,body{margin:0;padding:0}
  .banner{width:${w}px;height:${h}px;background:#080D18;position:relative;overflow:hidden;
    display:flex;align-items:center;justify-content:center}
  .net{position:absolute;inset:0}
  .net svg{display:block;width:100%;height:100%}
  .glow{position:absolute;inset:0;pointer-events:none;background:
    radial-gradient(ellipse ${Math.round(w * 0.22)}px ${Math.round(h * 0.85)}px at 50% 50%,
      rgba(${p.accentRgb},0.22) 0%, rgba(${p.accentRgb},0.07) 45%, transparent 72%)}
  .mark{position:relative;width:${mark}px;height:${mark}px;
    filter:drop-shadow(0 0 ${Math.round(h * 0.09)}px rgba(${p.accentRgb},0.55))
           drop-shadow(0 0 ${Math.round(h * 0.03)}px rgba(${p.accentRgb},0.4))}
  .mark svg{display:block;width:100%;height:100%}
</style></head>
<body><div class="banner">
  <div class="net">${constellationSvg(w, h, p.accentRgb)}</div>
  <div class="glow"></div>
  <div class="mark">${p.mark}</div>
</div></body></html>`
}

const run = async () => {
  const browser = await chromium.launch()
  for (const p of selected) {
    if (wantIcons) {
      for (const size of [256, 128]) {
        const page = await browser.newPage({
          viewport: { width: size, height: size },
          deviceScaleFactor: 1,
        })
        await page.setContent(iconHtml(p, size), { waitUntil: "networkidle" })
        await page.screenshot({
          path: out(p.slug, `icon-${size}x${size}.png`),
          omitBackground: true,
        })
        await page.close()
      }
    }
    for (const [w, h] of [
      [1544, 500],
      [772, 250],
    ]) {
      const page = await browser.newPage({
        viewport: { width: w, height: h },
        deviceScaleFactor: 1,
      })
      const html = p.graph
        ? graphBannerHtml(p, w, h)
        : p.minimal
          ? minimalBannerHtml(p, w, h)
          : bannerHtml(p, w, h)
      await page.setContent(html, { waitUntil: "networkidle" })
      await page.evaluate(() => document.fonts.ready)
      await page.screenshot({ path: out(p.slug, `banner-${w}x${h}.png`) })
      await page.close()
    }
    console.log(`generated ${wantIcons ? "assets" : "banners"} for ${p.slug}`)
  }
  await browser.close()
}

run().catch((e) => {
  console.error(e)
  process.exit(1)
})

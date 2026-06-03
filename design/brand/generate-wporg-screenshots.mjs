/**
 * Generate branded WordPress.org screenshot assets: composite a raw admin-UI
 * capture onto the sibling-brand frame (navy + accent glow + product logo, with
 * the UI in a rounded, shadowed window). Screenshotted at 4000x2200 to match the
 * existing assets.
 *
 * Edit the `sources` list per product, then run from the website workspace:
 *   cd websites/wpgraphql.com && node ../../design/brand/generate-wporg-screenshots.mjs
 */
import { chromium } from "@playwright/test"
import { fileURLToPath } from "node:url"
import fs from "node:fs"
import path from "node:path"
import { constellationSvg } from "./constellation.mjs"

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../")
const CLEANSHOT = "/Users/jasonbahl/Library/Application Support/CleanShot/media"

const acfMark = `<svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
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
</svg>`

const wpgMark = `<svg viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg">
  <circle cx="256" cy="256" r="256" fill="#0E1628"/>
  <path fill-rule="nonzero" fill="#FF8C1A" d="m117.592 300.896c0-35.138.58-39.429 7.074-52.301 5.682-11.133 20.758-25.05 30.732-28.065 2.203-.696 2.899.348 6.726 9.858 12.408 31.195 37.11 54.505 69.349 65.29l8.465 2.899.348 16.815c.116 9.394-.116 16.932-.58 16.816-.58 0-2.899-3.131-5.45-6.958-11.945-18.671-35.718-30.036-59.724-28.645-21.802 1.276-40.589 12.061-52.765 30.152l-4.175 6.147zm25.165 85.353c10.09-3.015 17.743-13.568 17.743-24.47 0-7.77 9.51-16.699 17.627-16.699 10.321 0 17.396 6.958 18.787 18.44 1.276 10.32 5.567 16.815 14.032 21.337 4.407 2.436 6.147 2.552 32.471 2.552 26.441 0 28.065-.116 32.588-2.552 5.566-3.015 11.712-9.51 12.872-14.032.58-1.74.928-25.049.928-51.838v-48.706l-2.9-5.103c-4.87-8.582-10.437-11.597-24.469-13.452-19.019-2.436-30.036-7.538-41.053-18.787-8.117-8.118-14.96-21.57-16.815-33.051-3.71-21.918 7.19-46.503 26.325-59.26 11.48-7.654 20.526-10.437 33.979-10.437 8.813 0 12.64.58 19.25 2.9 14.728 5.218 25.745 14.031 33.515 27.02 8.234 13.916 8.002 10.205 8.698 94.514.58 68.885.928 76.539 2.783 82.337 6.146 19.02 18.903 34.559 34.443 42.097 21.338 10.437 42.212 11.133 60.767 2.087 19.019-9.393 33.747-30.615 37.69-54.389 2.435-14.612-1.16-23.193-11.83-28.528-10.32-5.219-21.917-3.827-29.107 3.479-4.639 4.639-6.262 8.118-8.234 17.86-2.551 12.06-8.118 17.394-18.323 17.394-6.378 0-12.524-3.247-15.424-8.233-2.203-3.827-2.319-6.61-2.899-78.743-.58-66.566-.812-75.727-2.667-82.801-12.409-47.895-49.403-80.366-98.69-86.513-24.584-3.015-56.94 6.843-78.858 24.354-17.627 13.916-29.108 30.615-36.53 52.997l-3.479 9.974-11.944 4.29c-19.02 6.727-28.645 12.641-42.909 26.441-12.872 12.525-21.802 26.441-27.6 43.14-5.335 15.772-5.799 21.339-5.799 75.844v51.374l2.668 5.102c3.015 5.683 10.089 11.25 16.003 12.64 2.204.465 14.38.929 27.253 1.044 17.511.116 24.701-.347 29.108-1.623zm132.204-172.793c6.03-2.551 8.35-4.87 11.48-11.597 4.523-9.625 3.248-20.526-3.362-28.064-4.755-5.45-9.51-7.306-18.555-7.306-6.03 0-8.234.58-12.64 3.363-15.077 9.51-14.265 34.79 1.39 42.792 6.147 3.016 15.425 3.363 21.687.812z"/>
</svg>`

const products = [
  {
    slug: "wp-graphql",
    accent: "#FF8C1A",
    accentRgb: "255,140,26",
    mark: wpgMark,
    name: "WPGraphQL",
    accentWord: "",
    ext: "jpg",
    seed: 7, // match the core banner constellation
    sources: [
      `${CLEANSHOT}/media_LNR8wc4miM/CleanShot 2026-06-03 at 11.16.26.png`,
      `${CLEANSHOT}/media_5pEX74N5aQ/CleanShot 2026-06-03 at 11.16.46.png`,
    ],
  },
  {
    slug: "wp-graphql-acf",
    accent: "#10B981",
    accentRgb: "16,185,129",
    mark: acfMark,
    name: "WPGraphQL",
    accentWord: "ACF",
    ext: "jpg",
    seed: 23, // match the ACF banner constellation
    sources: [
      `${CLEANSHOT}/media_XbPKB9wIi8/CleanShot 2026-06-03 at 09.33.37.png`,
      `${CLEANSHOT}/media_NbqDoViNff/CleanShot 2026-06-03 at 09.34.28.png`,
      `${CLEANSHOT}/media_xQKxF7mAvl/CleanShot 2026-06-03 at 09.36.35.png`,
    ],
  },
]

const dataUri = (file) =>
  `data:image/png;base64,${fs.readFileSync(file).toString("base64")}`

const frameHtml = (p, imgUri) => `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&display=swap">
<style>
  html,body{margin:0;padding:0}
  .frame{width:2000px;height:1100px;background:#080D18;position:relative;overflow:hidden;
    display:flex;flex-direction:column;font-family:'Bricolage Grotesque',system-ui,sans-serif}
  .net{position:absolute;inset:0;z-index:0;opacity:0.5;pointer-events:none}
  .net svg{display:block;width:100%;height:100%}
  .glow{position:absolute;inset:0;z-index:0;pointer-events:none;background:
    radial-gradient(ellipse 1100px 760px at 76% -12%, rgba(${p.accentRgb},0.16) 0%, rgba(${p.accentRgb},0.05) 45%, transparent 70%),
    radial-gradient(ellipse 700px 560px at 8% 116%, rgba(${p.accentRgb},0.06) 0%, transparent 64%)}
  .head{position:relative;z-index:2;display:flex;align-items:center;gap:16px;padding:46px 88px 0}
  .head .mk{width:46px;height:46px;display:block}
  .head .wm{font-weight:800;font-size:31px;line-height:1;letter-spacing:-0.03em;color:#F0F4FF}
  .head .wm .a{color:${p.accent}}
  .stage{position:relative;z-index:2;flex:1;display:flex;align-items:center;justify-content:center;padding:34px 88px 84px}
  .win{width:1580px;border-radius:16px;overflow:hidden;background:#0C1220;
    border:1px solid rgba(${p.accentRgb},0.22);
    box-shadow:0 44px 110px -24px rgba(0,0,0,0.72), 0 0 90px -26px rgba(${p.accentRgb},0.28)}
  .win img{display:block;width:100%;height:auto}
</style></head>
<body>
  <div class="frame">
    <div class="net">${constellationSvg(2000, 1100, p.accentRgb, p.seed ?? 7, { clearFactor: 0.52, density: 0.7 })}</div>
    <div class="glow"></div>
    <div class="head">
      <div class="mk">${p.mark}</div>
      <div class="wm">${p.name} <span class="a">${p.accentWord}</span></div>
    </div>
    <div class="stage"><div class="win"><img src="${imgUri}"/></div></div>
  </div>
</body></html>`

// CLI: pass plugin slugs to limit which products are generated.
const slugs = process.argv.slice(2).filter((a) => !a.startsWith("--"))
const selected = slugs.length
  ? products.filter((p) => slugs.includes(p.slug))
  : products

const run = async () => {
  const browser = await chromium.launch()
  for (const p of selected) {
    for (let i = 0; i < p.sources.length; i++) {
      const src = p.sources[i]
      if (!fs.existsSync(src)) {
        console.warn(`SKIP missing source: ${src}`)
        continue
      }
      const page = await browser.newPage({
        viewport: { width: 2000, height: 1100 },
        deviceScaleFactor: 2,
      })
      await page.setContent(frameHtml(p, dataUri(src)), {
        waitUntil: "networkidle",
      })
      await page.evaluate(() => document.fonts.ready)
      const outPath = path.join(
        ROOT,
        "plugins",
        p.slug,
        ".wordpress-org",
        `screenshot-${i + 1}.${p.ext}`
      )
      await page.screenshot({
        path: outPath,
        type: p.ext === "jpg" ? "jpeg" : "png",
        ...(p.ext === "jpg" ? { quality: 92 } : {}),
      })
      await page.close()
      console.log(`wrote screenshot-${i + 1}.${p.ext} for ${p.slug}`)
    }
  }
  await browser.close()
}

run().catch((e) => {
  console.error(e)
  process.exit(1)
})

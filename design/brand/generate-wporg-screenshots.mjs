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

const products = [
  {
    slug: "wp-graphql-acf",
    accent: "#10B981",
    accentRgb: "16,185,129",
    mark: acfMark,
    name: "WPGraphQL",
    accentWord: "ACF",
    ext: "jpg",
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
  .glow{position:absolute;inset:0;pointer-events:none;background:
    radial-gradient(ellipse 1100px 760px at 76% -12%, rgba(${p.accentRgb},0.16) 0%, rgba(${p.accentRgb},0.05) 45%, transparent 70%),
    radial-gradient(ellipse 700px 560px at 8% 116%, rgba(${p.accentRgb},0.06) 0%, transparent 64%)}
  .head{position:relative;display:flex;align-items:center;gap:16px;padding:46px 88px 0}
  .head .mk{width:46px;height:46px;display:block}
  .head .wm{font-weight:800;font-size:31px;line-height:1;letter-spacing:-0.03em;color:#F0F4FF}
  .head .wm .a{color:${p.accent}}
  .stage{position:relative;flex:1;display:flex;align-items:center;justify-content:center;padding:34px 88px 84px}
  .win{width:1580px;border-radius:16px;overflow:hidden;background:#0C1220;
    border:1px solid rgba(${p.accentRgb},0.22);
    box-shadow:0 44px 110px -24px rgba(0,0,0,0.72), 0 0 90px -26px rgba(${p.accentRgb},0.28)}
  .win img{display:block;width:100%;height:auto}
</style></head>
<body>
  <div class="frame">
    <div class="glow"></div>
    <div class="head">
      <div class="mk">${p.mark}</div>
      <div class="wm">${p.name} <span class="a">${p.accentWord}</span></div>
    </div>
    <div class="stage"><div class="win"><img src="${imgUri}"/></div></div>
  </div>
</body></html>`

const run = async () => {
  const browser = await chromium.launch()
  for (const p of products) {
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

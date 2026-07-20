# AI Camera Helmet Detection — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the mock AI Camera helmet-detection UI into a real, in-browser detector using Roboflow inferencejs, controlled by a `.env` feature flag, with safe fallback to the existing mock when disabled or unconfigured.

**Architecture:** All inference runs client-side. `.env` → `config.php` → server-side guard in `ai-camera.php` → JS reads `window.__AI_CAMERA__` and either replays today's mock behavior (`runMockMode`) or wires 5 modules (`HelmetDetector`, `CameraStream`, `FrameLoop`, `ResultRenderer`, `ScanHistory`) for real capture + inference. No new PHP endpoint, no image upload to server.

**Tech Stack:** PHP 8+, vanilla JavaScript (no framework, no build step), Roboflow `inferencejs` (loaded from jsDelivr CDN), MediaDevices API, Canvas 2D, `localStorage`, existing CSS design tokens.

## Global Constraints

- **Privacy:** No endpoint on the PHP side ever receives camera frames or uploaded images. Everything stays in the browser.
- **Feature flag `AI_CAMERA_ENABLED` defaults to `false`** in `.env.example` — clean clones show the mock. Enabling requires all three of `AI_CAMERA_ENABLED=true`, `ROBOFLOW_PUBLISHABLE_KEY≠""`, `ROBOFLOW_MODEL≠""`. Server-side guard enforces this — client cannot lie about it.
- **Fallback path always works:** if the flag is off, or the key/model missing, or the Roboflow SDK fails to load, or the model fails to start, the page must fall back to today's mock behavior — never a blank screen and never a stack trace visible to the user.
- **No new external CSS/color values:** reuse `--green` / `--yellow` / `--cyan` / `--pink` / `--glass-fill` from `assets/css/style.css`.
- **Preserve inline `onclick="rescan()"`** in `ai-camera.php` lines 57 and 91 — `window.rescan` must remain a global function in both modes.
- **Cache-buster:** every CSS/JS reference in touched PHP files bumps `?v=5` → `?v=6` to defeat browser cache.
- **Testing:** the codebase has no JS test framework. Each task's verification is a **manual browser smoke test** with an explicit "expected" outcome. Do NOT install jest/vitest/playwright — that is out of scope.
- **Commits:** one commit per task, imperative subject, no attribution footer (git config disables it globally).

---

## File Structure

| File | Change | Responsibility |
|---|---|---|
| `.env` | Modify | Add `ROBOFLOW_PUBLISHABLE_KEY`, `ROBOFLOW_MODEL`; keep the `AI_CAMERA_ENABLED=true` line the user already added. |
| `.env.example` | Modify | Add all three vars with safe defaults (`enabled=false`, empty key/model). |
| `config.php` | Modify | Add three `define()` calls sourced from `env()`. |
| `ai-camera.php` | Modify | Add server-side guard, emit `window.__AI_CAMERA__`, add `Demo` badges on the two mock cards, add real scene DOM (`<video>` + `<canvas>` overlay), add real/demo indicator pill, add file input element, load Roboflow SDK conditionally. |
| `assets/js/ai-camera.js` | Rewrite | ~180 lines: `runMockMode()` (today's behavior verbatim), `runRealMode(cfg)` (wires 5 modules), `main()` (reads config and dispatches), plus the 5 modules. |
| `assets/css/shared-pages.css` | Modify | Small additions only: `.cam-scene.real` reveal rules, `.mode-pill`, `.demo-badge`, `.cam-status-banner`. Existing rules for mock scene untouched. |
| `docs/superpowers/plans/2026-07-20-ai-camera-helmet-detection.md` | Create | This file. |

---

## Task 1: Wire `.env` config through PHP into a client-safe JS bridge

**Files:**
- Modify: `.env` — add two lines after existing `AI_CAMERA_ENABLED=true`
- Modify: `.env.example` — add three lines at the end
- Modify: `config.php` — add three `define()` calls at the bottom
- Modify: `ai-camera.php` — add the `<?php` prelude before `<!DOCTYPE html>`

**Interfaces:**
- Produces:
  - PHP constants `AI_CAMERA_ENABLED` (bool), `ROBOFLOW_KEY` (string), `ROBOFLOW_MODEL` (string), available anywhere `config.php` is included.
  - Global `window.__AI_CAMERA__ = { enabled: boolean, key: string, model: string }` set before `assets/js/ai-camera.js` runs.
  - Server-side variable `$aiEnabled` inside `ai-camera.php` that equals `true` only when `AI_CAMERA_ENABLED && ROBOFLOW_KEY !== '' && ROBOFLOW_MODEL !== ''`.

- [ ] **Step 1: Add the two missing keys to `.env`** (leave user's `AI_CAMERA_ENABLED=true` untouched)

Append to `/Applications/XAMPP/xamppfiles/htdocs/Sieunhi_atgt/.env`:

```env
ROBOFLOW_PUBLISHABLE_KEY=
ROBOFLOW_MODEL=
```

Leave the values empty for now — the server guard will keep the site in mock mode until the user fills them in. That is intentional and will be tested.

- [ ] **Step 2: Mirror the three keys into `.env.example`** with safe defaults

Append to `/Applications/XAMPP/xamppfiles/htdocs/Sieunhi_atgt/.env.example`:

```env

# --- AI Camera ---
AI_CAMERA_ENABLED=false
ROBOFLOW_PUBLISHABLE_KEY=
ROBOFLOW_MODEL=
```

Note: `.env.example` gets `false` as the safe default for anyone cloning fresh.

- [ ] **Step 3: Add three `define()` calls to `config.php`**

Append to `/Applications/XAMPP/xamppfiles/htdocs/Sieunhi_atgt/config.php` (after the SMTP block):

```php

// --- AI Camera ---
defined('AI_CAMERA_ENABLED') || define('AI_CAMERA_ENABLED', (bool) env('AI_CAMERA_ENABLED', false));
defined('ROBOFLOW_KEY')      || define('ROBOFLOW_KEY',      env('ROBOFLOW_PUBLISHABLE_KEY', ''));
defined('ROBOFLOW_MODEL')    || define('ROBOFLOW_MODEL',    env('ROBOFLOW_MODEL', ''));
```

- [ ] **Step 4: Verify constants resolve correctly**

Run:
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/Sieunhi_atgt
php -r 'require_once "config.php"; var_export([
  "enabled" => AI_CAMERA_ENABLED,
  "key_len" => strlen(ROBOFLOW_KEY),
  "model"   => ROBOFLOW_MODEL,
]);'
```

Expected output (exact):
```
array (
  'enabled' => true,
  'key_len' => 0,
  'model' => '',
)
```

- [ ] **Step 5: Add PHP prelude to `ai-camera.php` computing `$aiEnabled`**

Replace line 1 of `ai-camera.php` (which is currently `<!DOCTYPE html>`) with:

```php
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php'; // for e() helper

$aiEnabled = AI_CAMERA_ENABLED && ROBOFLOW_KEY !== '' && ROBOFLOW_MODEL !== '';
?>
<!DOCTYPE html>
```

- [ ] **Step 6: Emit `window.__AI_CAMERA__` in the `<head>`** (still Task 1 — no JS reads it yet)

In `ai-camera.php`, immediately after the existing `<script>` on line 6 (the theme bootstrap), add:

```php
<script>
window.__AI_CAMERA__ = {
  enabled: <?= $aiEnabled ? 'true' : 'false' ?>,
  key:     "<?= $aiEnabled ? e(ROBOFLOW_KEY)   : '' ?>",
  model:   "<?= $aiEnabled ? e(ROBOFLOW_MODEL) : '' ?>"
};
</script>
```

- [ ] **Step 7: Verify the bridge in the browser**

Start XAMPP Apache (or `php -S 127.0.0.1:8000` from the project root). Open `http://localhost/Sieunhi_atgt/ai-camera.php`, open DevTools Console, run:

```js
window.__AI_CAMERA__
```

Expected: `{ enabled: false, key: "", model: "" }` — because `.env` has `AI_CAMERA_ENABLED=true` but empty key/model, so server guard flips `$aiEnabled` to false. Confirm no PHP notice/warning appears in the page source.

- [ ] **Step 8: Commit**

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/Sieunhi_atgt
git add .env.example config.php ai-camera.php
git commit -m "feat(ai-camera): wire AI_CAMERA_ENABLED / ROBOFLOW_KEY / ROBOFLOW_MODEL from .env through to window.__AI_CAMERA__

Server-side guard sets \$aiEnabled=true only when the flag is on AND
both key and model are non-empty. When guard is false, key and model
are emitted as empty strings — the client cannot bypass the flag."
```

Do NOT commit `.env` (it's git-ignored by design).

---

## Task 2: DOM scaffolding — dual scenes, Demo badges, mode pill, file input

**Files:**
- Modify: `ai-camera.php` — replace the `.cam-scene` block, add Demo badges on the two mock result cards, add mode-pill under `.upload-hint`, add hidden `<input type="file">`, add optional `<script>` for the Roboflow SDK.
- Modify: `assets/css/shared-pages.css` — append `.cam-scene.real`, `.mode-pill`, `.demo-badge`, `.cam-status-banner` rules.

**Interfaces:**
- Produces (for Task 4+):
  - `#camVideo` — hidden `<video>` element (`autoplay`, `playsinline`, `muted`) that CameraStream writes to.
  - `#camCanvas` — `<canvas>` positioned over the video, sized to match on resize.
  - `#camFileInput` — hidden `<input type="file" accept="image/*">` for the upload tab.
  - `#camMockScene` — the existing mock DOM, wrapped in a container div so it can be hidden by adding class `.real` to the parent `.cam-scene`.
  - `#camModePill` — the "● AI thật / ● Demo" indicator, visible under the camera controls.
  - `.cam-status-banner` slot inside `.cam-view` — used later for "chưa kết nối được model AI" and similar messages.

- [ ] **Step 1: Replace the `.cam-scene` block in `ai-camera.php`** (currently lines 45–53)

Old (delete):
```html
      <div class="cam-scene">
        <div class="street"></div>
        <div class="lane"></div>
        <div class="kid">🧒</div>
        <div class="helmet-box"></div>
        <div class="sign-icon">🚸</div>
        <div class="sign-box"></div>
        <div class="scan-line"></div>
      </div>
```

New:
```html
      <div class="cam-scene" id="camScene">
        <!-- REAL scene: revealed by adding class "real" to #camScene -->
        <video id="camVideo" class="cam-real" autoplay playsinline muted></video>
        <canvas id="camCanvas" class="cam-real"></canvas>
        <div id="camStatusBanner" class="cam-status-banner" hidden></div>

        <!-- MOCK scene: visible by default -->
        <div id="camMockScene" class="cam-mock">
          <div class="street"></div>
          <div class="lane"></div>
          <div class="kid">🧒</div>
          <div class="helmet-box"></div>
          <div class="sign-icon">🚸</div>
          <div class="sign-box"></div>
          <div class="scan-line"></div>
        </div>
      </div>
```

- [ ] **Step 2: Add a hidden file input inside `.cam-panel`** (after the closing `</div>` of `.cam-controls`, before `.upload-hint`)

Insert into `ai-camera.php` between the existing `<div class="cam-controls">…</div>` and `<div class="upload-hint">…</div>`:

```html
    <input type="file" id="camFileInput" accept="image/jpeg,image/png" hidden>
```

- [ ] **Step 3: Add the mode-pill under `.upload-hint`**

Immediately after the `.upload-hint` line, add:

```html
    <div class="mode-pill" id="camModePill" data-mode="<?= $aiEnabled ? 'real' : 'demo' ?>">
      <span class="mode-dot"></span>
      <?= $aiEnabled ? 'AI thật' : 'Demo' ?>
    </div>
```

- [ ] **Step 4: Add Demo badges on the two non-real result cards**

In `ai-camera.php`, inside the two `.detect-item` blocks for "Biển báo khu vực trường học" (currently lines 74–78) and "Vị trí đứng — Cần chú ý" (lines 79–83), add a `<span class="demo-badge">Demo</span>` immediately after the `<b>` line:

Change:
```html
    <div class="detect-item ok">
      <div class="d-icon">🚸</div>
      <div class="d-info"><b>Biển báo khu vực trường học</b><span>Nhận diện rõ ràng, vị trí chính xác</span></div>
      <div class="detect-bar"><i></i></div>
    </div>
```

To:
```html
    <div class="detect-item ok">
      <div class="d-icon">🚸</div>
      <div class="d-info"><b>Biển báo khu vực trường học <span class="demo-badge">Demo</span></b><span>Nhận diện rõ ràng, vị trí chính xác</span></div>
      <div class="detect-bar"><i></i></div>
    </div>
```

Same change for the "Vị trí đứng" card.

Leave the "Mũ bảo hiểm — Đạt chuẩn" card untouched — that one becomes real.

- [ ] **Step 5: Conditionally load Roboflow SDK before `ai-camera.js`**

In `ai-camera.php`, replace the two closing `<script>` tags (currently lines 128–129) with:

```php
<script src="assets/js/main.js?v=6"></script>
<?php if ($aiEnabled): ?>
<script src="https://cdn.jsdelivr.net/npm/inferencejs"></script>
<?php endif; ?>
<script src="assets/js/ai-camera.js?v=6"></script>
```

The SDK only loads when the server guard passes. If `AI_CAMERA_ENABLED=false`, the site never touches jsDelivr — zero external requests, matching the current mock's network profile.

- [ ] **Step 6: Bump the CSS version too**

In `ai-camera.php`, change both stylesheet lines (currently lines 10–11):

```html
<link rel="stylesheet" href="assets/css/style.css?v=6">
<link rel="stylesheet" href="assets/css/shared-pages.css?v=6">
```

- [ ] **Step 7: Append the four new CSS rules to `assets/css/shared-pages.css`**

Append at the very end of the file:

```css
/* ---------------- AI CAMERA — real/mock switching ---------------- */
.cam-real {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: none;
  pointer-events: none;
  border-radius: 16px;
}
#camScene.real .cam-real  { display: block; }
#camScene.real #camMockScene { display: none; }

.cam-status-banner {
  position: absolute;
  left: 50%;
  bottom: 14px;
  transform: translateX(-50%);
  background: rgba(7, 27, 58, 0.85);
  border: 1px solid var(--glass-border);
  color: var(--white);
  font-size: 12.5px;
  font-weight: 600;
  padding: 8px 14px;
  border-radius: 999px;
  backdrop-filter: blur(8px);
  z-index: 3;
  max-width: calc(100% - 32px);
  text-align: center;
}

.demo-badge {
  display: inline-block;
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.55);
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.14);
  padding: 2px 7px;
  border-radius: 6px;
  margin-left: 8px;
  vertical-align: middle;
}

.mode-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 8px 16px 12px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 0.04em;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--glass-border);
  color: rgba(255, 255, 255, 0.72);
  width: fit-content;
}
.mode-pill .mode-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.35);
}
.mode-pill[data-mode="real"] .mode-dot {
  background: var(--green);
  box-shadow: 0 0 8px var(--green);
}
.mode-pill[data-mode="real"] {
  color: var(--green);
  border-color: rgba(46, 204, 113, 0.35);
}
```

- [ ] **Step 8: Verify DOM in browser (mock mode still intact)**

Reload `http://localhost/Sieunhi_atgt/ai-camera.php`. Expected:
- Page looks identical to before, mock scene visible (kid emoji, helmet box overlay with "⛑️ Mũ bảo hiểm · 98%").
- Under the camera controls: a small pill reading `● Demo` (grey).
- The "Biển báo khu vực trường học" and "Vị trí đứng — Cần chú ý" cards each show a small `DEMO` badge next to the title.
- The "Mũ bảo hiểm — Đạt chuẩn" card has no such badge.
- DevTools Elements pane: `#camVideo`, `#camCanvas`, `#camFileInput`, `#camMockScene`, `#camStatusBanner` all exist. `#camScene` does NOT have class `real`, and the `<script src="…inferencejs">` tag is absent (because guard is false).
- DevTools Network pane on reload: no requests to `jsdelivr.net`.

- [ ] **Step 9: Commit**

```bash
git add ai-camera.php assets/css/shared-pages.css
git commit -m "feat(ai-camera): add dual mock/real scene DOM, Demo badges, mode pill

Real scene (<video>+<canvas>) is present but hidden until #camScene
gets class 'real'. Roboflow SDK loads conditionally on the server
guard. Mock scene continues to render byte-identically when disabled."
```

---

## Task 3: JS refactor — extract mock into `runMockMode()`, verify no behavior change

**Files:**
- Rewrite: `assets/js/ai-camera.js` (currently 26 lines) into a scaffold with `main()`, `runMockMode()`, and a stub `runRealMode()` that throws so we know it's not being reached.

**Interfaces:**
- Consumes: `window.__AI_CAMERA__` (from Task 1).
- Produces: `window.rescan` global (unchanged behavior in mock mode); private `runRealMode(cfg)` stub for Task 4+ to fill in.

- [ ] **Step 1: Manual "failing test" — read the current file**

Run `cat assets/js/ai-camera.js`. Confirm it's the 26-line mock. This is the baseline — the smoke test after Task 3 must produce identical UX for the disabled path.

- [ ] **Step 2: Replace the file with the scaffold**

Overwrite `/Applications/XAMPP/xamppfiles/htdocs/Sieunhi_atgt/assets/js/ai-camera.js`:

```js
/* ai-camera.js — Tương tác cho trang AI Camera
 *
 * Hai chế độ:
 *   - Mock (khi AI_CAMERA_ENABLED=false hoặc thiếu key/model)
 *     → giữ nguyên hành vi cũ: bấm "Quét lại" → random 94-98%.
 *   - Real (khi flag bật + có Roboflow key/model)
 *     → mở camera, load model, chạy inference thật.
 *
 * Server đã quyết định chế độ trong window.__AI_CAMERA__.
 * Client KHÔNG được phép tự bật real mode.
 */
(function () {
  'use strict';

  const cfg = window.__AI_CAMERA__ || { enabled: false, key: '', model: '' };

  document.addEventListener('DOMContentLoaded', () => {
    if (cfg.enabled) {
      runRealMode(cfg);
    } else {
      runMockMode();
    }
    wireTabs();
  });

  // ---------- MOCK MODE (byte-identical to trước khi refactor) ----------

  function runMockMode() {
    window.rescan = function () {
      const badge = document.getElementById('accBadge');
      const scanLine = document.querySelector('.scan-line');
      badge.textContent = '⏳ Đang phân tích...';
      badge.style.color = 'var(--cyan)';
      badge.style.background = 'rgba(34,211,238,0.15)';
      if (scanLine) scanLine.style.animationDuration = '0.6s';
      setTimeout(() => {
        const values = [94, 95, 96, 97, 98];
        const v = values[Math.floor(Math.random() * values.length)];
        badge.textContent = '✓ Độ chính xác ' + v + '%';
        badge.style.color = 'var(--green)';
        badge.style.background = 'rgba(52,211,153,0.15)';
        if (scanLine) scanLine.style.animationDuration = '2.6s';
      }, 1300);
    };
  }

  // ---------- REAL MODE (stub — filled in by Task 4+) ----------

  function runRealMode(_cfg) {
    // Task 4-9 will replace this. For now, fail loudly if it's ever reached
    // in a state Task 3 didn't expect.
    throw new Error('runRealMode not implemented yet');
  }

  // ---------- Shared: tab switcher ----------

  function wireTabs() {
    document.querySelectorAll('.cam-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.cam-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
      });
    });
  }
})();
```

- [ ] **Step 3: Verify mock parity in the browser**

Reload `http://localhost/Sieunhi_atgt/ai-camera.php`. `window.__AI_CAMERA__.enabled` is still `false` from Task 1 (key/model empty). Expected:

1. Click the 📸 shutter button. Badge changes to `⏳ Đang phân tích...`, then after ~1.3s shows `✓ Độ chính xác NN%` where `NN ∈ {94,95,96,97,98}`.
2. Click 🔁 Quét lại — same behavior.
3. Click a tab (`📷 Camera trực tiếp` / `🖼️ Tải ảnh lên`) — active tab visually switches.
4. DevTools Console: no errors, no `runRealMode not implemented yet` throw.

If ANY of the above differs from the pre-refactor behavior, stop and fix before committing.

- [ ] **Step 4: Commit**

```bash
git add assets/js/ai-camera.js
git commit -m "refactor(ai-camera): split JS into runMockMode/runRealMode, dispatch on window.__AI_CAMERA__

Behavior when AI_CAMERA_ENABLED=false is byte-identical to before —
same rescan() global, same random-96% animation. Real path is a stub
until Task 4-9 fill it in."
```

---

## Task 4: `HelmetDetector` — Roboflow inferencejs wrapper

**Files:**
- Modify: `assets/js/ai-camera.js` — add `HelmetDetector` factory before `runRealMode`.

**Interfaces:**
- Produces (for Tasks 6, 8):
  - `HelmetDetector.load(publishableKey, modelSlugWithVersion) → Promise<detector>`. `modelSlugWithVersion` is a string like `"helmet-detection-abcxyz/3"` — the wrapper splits on `/` to feed `startWorker(slug, version, key)`.
  - `detector.detect(mediaElement) → Promise<Detection[]>` where `Detection = { className: string, confidence: number /* 0..1 */, bbox: { x: number, y: number, width: number, height: number } }`. `mediaElement` is any `<video>`, `<img>`, or `<canvas>`.
  - `detector.destroy() → Promise<void>` — releases the Roboflow worker (called on tab teardown).
  - Rejects `load()` if `window.inferencejs` is missing or `startWorker` throws — the caller uses that rejection to trigger fallback.

- [ ] **Step 1: Verify Roboflow SDK global**

With `AI_CAMERA_ENABLED=true` + a real key/model temporarily set in `.env` (for this task's testing only), reload the page. In DevTools Console:

```js
typeof inferencejs
```

Expected: `"object"`. If `"undefined"`, the CDN failed to load and the fallback path (Task 9) will need to catch it. For this task, ensure the SDK is present so we're testing the happy path.

- [ ] **Step 2: Add `HelmetDetector` inside the IIFE, above `runRealMode`**

In `ai-camera.js`, insert this block immediately after the `// ---------- REAL MODE ... ` comment and BEFORE `function runRealMode`:

```js
  // ---------- HelmetDetector: wrapper for Roboflow inferencejs ----------

  const HelmetDetector = {
    /**
     * Load a model. modelSlugWithVersion e.g. "helmet-detection-abcxyz/3".
     * Rejects if SDK missing or startWorker throws — callers must handle.
     */
    async load(publishableKey, modelSlugWithVersion) {
      if (!window.inferencejs) {
        throw new Error('inferencejs SDK not loaded');
      }
      const slash = modelSlugWithVersion.lastIndexOf('/');
      if (slash < 1) {
        throw new Error('ROBOFLOW_MODEL must be "<slug>/<version>"');
      }
      const slug = modelSlugWithVersion.slice(0, slash);
      const version = modelSlugWithVersion.slice(slash + 1);

      const { InferenceEngine, CVImage } = window.inferencejs;
      const engine = new InferenceEngine();
      const workerId = await engine.startWorker(slug, version, publishableKey);

      return {
        async detect(mediaElement) {
          const image = new CVImage(mediaElement);
          const raw = await engine.infer(workerId, image);
          // Normalize Roboflow's shape to our Detection interface.
          return (raw || []).map(p => ({
            className: p.class || 'helmet',
            confidence: typeof p.confidence === 'number' ? p.confidence : (p.score || 0),
            bbox: {
              x: p.bbox?.x ?? p.x ?? 0,
              y: p.bbox?.y ?? p.y ?? 0,
              width:  p.bbox?.width  ?? p.width  ?? 0,
              height: p.bbox?.height ?? p.height ?? 0,
            },
          }));
        },
        async destroy() {
          try { await engine.stopWorker(workerId); } catch (_) { /* ignore */ }
        },
      };
    },
  };
```

- [ ] **Step 3: Sanity-check the load path without wiring UI yet**

In `runRealMode`, temporarily replace the `throw` line with:

```js
    HelmetDetector.load(_cfg.key, _cfg.model)
      .then(det => { window.__det = det; console.log('detector ready'); })
      .catch(err => console.error('detector load failed', err));
```

Reload the page (with real key/model in `.env`). Expected in Console:

- `detector ready` within ~5-15 seconds (first-time model download).
- Second reload: `detector ready` in <1 second (IndexedDB cache).

Then run:
```js
await window.__det.detect(document.querySelector('h1'))
```
Expected: `[]` (heading isn't a helmet). Confirms the wrapper accepts a DOM element and returns an array without throwing.

- [ ] **Step 4: Revert the temporary sanity-check**

Put the `throw new Error('runRealMode not implemented yet')` back in `runRealMode`. Task 6 will wire the detector properly.

- [ ] **Step 5: Commit**

```bash
git add assets/js/ai-camera.js
git commit -m "feat(ai-camera): add HelmetDetector wrapper around Roboflow inferencejs

Splits model string on '/', normalises Roboflow's prediction shape to
our internal Detection type, exposes destroy() so callers can stop
the worker on cleanup. Rejects clearly when the SDK is missing so
Task 9's fallback can catch it."
```

---

## Task 5: `CameraStream` — `getUserMedia` with permission handling

**Files:**
- Modify: `assets/js/ai-camera.js` — add `CameraStream` factory below `HelmetDetector`.

**Interfaces:**
- Produces (for Task 6):
  - `CameraStream.start(videoEl) → Promise<{ stop() }>`. Requests the user's camera at 1280×720 preferring the front camera, wires the stream to `videoEl`, resolves once the video has loaded metadata (so its `videoWidth`/`videoHeight` are trustworthy).
  - Rejects with an `Error` whose `.name` is one of:
    - `"NotAllowedError"` — user denied permission.
    - `"NotFoundError"` — no camera device.
    - `"NotSecureError"` — `getUserMedia` unavailable (HTTP + non-localhost). This is a custom name we set; MediaDevices' native error for this varies.
    - `"UnknownError"` — anything else.
  - `.stop()` on the returned handle calls `getTracks().forEach(t => t.stop())` and clears `videoEl.srcObject`.

- [ ] **Step 1: Add `CameraStream` in `ai-camera.js`**, immediately after the `HelmetDetector` block:

```js
  // ---------- CameraStream: getUserMedia wrapper ----------

  const CameraStream = {
    async start(videoEl) {
      if (!navigator.mediaDevices?.getUserMedia) {
        const err = new Error('getUserMedia unavailable — dùng localhost hoặc HTTPS');
        err.name = 'NotSecureError';
        throw err;
      }

      let stream;
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
          audio: false,
        });
      } catch (nativeErr) {
        // Preserve name for the caller's switch/case.
        throw nativeErr;
      }

      videoEl.srcObject = stream;
      await new Promise(resolve => {
        if (videoEl.readyState >= 2) return resolve();
        videoEl.onloadedmetadata = () => resolve();
      });
      await videoEl.play().catch(() => { /* autoplay policies — muted+playsinline should be fine */ });

      return {
        stop() {
          stream.getTracks().forEach(t => t.stop());
          videoEl.srcObject = null;
        },
      };
    },
  };
```

- [ ] **Step 2: Sanity check with a throwaway call**

Temporarily in `runRealMode`, replace the throw with:

```js
    CameraStream.start(document.getElementById('camVideo'))
      .then(h => { window.__cam = h; console.log('camera on', h); })
      .catch(err => console.warn('camera failed', err.name, err.message));
```

Reload the page. Browser should prompt for camera permission. Expected:

- Allow → console `camera on {stop: ƒ}`, `#camVideo` starts streaming (still hidden behind mock scene — that's fine, Task 6 reveals it).
- Deny → console `camera failed NotAllowedError …`. No crash, no unhandled rejection.

Run `window.__cam.stop()` — camera light on your machine turns off.

- [ ] **Step 3: Revert `runRealMode` back to the throw**

- [ ] **Step 4: Commit**

```bash
git add assets/js/ai-camera.js
git commit -m "feat(ai-camera): add CameraStream wrapper with typed permission errors

Distinguishes NotAllowedError / NotFoundError / NotSecureError /
UnknownError so the UI layer can show tailored copy. stop() cleanly
releases both the tracks and the srcObject reference."
```

---

## Task 6: `FrameLoop` + `ResultRenderer` — wire live-camera tab end-to-end

**Files:**
- Modify: `assets/js/ai-camera.js` — add `FrameLoop`, `ResultRenderer`, replace `runRealMode` body with the real orchestration for the live-camera tab only. Upload tab still no-op (Task 8).

**Interfaces:**
- Consumes: `HelmetDetector.load`, `CameraStream.start`.
- Produces (for Task 7):
  - `FrameLoop.start(callback) → { stop() }`. Calls `callback()` at ~2 fps using `setTimeout` + `requestAnimationFrame`. Awaits the callback's promise (if any) before scheduling the next tick — a slow detector self-throttles instead of piling up.
  - `ResultRenderer` singleton with methods:
    - `.updateBadge(text, tier)` where `tier ∈ 'ok'|'warn'|'idle'|'analyzing'`.
    - `.drawBoxes(canvas, videoEl, detections)` — clears + resizes canvas to match `videoEl` intrinsic size, draws each `Detection` with a colored `strokeRect` + label pill.
    - `.updateHelmetCard(topDetection | null)` — updates the "Mũ bảo hiểm" card (first `.detect-item.ok` in `.result-panel`) and the "Lời khuyên từ AI" copy per confidence tier.
    - `.setStatus(text | null)` — shows/hides `#camStatusBanner` inside `.cam-view`.
  - `runRealMode(cfg)` mounts everything and exposes `window.__aiCam` = `{ startCamera, stopCamera }` for the shutter button.

- [ ] **Step 1: Add `FrameLoop` and `ResultRenderer` above `runRealMode`**

Insert in `ai-camera.js` between `CameraStream` and `runRealMode`:

```js
  // ---------- FrameLoop: throttled tick, self-paces to detector latency ----------

  const FrameLoop = {
    start(callback, intervalMs = 500) {
      let stopped = false;
      let timer = null;

      async function tick() {
        if (stopped) return;
        try { await callback(); } catch (e) { console.warn('frame callback error', e); }
        if (stopped) return;
        timer = setTimeout(() => requestAnimationFrame(tick), intervalMs);
      }

      requestAnimationFrame(tick);

      return {
        stop() {
          stopped = true;
          if (timer) clearTimeout(timer);
        },
      };
    },
  };

  // ---------- ResultRenderer: draw bboxes + update side panel + banner ----------

  const ResultRenderer = (function () {
    const CONF_HIGH = 0.60;
    const CONF_MID  = 0.30;

    function tierFor(conf) {
      if (conf >= CONF_HIGH) return 'ok';
      if (conf >= CONF_MID)  return 'warn';
      return 'idle';
    }

    function colorFor(tier) {
      switch (tier) {
        case 'ok':   return getComputedStyle(document.documentElement).getPropertyValue('--green').trim() || '#2ecc71';
        case 'warn': return getComputedStyle(document.documentElement).getPropertyValue('--yellow').trim() || '#f5b642';
        default:     return 'rgba(255,255,255,0.35)';
      }
    }

    return {
      updateBadge(text, tier) {
        const badge = document.getElementById('accBadge');
        if (!badge) return;
        badge.textContent = text;
        const map = {
          ok:        { color: 'var(--green)',  bg: 'rgba(46,204,113,0.15)' },
          warn:      { color: 'var(--yellow)', bg: 'rgba(245,182,66,0.15)' },
          idle:      { color: 'rgba(255,255,255,0.55)', bg: 'rgba(255,255,255,0.06)' },
          analyzing: { color: 'var(--cyan)',   bg: 'rgba(79,195,247,0.15)' },
        };
        const s = map[tier] || map.idle;
        badge.style.color = s.color;
        badge.style.background = s.bg;
      },

      drawBoxes(canvas, videoEl, detections) {
        const w = videoEl.videoWidth  || videoEl.naturalWidth  || canvas.clientWidth;
        const h = videoEl.videoHeight || videoEl.naturalHeight || canvas.clientHeight;
        if (canvas.width !== w)  canvas.width  = w;
        if (canvas.height !== h) canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, w, h);
        for (const d of detections) {
          const tier = tierFor(d.confidence);
          if (tier === 'idle') continue;
          const stroke = colorFor(tier);
          const x = d.bbox.x - d.bbox.width / 2;
          const y = d.bbox.y - d.bbox.height / 2;
          ctx.lineWidth = 3;
          ctx.strokeStyle = stroke;
          ctx.strokeRect(x, y, d.bbox.width, d.bbox.height);

          const label = `⛑️ ${d.className} · ${Math.round(d.confidence * 100)}%`;
          ctx.font = 'bold 16px "Be Vietnam Pro", sans-serif';
          const pad = 6;
          const tw = ctx.measureText(label).width;
          ctx.fillStyle = 'rgba(7,27,58,0.85)';
          ctx.fillRect(x, y - 26, tw + pad * 2, 22);
          ctx.fillStyle = stroke;
          ctx.fillText(label, x + pad, y - 10);
        }
      },

      updateHelmetCard(top) {
        // First .detect-item.ok in .result-panel is the helmet card.
        const card = document.querySelector('.result-panel .detect-item');
        if (!card) return;
        const title = card.querySelector('.d-info b');
        const sub   = card.querySelector('.d-info span');
        const advice = document.querySelector('.advice-box p');

        if (!top) {
          card.classList.remove('ok', 'warn');
          card.classList.add('warn');
          if (title) title.childNodes[0].nodeValue = 'Chưa thấy mũ bảo hiểm ';
          if (sub)   sub.textContent = 'Hãy đưa mũ vào giữa khung camera';
          if (advice) advice.textContent = 'Chưa thấy mũ bảo hiểm trong khung. Con hãy đội mũ hoặc đưa mũ vào giữa khung camera nhé.';
          return;
        }
        const tier = tierFor(top.confidence);
        card.classList.remove('ok', 'warn');
        if (tier === 'ok') {
          card.classList.add('ok');
          if (title) title.childNodes[0].nodeValue = 'Mũ bảo hiểm — Đạt chuẩn ';
          if (sub)   sub.textContent = `Model tự tin ${Math.round(top.confidence * 100)}%`;
          if (advice) advice.textContent = 'Con đội mũ bảo hiểm rất chuẩn rồi! Nhớ luôn cài quai và quan sát hai bên trước khi qua đường nhé.';
        } else {
          card.classList.add('warn');
          if (title) title.childNodes[0].nodeValue = 'Mũ bảo hiểm — Chưa rõ ';
          if (sub)   sub.textContent = `Chỉ ${Math.round(top.confidence * 100)}% chắc chắn — hãy đưa mũ vào chính giữa khung`;
          if (advice) advice.textContent = 'Model chưa nhìn rõ mũ bảo hiểm. Con thử đưa mũ vào giữa khung, cách camera 1 sải tay nhé.';
        }
      },

      setStatus(text) {
        const el = document.getElementById('camStatusBanner');
        if (!el) return;
        if (!text) { el.hidden = true; el.textContent = ''; return; }
        el.hidden = false;
        el.textContent = text;
      },
    };
  })();
```

- [ ] **Step 2: Replace `runRealMode` body — live-camera tab wiring**

Replace the entire `runRealMode` function with:

```js
  function runRealMode(cfg) {
    const scene    = document.getElementById('camScene');
    const videoEl  = document.getElementById('camVideo');
    const canvasEl = document.getElementById('camCanvas');
    const shutter  = document.querySelector('.cam-controls .ctrl-btn.shutter');

    let detector = null;   // set after HelmetDetector.load resolves
    let camHandle = null;  // set while camera is active
    let loop = null;

    async function ensureDetector() {
      if (detector) return detector;
      ResultRenderer.updateBadge('⏳ Đang tải model...', 'analyzing');
      ResultRenderer.setStatus('Đang tải model AI (chỉ lần đầu)...');
      detector = await HelmetDetector.load(cfg.key, cfg.model);
      ResultRenderer.setStatus(null);
      return detector;
    }

    async function startCamera() {
      try {
        await ensureDetector();
        camHandle = await CameraStream.start(videoEl);
        scene.classList.add('real');
        ResultRenderer.updateBadge('🔴 AI đang phân tích', 'analyzing');
        loop = FrameLoop.start(async () => {
          const dets = await detector.detect(videoEl);
          ResultRenderer.drawBoxes(canvasEl, videoEl, dets);
          const top = dets.length
            ? dets.reduce((a, b) => (a.confidence >= b.confidence ? a : b))
            : null;
          if (top && top.confidence >= 0.60) {
            ResultRenderer.updateBadge(`✓ Độ chính xác ${Math.round(top.confidence * 100)}%`, 'ok');
          } else if (top) {
            ResultRenderer.updateBadge(`⚠ Chưa rõ ${Math.round(top.confidence * 100)}%`, 'warn');
          } else {
            ResultRenderer.updateBadge('⏸ Chưa thấy mũ', 'idle');
          }
          ResultRenderer.updateHelmetCard(top);
        });
      } catch (err) {
        stopCamera();
        handleCameraError(err);
      }
    }

    function stopCamera() {
      if (loop)      { loop.stop();    loop = null; }
      if (camHandle) { camHandle.stop(); camHandle = null; }
      scene.classList.remove('real');
      // Clear canvas so no stale bbox lingers.
      const ctx = canvasEl.getContext('2d');
      ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
    }

    function handleCameraError(err) {
      const map = {
        NotAllowedError: 'Bạn chưa cho phép camera. Bấm biểu tượng ổ khoá ở thanh địa chỉ để bật lại.',
        NotFoundError:   'Không tìm thấy camera trên thiết bị này.',
        NotSecureError:  'Trình duyệt yêu cầu HTTPS. Mở qua http://localhost hoặc bật HTTPS.',
      };
      const msg = map[err.name] || `Không mở được camera: ${err.message}`;
      ResultRenderer.setStatus(msg);
      ResultRenderer.updateBadge('⚠ Camera lỗi', 'warn');
    }

    // Shutter toggles camera on/off. Both onclick="rescan()" spots in the
    // markup still call window.rescan below — in real mode "rescan" means
    // "restart the camera loop".
    if (shutter) {
      shutter.addEventListener('click', () => {
        if (camHandle) stopCamera(); else startCamera();
      });
    }

    window.rescan = function () {
      if (!camHandle) startCamera();
      // If already streaming, do nothing — the loop is live.
    };

    window.__aiCam = { startCamera, stopCamera };
  }
```

- [ ] **Step 3: Manual verification with `AI_CAMERA_ENABLED=true` and a real key**

Reload the page. Expected:

1. Page loads with the mock scene visible, mode-pill reads `● AI thật` (green).
2. Click the 📸 shutter button. Browser prompts for camera. Allow.
3. Badge shows `⏳ Đang tải model...` then `🔴 AI đang phân tích` once ready. Mock scene is replaced by live video with canvas overlay.
4. Hold a bike helmet up (or a printed picture of one) — a green box with `⛑️ helmet · NN%` appears; the "Mũ bảo hiểm" card copy updates; "Lời khuyên từ AI" reflects tier.
5. Take the helmet away — badge falls to `⏸ Chưa thấy mũ`, card copy says "Chưa thấy mũ bảo hiểm".
6. Click the shutter again — camera stops, live scene hides, mock scene returns, no stale bbox on canvas.
7. Deny permission on the prompt — status banner reads "Bạn chưa cho phép camera...". No console errors.

- [ ] **Step 4: Commit**

```bash
git add assets/js/ai-camera.js
git commit -m "feat(ai-camera): wire live-camera tab end-to-end (FrameLoop + ResultRenderer)

Detector + camera + 2fps loop + bbox drawing + side-panel updates.
Shutter toggles the stream. Confidence tiers: >=60% green,
30-60% yellow, else idle. Errors show as an in-scene status banner
with tailored copy per DOMException name."
```

---

## Task 7: `ScanHistory` — persist last 5 detections in `localStorage`

**Files:**
- Modify: `assets/js/ai-camera.js` — add `ScanHistory` module, call it from the FrameLoop callback, render on load.

**Interfaces:**
- Produces:
  - `ScanHistory.push(detection)` — adds to the head, trims to 5, writes back to `localStorage['sieunhi.aicam.history']`. Debounced: only pushes when tier changes (no spam every 500 ms).
  - `ScanHistory.render()` — replaces the innerHTML of `.history-strip` with 5 thumb elements reflecting the current stored history.
- Storage schema: `[{ tier: 'ok'|'warn', confidence: number, ts: number }, ...]`. No image, no PII — just the tier + confidence + a timestamp so future work can compute streaks.

- [ ] **Step 1: Add `ScanHistory` in `ai-camera.js`**, after `ResultRenderer`:

```js
  // ---------- ScanHistory: last-5 tier log in localStorage ----------

  const ScanHistory = (function () {
    const KEY = 'sieunhi.aicam.history';
    const MAX = 5;

    function read() {
      try {
        const raw = localStorage.getItem(KEY);
        if (!raw) return [];
        const arr = JSON.parse(raw);
        return Array.isArray(arr) ? arr.slice(0, MAX) : [];
      } catch (_) { return []; }
    }
    function write(arr) {
      try { localStorage.setItem(KEY, JSON.stringify(arr.slice(0, MAX))); } catch (_) { /* quota */ }
    }

    return {
      push(det) {
        const tier = det.confidence >= 0.60 ? 'ok' : (det.confidence >= 0.30 ? 'warn' : null);
        if (!tier) return;
        const arr = read();
        // Debounce: skip if last entry has same tier within 3 seconds.
        const now = Date.now();
        if (arr[0] && arr[0].tier === tier && (now - arr[0].ts) < 3000) return;
        arr.unshift({ tier, confidence: det.confidence, ts: now });
        write(arr);
        this.render();
      },
      render() {
        const strip = document.querySelector('.history-strip');
        if (!strip) return;
        const arr = read();
        if (arr.length === 0) return; // keep the demo thumbs on first visit
        strip.innerHTML = arr.map(e => `
          <div class="hist-thumb ${e.tier}">
            ⛑️<span class="hist-status">${e.tier === 'ok' ? '✓' : '!'}</span>
          </div>
        `).join('');
      },
    };
  })();
```

- [ ] **Step 2: Call `ScanHistory` from real-mode setup**

In `runRealMode`, at the very top of the function (right after variable declarations), add:

```js
    ScanHistory.render();
```

And inside the FrameLoop callback, right after computing `top`:

```js
          if (top) ScanHistory.push(top);
```

- [ ] **Step 3: Manual verification**

1. Clear `localStorage`: `localStorage.removeItem('sieunhi.aicam.history')`, reload page. History strip shows the original 5 demo thumbs (untouched — arr.length === 0 path).
2. Turn camera on. Wave a helmet in and out of frame several times over ~30 seconds.
3. Refresh page. History strip now shows real thumbs based on your latest 5 tier changes (ok/warn mix), most recent on the left.
4. Confirm no huge growth of localStorage — `JSON.parse(localStorage.getItem('sieunhi.aicam.history')).length` should be ≤ 5.

- [ ] **Step 4: Commit**

```bash
git add assets/js/ai-camera.js
git commit -m "feat(ai-camera): persist last 5 detection tiers in localStorage

Debounced (skip if tier unchanged within 3s) to avoid spam.
Storage: [{ tier, confidence, ts }, ...]. No images, no PII."
```

---

## Task 8: Upload tab — single-shot detect on user file

**Files:**
- Modify: `assets/js/ai-camera.js` — extend `runRealMode` with upload wiring; support drag-and-drop onto `.cam-view`.
- Modify: `ai-camera.php` — the 3rd `.ctrl-btn` (currently the upload icon) needs `id="camUploadBtn"`.

**Interfaces:**
- Consumes: `HelmetDetector.detect(imgEl)`, `ResultRenderer.drawBoxes/updateBadge/updateHelmetCard`, `ScanHistory.push`.
- Produces: no exports; user-visible behavior only.

- [ ] **Step 1: Add `id` to the upload button in `ai-camera.php`**

Find the line (currently line 58):
```html
      <button class="ctrl-btn" title="Tải ảnh lên">🖼️</button>
```

Change to:
```html
      <button class="ctrl-btn" id="camUploadBtn" title="Tải ảnh lên">🖼️</button>
```

Bump the JS version: `assets/js/ai-camera.js?v=6` → `?v=7` at the bottom of the file.

- [ ] **Step 2: Add upload wiring at the bottom of `runRealMode`** (before `window.__aiCam = ...`)

```js
    // ---------- Upload tab: single-shot detect ----------

    const fileInput = document.getElementById('camFileInput');
    const uploadBtn = document.getElementById('camUploadBtn');
    const uploadTab = document.querySelectorAll('.cam-tab')[1];
    const camView   = document.querySelector('.cam-view');

    async function detectFromFile(file) {
      if (!file || !file.type.startsWith('image/')) return;
      try {
        await ensureDetector();
      } catch (err) {
        handleCameraError(err); return;
      }
      // If the live camera is on, stop it first — one scene at a time.
      if (camHandle) stopCamera();

      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = async () => {
        // Reveal real scene, draw the image to canvas, then overlay bboxes.
        scene.classList.add('real');
        // Use canvas to render the image itself so users see it in the frame.
        canvasEl.width  = img.naturalWidth;
        canvasEl.height = img.naturalHeight;
        const ctx = canvasEl.getContext('2d');
        ctx.drawImage(img, 0, 0);
        // Hide the video element so the canvas is the sole visual.
        videoEl.style.display = 'none';
        try {
          const dets = await detector.detect(img);
          ResultRenderer.drawBoxes(canvasEl, img, dets);
          const top = dets.length
            ? dets.reduce((a, b) => (a.confidence >= b.confidence ? a : b))
            : null;
          if (top && top.confidence >= 0.60) {
            ResultRenderer.updateBadge(`✓ Độ chính xác ${Math.round(top.confidence * 100)}%`, 'ok');
          } else if (top) {
            ResultRenderer.updateBadge(`⚠ Chưa rõ ${Math.round(top.confidence * 100)}%`, 'warn');
          } else {
            ResultRenderer.updateBadge('⏸ Chưa thấy mũ trong ảnh', 'idle');
          }
          ResultRenderer.updateHelmetCard(top);
          if (top) ScanHistory.push(top);
        } finally {
          URL.revokeObjectURL(url);
        }
      };
      img.src = url;
    }

    // File picker: both the upload button in the row AND the tab click open it.
    if (fileInput) {
      if (uploadBtn) uploadBtn.addEventListener('click', () => fileInput.click());
      if (uploadTab) uploadTab.addEventListener('click', () => {
        // Only auto-open picker on second click (first click just switches tabs)
        if (uploadTab.classList.contains('active')) fileInput.click();
      });
      fileInput.addEventListener('change', e => detectFromFile(e.target.files[0]));
    }

    // Drag and drop onto the camera view.
    if (camView) {
      ['dragover', 'dragenter'].forEach(ev => camView.addEventListener(ev, e => {
        e.preventDefault(); camView.classList.add('drop-hover');
      }));
      ['dragleave', 'drop'].forEach(ev => camView.addEventListener(ev, e => {
        e.preventDefault(); camView.classList.remove('drop-hover');
      }));
      camView.addEventListener('drop', e => {
        const f = e.dataTransfer?.files?.[0];
        if (f) detectFromFile(f);
      });
    }

    // Restore videoEl when switching back to live tab (stopCamera resets scene).
    // Task 6's stopCamera already removes .real from scene; add videoEl reveal:
    // (patch stopCamera in place if you split by hand — simplest is to just
    //  reset videoEl.style.display inside startCamera below)
```

- [ ] **Step 3: Ensure `startCamera` restores the video element display**

In `runRealMode`'s `startCamera`, add one line at the very top of the try block:

```js
      try {
        videoEl.style.display = '';   // undo hide from upload path
        await ensureDetector();
```

- [ ] **Step 4: Small CSS touch — drop-hover feedback**

Append to `assets/css/shared-pages.css`:

```css
.cam-view.drop-hover { outline: 2px dashed var(--cyan); outline-offset: -6px; }
```

Bump the shared-pages.css version reference in `ai-camera.php` to `?v=7`.

- [ ] **Step 5: Manual verification**

1. Click the 🖼️ Tab, then click again (or click the upload 🖼️ button) — file picker opens.
2. Choose a photo containing a person with a helmet — the photo appears in the camera area with a green bbox + label; side panel updates.
3. Choose a photo with no helmet — badge shows `⏸ Chưa thấy mũ trong ảnh`, card copy reflects it.
4. Drag a photo file from the desktop and drop it onto the camera area — same detection flow triggers, dashed outline shown during dragover.
5. Click 📷 Camera trực tiếp tab, then click the shutter — live camera returns cleanly; the previous image is gone.

- [ ] **Step 6: Commit**

```bash
git add ai-camera.php assets/js/ai-camera.js assets/css/shared-pages.css
git commit -m "feat(ai-camera): upload-tab single-shot detect with drag-and-drop

File input + drop zone route through the same HelmetDetector +
ResultRenderer + ScanHistory as the live tab. Live camera stops
when a file is dropped — one scene at a time."
```

---

## Task 9: Fallback safety net — SDK missing, model load failure, low-light hint

**Files:**
- Modify: `assets/js/ai-camera.js` — wrap `main()` dispatch in a try/catch that drops to `runMockMode()` + banner if `runRealMode` throws during setup; add a "5s no detections" hint inside FrameLoop callback.

**Interfaces:**
- No new exports; hardens existing paths.

- [ ] **Step 1: Change the DOMContentLoaded handler to fall back on error**

In `ai-camera.js`, replace the current dispatch block:

```js
  document.addEventListener('DOMContentLoaded', () => {
    if (cfg.enabled) {
      runRealMode(cfg);
    } else {
      runMockMode();
    }
    wireTabs();
  });
```

With:

```js
  document.addEventListener('DOMContentLoaded', () => {
    if (cfg.enabled) {
      try {
        runRealMode(cfg);
      } catch (e) {
        console.warn('AI camera real mode failed to init, falling back to mock', e);
        runMockMode();
        showFallbackBanner();
      }
    } else {
      runMockMode();
    }
    wireTabs();
  });

  function showFallbackBanner() {
    const el = document.getElementById('camStatusBanner');
    if (!el) return;
    el.hidden = false;
    el.textContent = 'Đang chạy chế độ demo — chưa kết nối được model AI.';
  }
```

- [ ] **Step 2: Also fall back if `ensureDetector` fails at click time**

The runtime failure path (SDK loaded but startWorker fails at first shutter click) — catch it and fall back for the rest of the session:

In `runRealMode`'s `startCamera`, replace the outer `catch (err)`:

```js
      } catch (err) {
        stopCamera();
        if (err.message && err.message.includes('inferencejs')) {
          console.warn('detector unavailable, falling back to mock', err);
          showFallbackBanner();
          runMockMode(); // rebinds window.rescan
          return;
        }
        handleCameraError(err);
      }
```

- [ ] **Step 3: Add the "5s no detections" gentle hint**

Inside `runRealMode`, add a small `noDetSince` tracker. Replace the FrameLoop callback with:

```js
        let noDetSince = null;
        loop = FrameLoop.start(async () => {
          const dets = await detector.detect(videoEl);
          ResultRenderer.drawBoxes(canvasEl, videoEl, dets);
          const top = dets.length
            ? dets.reduce((a, b) => (a.confidence >= b.confidence ? a : b))
            : null;
          if (top && top.confidence >= 0.60) {
            ResultRenderer.updateBadge(`✓ Độ chính xác ${Math.round(top.confidence * 100)}%`, 'ok');
            noDetSince = null;
            ResultRenderer.setStatus(null);
          } else if (top) {
            ResultRenderer.updateBadge(`⚠ Chưa rõ ${Math.round(top.confidence * 100)}%`, 'warn');
            noDetSince = null;
            ResultRenderer.setStatus(null);
          } else {
            ResultRenderer.updateBadge('⏸ Chưa thấy mũ', 'idle');
            if (!noDetSince) noDetSince = Date.now();
            if (Date.now() - noDetSince >= 5000) {
              ResultRenderer.setStatus('Hãy đứng gần cửa sổ hoặc bật đèn — camera đang thấy tối.');
            }
          }
          ResultRenderer.updateHelmetCard(top);
          if (top) ScanHistory.push(top);
        });
```

- [ ] **Step 4: Manual verification of all fallback paths**

1. **SDK missing:** Temporarily block `cdn.jsdelivr.net` in DevTools Network → Block request URL. Reload with `AI_CAMERA_ENABLED=true` + valid key/model. Expected: mock scene, mock shutter behavior (random 96%), status banner reads "Đang chạy chế độ demo — chưa kết nối được model AI." No red console errors.
2. **Bad model slug:** Set `ROBOFLOW_MODEL=nonexistent-model/999` in `.env`. Reload, click shutter. Expected: fallback banner appears mid-flow, mock rescan starts working, no crash.
3. **Low light:** Cover camera lens with your hand. Wait 5 seconds. Expected: status banner reads "Hãy đứng gần cửa sổ hoặc bật đèn — camera đang thấy tối." Uncover — banner disappears.
4. **Deny permission:** Deny prompt → banner: "Bạn chưa cho phép camera..." — from Task 6, still works.

- [ ] **Step 5: Restore `.env` to real values, one last full smoke test**

With correct `AI_CAMERA_ENABLED=true`, `ROBOFLOW_PUBLISHABLE_KEY=<real>`, `ROBOFLOW_MODEL=<slug/version>`, walk through every path once more:

- Live camera detect + tier transitions.
- Upload a JPG → detect.
- Drag-drop a PNG → detect.
- Switch tabs back and forth.
- Reload, observe history strip persists.
- Set `AI_CAMERA_ENABLED=false` in `.env`, reload → identical to today's mock.

- [ ] **Step 6: Commit**

```bash
git add assets/js/ai-camera.js
git commit -m "feat(ai-camera): safety net — SDK/model failures fall back to mock, low-light hint after 5s

Setup-time failure of runRealMode drops to runMockMode + banner.
Runtime failure of ensureDetector (bad model, expired key) does the
same at click time. No red console error is visible to end users."
```

---

## Task 10: Update README with the new AI Camera section

**Files:**
- Modify: `README.md` — add a new section explaining the AI Camera feature flag, how to get a Roboflow model, expected behavior in each mode.

**Interfaces:**
- Documentation only.

- [ ] **Step 1: Append a new section after "Việc tiếp theo bạn có thể làm"**

Add to `README.md`:

```markdown

## AI Camera — Detect mũ bảo hiểm thật

Từ bản này chức năng AI Camera đã hỗ trợ chạy **model AI thật** trong browser (Roboflow inferencejs, không upload ảnh ra server). Mặc định **tắt** — trang vẫn chạy mock cũ cho tới khi bạn bật.

### Bật chế độ thật

1. Đăng ký free account tại https://roboflow.com, tìm model "helmet detection" trên **Roboflow Universe** (hoặc train riêng, cần bản trả phí).
2. Copy `Publishable Key` từ trang Settings và model slug (dạng `<workspace-project>/<version>`, vd `helmet-detection-abcxyz/3`).
3. Điền vào `.env`:
   ```env
   AI_CAMERA_ENABLED=true
   ROBOFLOW_PUBLISHABLE_KEY=rf_xxxxxxxxxxxxxxxx
   ROBOFLOW_MODEL=helmet-detection-abcxyz/3
   ```
4. Mở `http://localhost/Sieunhi_atgt/ai-camera.php`. Nhãn dưới nút camera phải hiện `● AI thật` (xanh). Bấm nút 📸 → cho phép camera → model detect thật.

### Chế độ mock (mặc định)

Nếu bất kỳ điều nào sau đây đúng, trang giữ nguyên hành vi mock cũ (random 94–98%) và **không** gọi Roboflow / không xin quyền camera:
- `AI_CAMERA_ENABLED=false`
- `ROBOFLOW_PUBLISHABLE_KEY` trống
- `ROBOFLOW_MODEL` trống
- SDK jsDelivr load lỗi (offline hoặc bị firewall chặn)
- Model không tồn tại / key hết hạn (fallback tại thời điểm click camera)

Trường hợp fallback vì lý do kỹ thuật, banner nhỏ ở khung camera sẽ giải thích.

### Ghi nhớ

- **Ảnh KHÔNG rời khỏi máy user.** Không có endpoint upload — server không thể lưu kể cả muốn.
- Publishable key của Roboflow **an toàn để lộ** ở client (khác với secret key). Việc để trong `.env` là để dễ đổi + không commit lên git.
- Card "Biển báo khu vực trường học" và "Vị trí đứng" gắn nhãn `Demo` — chỉ mũ bảo hiểm là detect thật.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs(ai-camera): document AI_CAMERA_ENABLED flag and Roboflow setup"
```

---

## Self-Review

**Spec coverage (§ of spec → task):**
- §3.1 env vars → Task 1 ✓
- §3.2 config.php bridge → Task 1 ✓
- §3.3 server-side guard → Task 1 (`$aiEnabled`) + Task 2 (conditional SDK script) ✓
- §3.4 five JS modules → Tasks 4 (HelmetDetector), 5 (CameraStream), 6 (FrameLoop + ResultRenderer), 7 (ScanHistory) ✓
- §3.5 flow → Task 3 (dispatch) + Task 6 (orchestration) ✓
- §4.1 3 confidence tiers → Task 6 (`tierFor`, updateBadge, updateHelmetCard) ✓
- §4.2 live-camera flow → Task 6 ✓
- §4.3 upload flow → Task 8 ✓
- §4.4 edge cases (HTTP block, model fail, low-light, no getUserMedia) → Tasks 5, 6, 9 ✓
- §4.5 Demo badges → Task 2 step 4 ✓
- §4.6 mode pill → Task 2 step 3 ✓
- §5 YAGNI (no upload endpoint, no slider, no training) → not built (correct) ✓
- §6 fallback resilience → Task 9 ✓
- §7 manual testing → verification steps in every task ✓
- §8 files touched → covered across tasks 1-10 ✓

**Placeholder scan:** No "TBD"/"TODO"/"add appropriate X" in the plan. Every step has concrete code or a concrete command with expected output.

**Type consistency:** `Detection = { className, confidence, bbox: {x, y, width, height} }` used identically in HelmetDetector (Task 4), FrameLoop callbacks (Task 6, 9), ResultRenderer (Task 6), ScanHistory (Task 7), upload path (Task 8). `cfg = { enabled, key, model }` shape emitted in Task 1 Step 6 and consumed in Task 3 dispatch and Task 6 `runRealMode`.

**No CI/framework tests:** deliberate — see Global Constraints. Each task's gate is a browser smoke test with explicit expected outcomes.

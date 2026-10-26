import { execFileSync } from "node:child_process";
import { mkdirSync, renameSync, writeFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { chromium } from "playwright";

const root = path.dirname(fileURLToPath(import.meta.url));
const captureDir = path.join(root, "public", "captures");
const profile =
  process.env.FORMLYX_CHROME_PROFILE || "/tmp/formlyx-video-chrome";
const site = "https://peerkit-chat-test.instawp.site";
const formEditor =
  "https://www.formlyx.com/app/forms/cmvl8469t001plg1y7bc67j7u";
const protectedPosts = new Set(["2", "3", "18", "20"]);

const targets = {
  intro: 120,
  plugins: 360,
  connect: 420,
  form: 300,
  editor: 540,
  live: 450,
};

mkdirSync(captureDir, { recursive: true });

const pause = (page, ms) => page.waitForTimeout(ms);

const moveClick = async (page, locator) => {
  const box = await locator.boundingBox();
  if (box) {
    await page.mouse.move(box.x + box.width / 2, box.y + Math.min(box.height / 2, 24), {
      steps: 18,
    });
    await pause(page, 250);
  }
  await locator.click();
};

const maskPrivate = async (page) => {
  await page.evaluate(() => {
    const blur = (element) => {
      if (!element) {
        return;
      }
      element.style.filter = "blur(10px)";
      element.style.userSelect = "none";
    };
    document
      .querySelectorAll("#wp-admin-bar-my-account, #wp-admin-bar-user-info")
      .forEach(blur);
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const textNodes = [];
    while (walker.nextNode()) {
      textNodes.push(walker.currentNode);
    }
    textNodes.forEach((node) => {
      if (node.nodeValue && node.nodeValue.includes("@")) {
        blur(node.parentElement);
      }
    });
    document.querySelectorAll("input, textarea").forEach((element) => {
      if ("value" in element && String(element.value).includes("@")) {
        blur(element);
      }
    });
    document.querySelectorAll('[role="option"]').forEach((option) => {
      const text = option.textContent || "";
      if (
        text.trim() &&
        !text.includes("WordPress tutorial") &&
        !text.includes("Formlyx Form")
      ) {
        option.style.filter = "blur(14px)";
      }
    });
  });
};

const assertSafePage = (page) => {
  const url = new URL(page.url());
  const post = url.searchParams.get("post");
  if (post && protectedPosts.has(post)) {
    throw new Error("Refusing to edit an existing WordPress page.");
  }
  const pathName = url.pathname;
  if (
    pathName.includes("formlyx-acceptance") ||
    pathName.includes("sample-page") ||
    pathName.includes("peerkit-private-chat-test")
  ) {
    throw new Error("Refusing to open an existing public page in the editor.");
  }
};

const dismissModals = async (page) => {
  const labels = ["Skip", "Close dialog", "Close", "Not now", "Got it", "Start writing"];
  for (let attempt = 0; attempt < 6; attempt += 1) {
    let clicked = false;
    for (const label of labels) {
      const buttons = page.getByRole("button", { name: label, exact: true });
      const count = await buttons.count();
      for (let index = 0; index < count; index += 1) {
        const button = buttons.nth(index);
        if (await button.isVisible().catch(() => false)) {
          await button.click();
          clicked = true;
          await pause(page, 400);
          break;
        }
      }
      if (clicked) {
        break;
      }
    }
    if (!clicked) {
      return;
    }
  }
};

const shoot = async (context, name, run) => {
  if (!shouldCapture(name)) {
    return 900;
  }
  const page = await context.newPage();
  const started = Date.now();
  let readyMs = 0;
  const markReady = () => {
    if (!readyMs) {
      readyMs = Date.now() - started;
    }
  };
  try {
    await run(page, markReady);
    await maskPrivate(page);
    await pause(page, 400);
    const video = page.video();
    await page.close();
    const saved = await video.path();
    renameSync(saved, path.join(captureDir, `${name}.webm`));
    return readyMs;
  } catch (error) {
    const labels = await page
      .evaluate(() =>
        [...document.querySelectorAll("button, [role='option'], [role='heading'], input, textarea")]
          .map((element) => (element.getAttribute("aria-label") || element.innerText || element.getAttribute("placeholder") || "").replace(/\s+/g, " ").trim())
          .filter((text) => text && !text.includes("@") && !/howdy|author/i.test(text))
          .slice(0, 40)
      )
      .catch(() => []);
    writeFileSync(path.join(captureDir, `${name}-labels.txt`), `${page.url().split("?")[0]}\n${labels.join("\n")}\n`);
    await page.screenshot({ path: path.join(captureDir, `${name}-failure.png`) }).catch(() => {});
    const video = page.video();
    await page.close().catch(() => {});
    if (video) {
      const saved = await video.path().catch(() => "");
      if (saved) {
        renameSync(saved, path.join(captureDir, `${name}-failed.webm`));
      }
    }
    throw error;
  }
};

const framesOf = (file) => {
  const seconds = execFileSync(
    "ffprobe",
    [
      "-v",
      "error",
      "-show_entries",
      "format=duration",
      "-of",
      "csv=p=0",
      file,
    ],
    { encoding: "utf8" }
  );
  return Math.max(1, Math.floor(Number(seconds.trim()) * 30));
};

const order = ["intro", "plugins", "connect", "form", "editor", "live"];
const captureFrom = process.env.CAPTURE_FROM || "intro";
const only = (process.env.CAPTURE_ONLY || "")
  .split(",")
  .map((name) => name.trim())
  .filter(Boolean);
const shouldCapture = (name) =>
  only.length > 0 ? only.includes(name) : order.indexOf(name) >= order.indexOf(captureFrom);

const run = async () => {
  const context = await chromium.launchPersistentContext(profile, {
    channel: "chrome",
    headless: true,
    viewport: { width: 1920, height: 1080 },
    deviceScaleFactor: 1,
    recordVideo: { dir: captureDir, size: { width: 1920, height: 1080 } },
    args: ["--disable-session-crashed-bubble", "--hide-scrollbars"],
  });
  for (const restored of context.pages()) {
    await restored.close().catch(() => {});
  }
  await context.addInitScript(() => {
    const ring = document.createElement("div");
    ring.setAttribute("data-formlyx-cursor", "true");
    ring.style.cssText = [
      "position:fixed",
      "width:34px",
      "height:34px",
      "margin:-17px 0 0 -17px",
      "border:3px solid #433BC5",
      "border-radius:999px",
      "box-shadow:0 0 0 7px rgba(67,59,197,0.22)",
      "pointer-events:none",
      "z-index:2147483647",
      "left:0",
      "top:0",
    ].join(";");
    document.addEventListener(
      "mousemove",
      (event) => {
        if (!ring.isConnected) {
          document.documentElement.appendChild(ring);
        }
        ring.style.left = `${event.clientX}px`;
        ring.style.top = `${event.clientY}px`;
      },
      true
    );
    const hideSupport = () => {
      document.querySelectorAll('[role="dialog"]').forEach((dialog) => {
        const label = dialog.getAttribute("aria-label") || "";
        if (label.toLowerCase().includes("solvewise")) {
          dialog.remove();
        }
      });
    };
    new MutationObserver(hideSupport).observe(document.documentElement, {
      childList: true,
      subtree: true,
    });
  });

  const ready = {};
  try {
    ready.intro = await (async () => {
      if (!shouldCapture("intro")) {
        return 900;
      }
      const browser = await chromium.launch({ channel: "chrome", headless: true });
      const clean = await browser.newContext({
        viewport: { width: 1920, height: 1080 },
        deviceScaleFactor: 1,
        recordVideo: { dir: captureDir, size: { width: 1920, height: 1080 } },
      });
      try {
        return await shoot(clean, "intro", async (page, markReady) => {
          await page.goto("https://www.formlyx.com/", { waitUntil: "domcontentloaded" });
          if (page.url().includes("/app")) {
            throw new Error("Refusing to record the signed-in Formlyx dashboard.");
          }
          await page.getByRole("heading", { level: 1 }).first().waitFor();
          markReady();
          await pause(page, 4500);
        });
      } finally {
        await clean.close();
        await browser.close();
      }
    })();

    ready.plugins = await shoot(context, "plugins", async (page, markReady) => {
      await page.goto(`${site}/wp-admin/plugins.php`, { waitUntil: "domcontentloaded" });
      if (page.url().includes("wp-login.php")) {
        throw new Error("WordPress admin is signed out. A password was not entered.");
      }
      await page.getByRole("heading", { name: "Plugins", exact: true }).waitFor();
      const search = page.getByRole("searchbox", { name: "Search installed plugins" });
      await moveClick(page, search);
      await search.fill("Formlyx");
      await page.getByText("Formlyx Forms", { exact: true }).first().waitFor();
      markReady();
      await maskPrivate(page);
      const row = page.getByText("Connect Formlyx to WordPress and embed hosted forms.");
      await moveClick(page, row);
      await pause(page, 11000);
    });

    ready.connect = await shoot(context, "connect", async (page, markReady) => {
      await page.goto(`${site}/wp-admin/options-general.php?page=formlyx`, {
        waitUntil: "domcontentloaded",
      });
      await page.getByRole("heading", { name: "Connected to Formlyx" }).waitFor();
      await maskPrivate(page);
      markReady();
      await pause(page, 3500);
      const reconnect = page.getByRole("button", { name: "Reconnect" });
      await moveClick(page, reconnect);
      const approval = page.getByRole("heading", { name: "Connect to Formlyx" });
      const appeared = await approval
        .waitFor({ timeout: 20000 })
        .then(() => true)
        .catch(() => false);
      if (appeared) {
        if (await page.locator('input[type="password"]').count()) {
          await maskPrivate(page);
          await pause(page, 5500);
        }
      }
      await page.goto(`${site}/wp-admin/options-general.php?page=formlyx`, {
        waitUntil: "domcontentloaded",
      });
      await page.getByRole("heading", { name: "Connected to Formlyx" }).waitFor();
      await maskPrivate(page);
      await pause(page, 3500);
    });

    ready.form = await shoot(context, "form", async (page, markReady) => {
      await page.goto(formEditor, { waitUntil: "domcontentloaded" });
      if (page.url().includes("/login")) {
        throw new Error("Formlyx is signed out. A password was not entered.");
      }
      await page.getByRole("button", { name: /Name Short text/ }).waitFor();
      markReady();
      await maskPrivate(page);
      const nameField = page.getByRole("button", { name: /Name Short text/ });
      await moveClick(page, nameField);
      await pause(page, 2500);
      const messageField = page.getByRole("button", { name: /Message Long text/ });
      await moveClick(page, messageField);
      await pause(page, 4500);
    });

    ready.editor = await shoot(context, "editor", async (page, markReady) => {
      await page.goto(`${site}/wp-admin/post-new.php?post_type=page`, {
        waitUntil: "domcontentloaded",
      });
      if (page.url().includes("wp-login.php")) {
        throw new Error("WordPress admin is signed out. A password was not entered.");
      }
      assertSafePage(page);
      const title = page.getByRole("textbox", { name: "Add title" });
      for (let attempt = 0; attempt < 8 && !(await title.isVisible().catch(() => false)); attempt += 1) {
        await dismissModals(page);
        await pause(page, 350);
      }
      await title.waitFor({ timeout: 20000 });
      markReady();
      await moveClick(page, title);
      await title.fill("Contact");
      await pause(page, 600);
      const paragraph = page.locator(
        '.editor-styles-wrapper p[data-empty="true"], .block-editor-default-block-appender__content, .editor-styles-wrapper [data-type="core/paragraph"]'
      ).first();
      if (await paragraph.isVisible().catch(() => false)) {
        await moveClick(page, paragraph);
      }
      await page.keyboard.type("/Formlyx", { delay: 40 });
      const slashChoice = page.getByRole("option", { name: /Formlyx Form/ }).first();
      if (!(await slashChoice.isVisible().catch(() => false))) {
        await page.keyboard.press("Escape");
        const inserter = page.getByRole("button", { name: /block inserter/i }).first();
        await moveClick(page, inserter);
        const search = page.locator(
          'input[placeholder="Search"], input.block-editor-inserter__search-input, [role="searchbox"]'
        ).first();
        await search.fill("Formlyx");
      }
      const block = page.getByRole("option", { name: /Formlyx Form/ }).first();
      await block.waitFor({ timeout: 15000 });
      await moveClick(page, block);
      const published = page.getByRole("combobox", { name: "Published form" });
      await published.waitFor({ timeout: 20000 });
      await maskPrivate(page);
      const formId = page.getByRole("textbox", { name: "Form ID" });
      await formId.fill("cmvl8469t001plg1y7bc67j7u");
      await formId.press("Tab");
      const theme = page.getByLabel("Theme");
      await theme.selectOption({ label: "Light" });
      await pause(page, 800);
      const allow = page.getByRole("button", { name: "Allow embedding on this site" });
      if (await allow.isVisible().catch(() => false)) {
        await moveClick(page, allow);
        await pause(page, 1500);
      }
      assertSafePage(page);
      const headerPublish = page.locator("button.editor-post-publish-button__button").first();
      await moveClick(page, headerPublish);
      const confirm = page.locator(".editor-post-publish-panel button.editor-post-publish-button__button");
      if (await confirm.isVisible().catch(() => false)) {
        assertSafePage(page);
        await moveClick(page, confirm);
      }
      const view = page.getByRole("link", { name: "View Page" });
      await view.waitFor({ timeout: 25000 });
      const href = await view.getAttribute("href");
      if (!href || href.includes("formlyx-acceptance") || href.includes("sample-page") || href.includes("peerkit-private")) {
        throw new Error("Refusing to use an existing public page.");
      }
      writeFileSync(path.join(captureDir, "page-url.txt"), href, "utf8");
      await pause(page, 2200);
    });

    ready.live = await shoot(context, "live", async (page, markReady) => {
      const href = await import("node:fs").then((fs) =>
        fs.readFileSync(path.join(captureDir, "page-url.txt"), "utf8").trim()
      );
      await page.goto(href, { waitUntil: "domcontentloaded" });
      const name = page.locator('input[name="name"]');
      await name.waitFor({ timeout: 20000 });
      markReady();
      await moveClick(page, name);
      await name.fill("Alex Morgan");
      const email = page.locator('input[name="email"]');
      await moveClick(page, email);
      await email.fill("alex.morgan@example.com");
      const message = page.locator('textarea[name="message"]');
      await moveClick(page, message);
      await message.fill("This is a fictional note for the Formlyx WordPress tutorial.");
      await pause(page, 600);
      const submit = page.getByRole("button", { name: "Send message" });
      await moveClick(page, submit);
      await page.getByText("Thanks. We received your message.").waitFor({ timeout: 20000 });
      await pause(page, 2500);
      await page.screenshot({ path: path.join(captureDir, "success.png") });
      await pause(page, 1500);
    });
  } finally {
    await context.close();
  }

  const clips = {};
  for (const [name, target] of Object.entries(targets)) {
    const file = path.join(captureDir, `${name}.webm`);
    const frames = framesOf(file);
    const startFrom = Math.min(Math.round((ready[name] || 0) / 1000 * 30), Math.max(0, frames - 45));
    const duration = Math.min(target, frames - startFrom);
    if (duration < 45) {
      throw new Error(`${name} is too short to use (${frames} frames).`);
    }
    clips[name] = {
      file: `captures/${name}.webm`,
      startFrom,
      duration,
    };
  }
  clips.outroStill = "captures/success.png";
  writeFileSync(path.join(root, "src", "clips.json"), `${JSON.stringify(clips, null, 2)}\n`);
  console.log(JSON.stringify(clips, null, 2));
};

run().catch((error) => {
  console.error(error instanceof Error ? error.message : error);
  process.exit(1);
});

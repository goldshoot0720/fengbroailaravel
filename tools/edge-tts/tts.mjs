#!/usr/bin/env node
/**
 * Edge TTS CLI for fengbroailaravel.
 * Usage:
 *   node tts.mjs --out file.mp3 --voice zh-TW-HsiaoChenNeural --rate +0% --text "你好"
 *   node tts.mjs --batch batch.json
 *
 * batch.json:
 * {
 *   "voice": "zh-TW-HsiaoChenNeural",
 *   "rate": "+0%",
 *   "volume": "+0%",
 *   "items": [{ "text": "...", "out": "a.mp3" }, ...]
 * }
 */
import { writeFile, mkdir } from "fs/promises";
import { dirname, resolve } from "path";
import { MsEdgeTTS, OUTPUT_FORMAT } from "msedge-tts";

function parseArgs(argv) {
  const out = { _: [] };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === "--out" || a === "--voice" || a === "--rate" || a === "--volume" || a === "--text" || a === "--batch") {
      out[a.slice(2)] = argv[++i];
    } else if (a === "--list-voices") {
      out.listVoices = true;
    } else {
      out._.push(a);
    }
  }
  return out;
}

function collectStream(tts, text, rate, volume) {
  return new Promise((resolveP, reject) => {
    const chunks = [];
    try {
      const { audioStream } = tts.toStream(text, { rate, volume });
      audioStream.on("data", (c) => chunks.push(Buffer.from(c)));
      audioStream.on("end", () => resolveP(Buffer.concat(chunks)));
      audioStream.on("error", reject);
    } catch (e) {
      reject(e);
    }
  });
}

async function withTimeout(promise, ms, label) {
  let timer;
  try {
    return await Promise.race([
      promise,
      new Promise((_, rej) => {
        timer = setTimeout(() => rej(new Error(`${label} timeout ${ms}ms`)), ms);
      }),
    ]);
  } finally {
    clearTimeout(timer);
  }
}

async function synthOne(text, voice, rate, volume, outPath) {
  const tts = new MsEdgeTTS();
  try {
    await withTimeout(
      tts.setMetadata(voice, OUTPUT_FORMAT.AUDIO_24KHZ_96KBITRATE_MONO_MP3),
      15000,
      "connect"
    );
    const buf = await withTimeout(collectStream(tts, text, rate, volume), 30000, "synth");
    await mkdir(dirname(outPath), { recursive: true });
    await writeFile(outPath, buf);
    return { ok: true, out: outPath, bytes: buf.length };
  } finally {
    try {
      tts.close();
    } catch {
      /* ignore */
    }
  }
}

async function main() {
  const args = parseArgs(process.argv);

  if (args.listVoices) {
    const tts = new MsEdgeTTS();
    const voices = await tts.getVoices();
    process.stdout.write(JSON.stringify(voices, null, 2));
    return;
  }

  if (args.batch) {
    const { readFile } = await import("fs/promises");
    const cfg = JSON.parse(await readFile(resolve(args.batch), "utf8"));
    const voice = cfg.voice || "zh-TW-HsiaoChenNeural";
    const rate = cfg.rate ?? "+0%";
    const volume = cfg.volume ?? "+0%";
    const items = Array.isArray(cfg.items) ? cfg.items : [];
    if (!items.length) {
      console.error(JSON.stringify({ ok: false, error: "empty items" }));
      process.exit(1);
    }
    // One WS per voice, sequential for reliability
    const tts = new MsEdgeTTS();
    const results = [];
    try {
      await withTimeout(
        tts.setMetadata(voice, OUTPUT_FORMAT.AUDIO_24KHZ_96KBITRATE_MONO_MP3),
        15000,
        "connect"
      );
      for (const item of items) {
        const text = String(item.text || "").trim();
        const out = resolve(String(item.out || ""));
        if (!text || !out) {
          results.push({ ok: false, error: "missing text/out" });
          continue;
        }
        try {
          const buf = await withTimeout(collectStream(tts, text, rate, volume), 30000, "synth");
          await mkdir(dirname(out), { recursive: true });
          await writeFile(out, buf);
          results.push({ ok: true, out, bytes: buf.length });
        } catch (e) {
          results.push({ ok: false, out, error: e?.message || String(e) });
        }
      }
    } finally {
      try {
        tts.close();
      } catch {
        /* ignore */
      }
    }
    const failed = results.filter((r) => !r.ok);
    process.stdout.write(JSON.stringify({ ok: failed.length === 0, results }));
    process.exit(failed.length ? 2 : 0);
  }

  const text = String(args.text || "").trim();
  const out = args.out ? resolve(args.out) : "";
  const voice = args.voice || "zh-TW-HsiaoChenNeural";
  const rate = args.rate || "+0%";
  const volume = args.volume || "+0%";
  if (!text || !out) {
    console.error("Usage: node tts.mjs --out file.mp3 --text \"...\" [--voice name] [--rate +0%] [--volume +0%]");
    process.exit(1);
  }
  const r = await synthOne(text, voice, rate, volume, out);
  process.stdout.write(JSON.stringify(r));
}

main().catch((e) => {
  console.error(JSON.stringify({ ok: false, error: e?.message || String(e) }));
  process.exit(1);
});

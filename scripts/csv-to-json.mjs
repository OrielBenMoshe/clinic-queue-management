#!/usr/bin/env node
/**
 * המרת קובץ CSV של תחומי רפואה וטיפולים ל-JSON.
 * פלט: רשימת אובייקטים { name, slug, treatments } (ללא כפילויות מבניות).
 *
 * שימוש:
 *   node scripts/csv-to-json.mjs
 *   node scripts/csv-to-json.mjs [path/to/file.csv]
 *   node scripts/csv-to-json.mjs [path/to/file.csv] -o output.json
 *   node scripts/csv-to-json.mjs --ascii-slugs
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const DEFAULT_CSV =
  "רשימת תחומי רפואה - פורטל 15.12.25 - רשימת תחומי רפואה וטיפולים 9.2.26.csv";

function slugify(name) {
  if (!name || !name.trim()) return "";
  let s = name
    .trim()
    .replace(/[\s/]+/g, "-")
    .replace(/[^\p{L}\p{N}_\-]/gu, "")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "");
  return s || "unknown";
}

/**
 * מפרק שורת CSV (תומך בשדות במרכאות עם פסיקים בפנים).
 */
function parseCsvLine(line) {
  const out = [];
  let i = 0;
  while (i < line.length) {
    if (line[i] === '"') {
      let end = i + 1;
      const parts = [];
      while (end < line.length) {
        if (line[end] === '"') {
          end++;
          if (line[end] === '"') {
            parts.push('"');
            end++;
          } else break;
        } else {
          parts.push(line[end]);
          end++;
        }
      }
      out.push(parts.join(""));
      i = end;
      if (line[i] === ",") i++;
    } else {
      let end = line.indexOf(",", i);
      if (end === -1) end = line.length;
      out.push(line.slice(i, end).trim());
      i = end + 1;
    }
  }
  return out;
}

function getCsvPath() {
  const args = process.argv.slice(2);
  for (let i = 0; i < args.length; i++) {
    if (args[i] === "-o") {
      i++;
      continue;
    }
    if (!args[i].startsWith("-")) return path.resolve(args[i]);
  }
  return path.join(__dirname, "..", DEFAULT_CSV);
}

function getOutputPath() {
  const args = process.argv.slice(2);
  const i = args.indexOf("-o");
  if (i !== -1 && args[i + 1]) return path.resolve(args[i + 1]);
  return path.join(__dirname, "..", "core", "out-specialties.json");
}

function useAsciiSlugs() {
  return process.argv.includes("--ascii-slugs");
}

function main() {
  const csvPath = getCsvPath();
  const outputPath = getOutputPath();

  if (!fs.existsSync(csvPath)) {
    console.error("שגיאה: קובץ לא נמצא:", csvPath);
    process.exit(1);
  }

  let raw;
  try {
    raw = fs.readFileSync(csvPath, "utf8");
  } catch (e) {
    console.error("שגיאה בקריאת הקובץ:", e.message);
    process.exit(1);
  }

  // BOM
  if (raw.charCodeAt(0) === 0xfeff) raw = raw.slice(1);
  const lines = raw.split(/\r?\n/).filter((l) => l.length > 0);
  if (lines.length === 0) {
    console.error("שגיאה: הקובץ ריק.");
    process.exit(1);
  }

  const rows = lines.map(parseCsvLine);
  const headers = rows[0].map((h) => h.trim());
  while (headers.length && !headers[headers.length - 1]) headers.pop();
  const numColumns = headers.length;
  const asciiSlugs = useAsciiSlugs();
  const seenSlugs = new Set();

  const result = [];

  for (let col = 0; col < numColumns; col++) {
    const name = headers[col];
    if (!name) continue;

    const slug = asciiSlugs
      ? `specialty-${col}`
      : (() => {
          let s = slugify(name);
          if (!s) s = `specialty-${col}`;
          else if (seenSlugs.has(s)) s = `${s}-${col}`;
          seenSlugs.add(s);
          return s;
        })();

    const treatments = [];
    const seen = new Set();
    for (let r = 1; r < rows.length; r++) {
      const row = rows[r];
      const cell = row[col] != null ? String(row[col]).trim() : "";
      if (!cell || seen.has(cell)) continue;
      seen.add(cell);
      treatments.push(cell);
    }

    result.push({ name, slug, treatments });
  }

  fs.writeFileSync(outputPath, JSON.stringify(result, null, 2), "utf8");
  console.log("נוצר:", outputPath);
  console.log("התמחויות:", result.length);
  const total = result.reduce((acc, o) => acc + o.treatments.length, 0);
  console.log('סה"כ טיפולים:', total);
}

main();

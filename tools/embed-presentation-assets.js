const fs = require("fs");
const path = require("path");

const repoRoot = path.resolve(__dirname, "..");
const htdocsRoot = path.resolve(repoRoot, "..");
const sourcePath = path.join(repoRoot, "Sari-Sari_COA_Interactive_Presentation.html");
const outputPath = path.join(repoRoot, "Sari-Sari_COA_Interactive_Presentation_STANDALONE.html");

const mimeTypes = {
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".svg": "image/svg+xml",
  ".webp": "image/webp",
};

function normalizeRef(ref) {
  return ref.replace(/\\/g, "/");
}

function resolveAsset(ref) {
  const clean = normalizeRef(ref).split("#")[0].split("?")[0];
  const candidates = [
    path.join(htdocsRoot, clean),
    path.join(repoRoot, clean),
  ];
  return candidates.find((candidate) => fs.existsSync(candidate));
}

function toDataUri(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  const mime = mimeTypes[ext];
  if (!mime) return null;
  return `data:${mime};base64,${fs.readFileSync(filePath).toString("base64")}`;
}

let html = fs.readFileSync(sourcePath, "utf8");
let embedded = 0;
const missing = new Set();

html = html.replace(/url\((["']?)([^"')]+)\1\)/g, (match, quote, ref) => {
  if (/^(data:|https?:|#)/i.test(ref)) return match;
  const filePath = resolveAsset(ref);
  if (!filePath) {
    missing.add(ref);
    return match;
  }
  const dataUri = toDataUri(filePath);
  if (!dataUri) return match;
  embedded++;
  return `url("${dataUri}")`;
});

html = html.replace(/\bsrc="([^"]+)"/g, (match, ref) => {
  if (/^(data:|https?:|#)/i.test(ref)) return match;
  const filePath = resolveAsset(ref);
  if (!filePath) {
    missing.add(ref);
    return match;
  }
  const dataUri = toDataUri(filePath);
  if (!dataUri) return match;
  embedded++;
  return `src="${dataUri}"`;
});

html = html.replace(/\s+onerror="[^"]*"/g, "");
html = html.replace(
  /<title>(.*?)<\/title>/,
  "<title>$1 - Standalone</title>"
);

fs.writeFileSync(outputPath, html, "utf8");

console.log(`Wrote ${outputPath}`);
console.log(`Embedded ${embedded} asset references`);
if (missing.size) {
  console.log("Missing references:");
  for (const ref of missing) console.log(`- ${ref}`);
}

import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const { data, info } = await sharp(path.join(root, 'public/un-logo-horizontal.webp'))
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

function isMagenta(r, g, b) {
  const lum = r + g + b;
  return lum > 150 && r > 80 && b > 60 && g < 120 && r - g > 15;
}

const buckets = { bg: 0, ink: 0, magenta: 0, skip: 0 };
for (let i = 0; i < data.length; i += 4) {
  const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
  if (a < 128) { buckets.skip++; continue; }
  if (r + g + b < 20) buckets.bg++;
  else if (isMagenta(r, g, b)) buckets.magenta++;
  else buckets.ink++;
}
console.log(buckets);

import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const { data, info } = await sharp(path.join(root, 'public/un-logo-horizontal.webp'))
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

const samples = [];
for (let y = 20; y <= 32; y++) {
  for (let x = 155; x < info.width; x++) {
    const i = (y * info.width + x) * 4;
    const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
    if (a > 128 && r + g + b > 20) {
      samples.push({ x, y, r, g, b, lum: r + g + b });
    }
  }
}

const colorCounts = new Map();
for (const s of samples) {
  const key = `${Math.round(s.r / 8) * 8},${Math.round(s.g / 8) * 8},${Math.round(s.b / 8) * 8}`;
  colorCounts.set(key, (colorCounts.get(key) || 0) + 1);
}

fs.writeFileSync(
  path.join(root, 'logo-right.json'),
  JSON.stringify(
    {
      top: [...colorCounts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 15),
      samples: samples.slice(0, 50),
    },
    null,
    2,
  ),
);
console.log('done');

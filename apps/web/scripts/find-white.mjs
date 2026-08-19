import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const { data, info } = await sharp(path.join(root, 'public/un-logo-horizontal.webp'))
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

let white = 0, light = 0;
for (let i = 0; i < data.length; i += 4) {
  const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
  if (a < 128) continue;
  if (r > 200 && g > 200 && b > 200) white++;
  else if (r + g + b > 550) light++;
}
console.log({ white, light, total: info.width * info.height });

// full width scan y=24 with finer classes
const y = 24;
const row = [];
for (let x = 0; x < info.width; x++) {
  const i = (y * info.width + x) * 4;
  const r = data[i], g = data[i + 1], b = data[i + 2];
  if (r + g + b < 20) row.push(' ');
  else if (r > 200 && g > 200 && b > 200) row.push('W');
  else if (r > 100 && b > 80 && g < 120) row.push('M');
  else if (r + g + b < 100) row.push('#');
  else row.push('.');
}
console.log(row.join(''));

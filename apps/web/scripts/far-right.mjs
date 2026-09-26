import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const { data, info } = await sharp(path.join(root, 'public/un-logo-horizontal.webp'))
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

for (let y = 18; y <= 30; y++) {
  for (let x = 185; x < info.width; x++) {
    const i = (y * info.width + x) * 4;
    const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
    if (a > 128 && r + g + b > 20) {
      console.log(`(${x},${y}) rgb(${r},${g},${b})`);
    }
  }
}

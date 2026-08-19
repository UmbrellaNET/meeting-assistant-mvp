import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const { data, info } = await sharp(path.join(root, 'public/un-logo-horizontal.webp'))
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

let lowAlphaDark = 0, highAlphaDark = 0;
for (let i = 0; i < data.length; i += 4) {
  const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
  const lum = r + g + b;
  if (lum >= 20 && lum < 150 && !(r > 80 && b > 60 && g < 120 && r - g > 15)) {
    if (a < 128) lowAlphaDark++;
    else highAlphaDark++;
  }
}
console.log({ lowAlphaDark, highAlphaDark });

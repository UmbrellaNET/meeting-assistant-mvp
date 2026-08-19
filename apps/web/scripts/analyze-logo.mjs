import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const { data, info } = await sharp(path.join(root, 'public/un-logo-horizontal.webp'))
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

for (const y of [15, 20, 25, 30, 35]) {
  const row = [];
  for (let x = 0; x < info.width; x++) {
    const i = (y * info.width + x) * 4;
    const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
    if (a < 128 || r + g + b < 20) row.push(' ');
    else if (r > 100 && b > 80 && g < 120) row.push('M');
    else if (r + g + b < 100) row.push('#');
    else row.push('.');
  }
  console.log('y=' + y + ':', row.join(''));
}

// x-range 130-200 likely NET area
console.log('\nPixels x=130-200 y=20-35:');
for (let y = 20; y <= 35; y++) {
  for (let x = 130; x <= 200; x++) {
    const i = (y * info.width + x) * 4;
    const r = data[i], g = data[i + 1], b = data[i + 2];
    if (r + g + b > 20) {
      console.log(`(${x},${y}) rgb(${r},${g},${b}) lum=${r+g+b}`);
    }
  }
}

import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';
import fs from 'fs';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const { data, info } = await sharp(path.join(root, 'public/un-logo-horizontal.webp'))
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

const lines = [];
for (let y = 15; y <= 38; y++) {
  let row = '';
  for (let x = 0; x < info.width; x++) {
    const i = (y * info.width + x) * 4;
    const r = data[i], g = data[i + 1], b = data[i + 2];
    if (r + g + b < 20) row += ' ';
    else if (r > 100 && b > 80 && g < 120) row += 'M';
    else if (r + g + b < 100) row += '#';
    else row += '.';
  }
  lines.push(`y=${y}: ${row}`);
}

fs.writeFileSync(path.join(root, 'logo-scan.txt'), lines.join('\n'));
console.log('written', lines.length, 'lines');

import sharp from 'sharp';
import path from 'path';
import { fileURLToPath } from 'url';

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');

for (const file of ['un-logo-horizontal.webp', 'un-logo-horizontal-light.webp']) {
  const { data, info } = await sharp(path.join(root, 'public', file))
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  const y = 25;
  const row = [];
  for (let x = 0; x < info.width; x++) {
    const i = (y * info.width + x) * 4;
    const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
    if (a < 128) row.push(' ');
    else if (r + g + b < 80) row.push('#');
    else if (r > 150 && g > 150 && b > 150) row.push('W');
    else if (r > 100 && b > 80 && g < 120) row.push('M');
    else row.push('.');
  }
  console.log(file, 'y=25:', row.join(''));

  let transparent = 0, magenta = 0, ink = 0, other = 0;
  for (let i = 0; i < data.length; i += 4) {
    const r = data[i], g = data[i + 1], b = data[i + 2], a = data[i + 3];
    if (a < 128) transparent++;
    else if (r === 18 && g === 18 && b === 20) ink++;
    else if (r > 100 && b > 80 && g < 120) magenta++;
    else other++;
  }
  console.log({ transparent, magenta, ink, other });
}

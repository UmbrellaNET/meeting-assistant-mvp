import sharp from 'sharp';
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const input = path.join(root, 'public/un-logo-horizontal.webp');
const output = path.join(root, 'public/un-logo-horizontal-light.webp');

const INK = { r: 18, g: 18, b: 20 }; // #121214

function isMagenta(r, g, b) {
  const lum = r + g + b;
  return lum > 150 && r > 80 && b > 60 && g < 120 && r - g > 15;
}

function isBackground(r, g, b, a) {
  return a < 8 || r + g + b < 20;
}

const { data, info } = await sharp(input)
  .ensureAlpha()
  .raw()
  .toBuffer({ resolveWithObject: true });

for (let i = 0; i < data.length; i += 4) {
  const r = data[i];
  const g = data[i + 1];
  const b = data[i + 2];
  const a = data[i + 3];

  if (isBackground(r, g, b, a)) {
    data[i + 3] = 0;
    continue;
  }

  if (isMagenta(r, g, b)) {
    data[i + 3] = 255;
    continue;
  }

  // semi-transparent dark letterforms (NET) and anti-aliasing -> ink
  data[i] = INK.r;
  data[i + 1] = INK.g;
  data[i + 2] = INK.b;
  data[i + 3] = 255;
}

await sharp(Buffer.from(data), {
  raw: { width: info.width, height: info.height, channels: 4 },
})
  .webp({ lossless: true })
  .toFile(output);

const meta = await sharp(output).metadata();
console.log('Created', output, `${meta.width}x${meta.height}`);

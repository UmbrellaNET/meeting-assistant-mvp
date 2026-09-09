import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const sharp = require('sharp');

const root = path.join(path.dirname(fileURLToPath(import.meta.url)), '..');
const publicDir = path.join(root, 'public');
const mark = path.join(root, 'scripts/favicon-mark.png');
const apple = path.join(publicDir, 'apple-touch-icon.png');

await sharp(mark)
  .resize(32, 32, { kernel: 'lanczos3' })
  .png({ compressionLevel: 9 })
  .toFile(path.join(publicDir, 'favicon.png'));

await sharp(mark)
  .resize(16, 16, { kernel: 'lanczos3' })
  .png({ compressionLevel: 9 })
  .toFile(path.join(publicDir, 'favicon-16.png'));

await sharp(apple)
  .resize(192, 192, { kernel: 'lanczos3' })
  .png({ compressionLevel: 9 })
  .toFile(path.join(publicDir, 'icon-192.png'));

for (const file of ['un-logo-horizontal.webp', 'favicon.png', 'favicon-16.png', 'apple-touch-icon.png', 'icon-192.png']) {
  const meta = await sharp(path.join(publicDir, file)).metadata();
  console.log(file, `${meta.width}x${meta.height}`);
}

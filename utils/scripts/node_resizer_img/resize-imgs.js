const fs = require('fs/promises');
const path = require('path');
const sharp = require('sharp');

const INPUT_DIR = path.join(__dirname, 'clrvbk');
const OUTPUT_DIR = path.join(__dirname, 'clrvbk-otimizadas');

const MAX_WIDTH = 500;
const MAX_HEIGHT = 500;
const QUALITY = 85;

async function processImage(inputPath, outputPath) {
  try {
    const image = sharp(inputPath);

    const metadata = await image.metadata();

    const originalWidth = metadata.width;
    const originalHeight = metadata.height;

    if (!originalWidth || !originalHeight) {
      throw new Error('Não foi possível obter as dimensões da imagem');
    }

    let width = originalWidth;
    let height = originalHeight;

    // Mesmo cálculo proporcional do seu código do browser
    if (width > MAX_WIDTH || height > MAX_HEIGHT) {
      const ratio = Math.min(
        MAX_WIDTH / width,
        MAX_HEIGHT / height
      );

      width = Math.round(width * ratio);
      height = Math.round(height * ratio);
    }

    await image
      .resize(width, height, {
        fit: 'fill',
      })
      .webp({
        quality: QUALITY,
      })
      .toFile(outputPath);

    const originalSize = (await fs.stat(inputPath)).size;
    const newSize = (await fs.stat(outputPath)).size;

    console.log(
      `${path.basename(inputPath)}: ` +
      `${originalWidth}x${originalHeight} -> ${width}x${height} | ` +
      `${formatBytes(originalSize)} -> ${formatBytes(newSize)}`
    );
  } catch (error) {
    console.error(`Erro ao processar ${inputPath}:`, error.message);
  }
}

function formatBytes(bytes) {
  if (bytes < 1024) {
    return `${bytes} B`;
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(2)} KB`;
  }

  return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
}

async function main() {
  await fs.mkdir(OUTPUT_DIR, { recursive: true });

  const files = await fs.readdir(INPUT_DIR);

  const webpFiles = files.filter(
    file => path.extname(file).toLowerCase() === '.webp'
  );

  console.log(`Encontradas ${webpFiles.length} imagens WebP.`);

  for (const file of webpFiles) {
    const inputPath = path.join(INPUT_DIR, file);
    const outputPath = path.join(OUTPUT_DIR, file);

    await processImage(inputPath, outputPath);
  }

  console.log('\nProcessamento concluído!');
}

main().catch(error => {
  console.error(error);
  process.exit(1);
});
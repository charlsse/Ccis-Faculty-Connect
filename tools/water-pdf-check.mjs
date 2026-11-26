import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { createCanvas } = require('../storage/app/private/water-qa/node_modules/@napi-rs/canvas');
const pdfjs = await import('../storage/app/private/water-qa/node_modules/pdfjs-dist/legacy/build/pdf.mjs');
const directory = path.resolve('storage/app/private/water-qa');
const doc = await pdfjs.getDocument({ data: new Uint8Array(fs.readFileSync(path.join(directory, 'verified-receipt.pdf'))), useSystemFonts: true }).promise;
const text = [];
for (let number = 1; number <= doc.numPages; number++) {
    const page = await doc.getPage(number);
    const viewport = page.getViewport({ scale: 1.5 });
    const canvas = createCanvas(Math.ceil(viewport.width), Math.ceil(viewport.height));
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    fs.writeFileSync(path.join(directory, `receipt-pdf-page-${number}.png`), canvas.toBuffer('image/png'));
    text.push((await page.getTextContent()).items.map(item => item.str).join(' '));
}
if (!text.join(' ').includes('VERIFIED PAYMENT') || !text.join(' ').includes('QA Faculty Synthetic')) throw new Error('Receipt text missing');
console.log(JSON.stringify({ pages: doc.numPages, receiptTextVerified: true, renderedPages: doc.numPages }));

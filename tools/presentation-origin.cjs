'use strict';
const fs = require('node:fs');
const path = require('node:path');
module.exports = function presentationOrigin() {
  const file = path.join(__dirname, '../storage/app/private/presentation-origin.json');
  if (!fs.existsSync(file)) return 'http://127.0.0.1:8000';
  const origin = JSON.parse(fs.readFileSync(file, 'utf8')).origin;
  if (origin !== 'https://dui-absolutely-beam-plains.trycloudflare.com') throw new Error('Unapproved presentation origin');
  return origin;
};

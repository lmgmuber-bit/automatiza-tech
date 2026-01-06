const fs = require('fs');
const filePath = 'c:/wamp64/www/automatiza-tech/N8N/PROD/WhatsApp_Tech_Principal.json';

let c = fs.readFileSync(filePath, 'utf8');

// Fix briefcase emoji: ðŸ'¼ -> 💼
// Pattern: \u00f0\u0178\u2019\u00bc
const badBriefcase = '\u00f0\u0178\u2019\u00bc';
const goodBriefcase = '\ud83d\udcbc'; // 💼

const count = (c.match(new RegExp(badBriefcase, 'g')) || []).length;
console.log('Briefcase emoji matches:', count);

c = c.split(badBriefcase).join(goodBriefcase);

// Also fix any other corrupted emojis with similar pattern
// ðŸ pattern starts with \u00f0\u0178
const emojiPatterns = [
  ['\u00f0\u0178\u201c\u0085', '\ud83d\udcc5'], // 📅
  ['\u00f0\u0178\u00a4\u0096', '\ud83e\udd16'], // 🤖
  ['\u00f0\u0178\u00a4\u201d', '\ud83e\udd14'], // 🤔
  ['\u00f0\u0178\u2019\u008b', '\ud83d\udc4b'], // 👋
  ['\u00f0\u0178\u0178\u2030', '\ud83c\udf89'], // 🎉
];

for (const [bad, good] of emojiPatterns) {
  const cnt = (c.match(new RegExp(bad, 'g')) || []).length;
  if (cnt > 0) {
    console.log('Fixed emoji pattern:', cnt);
    c = c.split(bad).join(good);
  }
}

fs.writeFileSync(filePath, c, 'utf8');
console.log('Guardado');

// Verify
const check = fs.readFileSync(filePath, 'utf8');
console.log('Contains good briefcase:', check.includes('\ud83d\udcbc'));
const sample = check.substring(check.indexOf('Selecciona un plan')-5, check.indexOf('Selecciona un plan')+25);
console.log('Sample:', sample);

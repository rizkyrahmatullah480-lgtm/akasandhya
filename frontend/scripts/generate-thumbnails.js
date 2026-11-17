import fs from 'fs';
import path from 'path';

const outDir = path.resolve('static/templates');
const coversDir = path.resolve('static/templates/covers');

if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });
if (!fs.existsSync(coversDir)) fs.mkdirSync(coversDir, { recursive: true });

const templates = [
  {
    name: 'elegant-floral',
    title: 'The Wedding Of',
    names: 'Rian & Sinta',
    subtitle: 'Sabtu, 14 Juni 2026',
    bg: '#FAF8F6',
    primary: '#7B2D43',
    accent: '#D6B98C',
    decor: `
      <circle cx="200" cy="180" r="110" fill="none" stroke="#D6B98C" stroke-width="2" stroke-dasharray="4 4" />
      <circle cx="200" cy="180" r="100" fill="#FFFFFF" fill-opacity="0.9" />
      <!-- Botanical Wreath -->
      <path d="M 120 180 C 120 120, 280 120, 280 180 C 280 240, 120 240, 120 180" fill="none" stroke="#7B2D43" stroke-width="1.5" stroke-opacity="0.3"/>
      <path d="M 130 150 Q 140 135 155 140 Q 160 155 145 160 Z" fill="#7B2D43" fill-opacity="0.7"/>
      <path d="M 270 150 Q 260 135 245 140 Q 240 155 255 160 Z" fill="#7B2D43" fill-opacity="0.7"/>
      <path d="M 140 215 Q 150 230 165 225 Q 170 210 155 205 Z" fill="#D6B98C" />
      <path d="M 260 215 Q 250 230 235 225 Q 230 210 245 205 Z" fill="#D6B98C" />
    `,
    font: 'Playfair Display, serif',
  },
  {
    name: 'minimalist-white',
    title: 'P E R N I K A H A N',
    names: 'ANDI + CLARA',
    subtitle: 'JAKARTA • 2026',
    bg: '#FFFFFF',
    primary: '#1F2937',
    accent: '#9CA3AF',
    decor: `
      <rect x="50" y="50" width="300" height="260" fill="none" stroke="#E5E7EB" stroke-width="1.5" />
      <line x1="180" y1="130" x2="220" y2="130" stroke="#1F2937" stroke-width="2" />
      <text x="200" y="90" font-family="Inter, sans-serif" font-size="10" fill="#9CA3AF" text-anchor="middle" letter-spacing="4">SAVE THE DATE</text>
    `,
    font: 'Inter, sans-serif',
  },
  {
    name: 'classic-burgundy',
    title: 'Walimatul Ursy',
    names: 'Dimas & Anisa',
    subtitle: '22 Agustus 2026',
    bg: '#58111A',
    primary: '#FFFFFF',
    accent: '#E5C158',
    decor: `
      <rect x="30" y="30" width="340" height="300" fill="none" stroke="#E5C158" stroke-width="2" />
      <rect x="36" y="36" width="328" height="288" fill="none" stroke="#E5C158" stroke-width="0.8" />
      <!-- Crest Top -->
      <polygon points="200,45 210,60 190,60" fill="#E5C158" />
      <circle cx="200" cy="75" r="4" fill="#E5C158" />
      <path d="M 170 75 Q 200 65 230 75" fill="none" stroke="#E5C158" stroke-width="1" />
    `,
    font: 'Cormorant Garamond, serif',
  },
  {
    name: 'golden-luxury',
    title: 'THE ROYAL WEDDING',
    names: 'KENZO & AUREL',
    subtitle: 'HOTEL MULIA • 2026',
    bg: '#181616',
    primary: '#FAF0D7',
    accent: '#C5A059',
    decor: `
      <defs>
        <linearGradient id="goldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="#C5A059" />
          <stop offset="50%" stop-color="#FDF1D6" />
          <stop offset="100%" stop-color="#9E7830" />
        </linearGradient>
      </defs>
      <circle cx="200" cy="180" r="105" fill="none" stroke="url(#goldGrad)" stroke-width="3" />
      <circle cx="200" cy="180" r="115" fill="none" stroke="#C5A059" stroke-width="0.8" stroke-opacity="0.5" />
      <!-- Sparkles -->
      <path d="M 200 60 L 203 68 L 211 71 L 203 74 L 200 82 L 197 74 L 189 71 L 197 68 Z" fill="#FDF1D6" />
      <path d="M 90 180 L 92 185 L 97 187 L 92 189 L 90 194 L 88 189 L 83 187 L 88 185 Z" fill="#C5A059" />
      <path d="M 310 180 L 312 185 L 317 187 L 312 189 L 310 194 L 308 189 L 303 187 L 308 185 Z" fill="#C5A059" />
    `,
    font: 'Playfair Display, serif',
  },
  {
    name: 'rustic-garden',
    title: 'Lamaran & Pertunangan',
    names: 'Bayu & Nadia',
    subtitle: 'Taman Suropati • 2026',
    bg: '#F7F8F5',
    primary: '#4A5D4E',
    accent: '#D1BC9E',
    decor: `
      <!-- Kraft Box & Eucalyptus -->
      <rect x="60" y="50" width="280" height="260" rx="16" fill="#FFFFFF" stroke="#D1BC9E" stroke-width="1.5" />
      <path d="M 70 80 Q 90 50 130 65" fill="none" stroke="#7A8F7E" stroke-width="2" />
      <ellipse cx="85" cy="62" rx="8" ry="4" transform="rotate(-30 85 62)" fill="#7A8F7E" />
      <ellipse cx="115" cy="58" rx="8" ry="4" transform="rotate(20 115 58)" fill="#4A5D4E" />
      <path d="M 330 280 Q 310 310 270 295" fill="none" stroke="#7A8F7E" stroke-width="2" />
      <ellipse cx="315" cy="298" rx="8" ry="4" transform="rotate(-30 315 298)" fill="#7A8F7E" />
      <ellipse cx="285" cy="302" rx="8" ry="4" transform="rotate(20 285 302)" fill="#4A5D4E" />
    `,
    font: 'Cormorant Garamond, serif',
  },
  {
    name: 'modern-monogram',
    title: 'CELEBRATING LOVE',
    names: 'F & G',
    subtitle: 'Fajar & Gita • 2026',
    bg: '#F9FBFB',
    primary: '#2C3E50',
    accent: '#C5A880',
    decor: `
      <circle cx="200" cy="150" r="70" fill="#2C3E50" fill-opacity="0.05" />
      <circle cx="200" cy="150" r="65" fill="none" stroke="#C5A880" stroke-width="2" />
      <text x="200" y="165" font-family="Playfair Display, serif" font-size="44" font-weight="bold" fill="#2C3E50" text-anchor="middle">FG</text>
    `,
    font: 'Playfair Display, serif',
  },
  {
    name: 'islamic-wedding',
    title: 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ',
    names: 'Hafiz & Fatimah',
    subtitle: 'Akad Nikah • 10 Dzulhijjah',
    bg: '#FAFBF7',
    primary: '#1B4D3E',
    accent: '#D4AF37',
    decor: `
      <!-- Mosque Dome Arch -->
      <path d="M 80 320 L 80 180 C 80 120, 200 60, 200 60 C 200 60, 320 120, 320 180 L 320 320" fill="none" stroke="#D4AF37" stroke-width="2" />
      <path d="M 90 320 L 90 185 C 90 130, 200 75, 200 75 C 200 75, 310 130, 310 185 L 310 320" fill="none" stroke="#2E7D63" stroke-width="1" stroke-opacity="0.5" />
      <circle cx="200" cy="60" r="5" fill="#D4AF37" />
    `,
    font: 'Cormorant Garamond, serif',
  },
  {
    name: 'traditional-java',
    title: 'Pawiwahan Ageng',
    names: 'Raden & Sekar',
    subtitle: 'Surakarta Hadiningrat',
    bg: '#FBF7EE',
    primary: '#4A2E18',
    accent: '#C69214',
    decor: `
      <!-- Gunungan Wayang Silhouette Outline -->
      <path d="M 200 50 C 230 110, 270 170, 290 230 C 270 250, 230 250, 200 240 C 170 250, 130 250, 110 230 C 130 170, 170 110, 200 50 Z" fill="#C69214" fill-opacity="0.12" stroke="#C69214" stroke-width="2" />
      <line x1="200" y1="50" x2="200" y2="240" stroke="#C69214" stroke-width="1" stroke-dasharray="3 3" />
      <circle cx="200" cy="50" r="4" fill="#C69214" />
    `,
    font: 'Playfair Display, serif',
  },
  {
    name: 'modern-birthday',
    title: "SWEET 17TH BIRTHDAY",
    names: "Nathalie's Party",
    subtitle: 'Saturday Night • 19:00 WIB',
    bg: '#FDF7FC',
    primary: '#8E44AD',
    accent: '#E91E63',
    decor: `
      <!-- Confetti & Balloons -->
      <circle cx="90" cy="80" r="24" fill="#E91E63" fill-opacity="0.8" />
      <path d="M 90 104 L 90 140" stroke="#E91E63" stroke-width="1.5" stroke-dasharray="2 2" />
      <circle cx="310" cy="70" r="28" fill="#8E44AD" fill-opacity="0.8" />
      <path d="M 310 98 L 310 140" stroke="#8E44AD" stroke-width="1.5" stroke-dasharray="2 2" />
      <circle cx="200" cy="80" r="5" fill="#FFC107" />
      <circle cx="150" cy="110" r="4" fill="#2980B9" />
      <circle cx="250" cy="110" r="4" fill="#E91E63" />
      <circle cx="120" cy="270" r="6" fill="#8E44AD" />
      <circle cx="280" cy="260" r="5" fill="#FFC107" />
    `,
    font: 'Playfair Display, serif',
  },
  {
    name: 'simple-family-event',
    title: 'Tasyakuran & Silaturahmi',
    names: 'Keluarga Bpk. Santoso',
    subtitle: 'Kebersamaan Dalam Keberkahan',
    bg: '#F4F9FC',
    primary: '#1B3A4B',
    accent: '#2980B9',
    decor: `
      <rect x="50" y="60" width="300" height="240" rx="24" fill="#FFFFFF" stroke="#E2E8F0" stroke-width="2" />
      <circle cx="200" cy="120" r="32" fill="#2980B9" fill-opacity="0.1" />
      <path d="M 188 120 C 188 113, 193 108, 200 108 C 207 108, 212 113, 212 120 C 212 127, 207 132, 200 132 C 193 132, 188 127, 188 120 Z" fill="#2980B9" />
      <path d="M 180 136 C 180 130, 189 128, 200 128 C 211 128, 220 130, 220 136 L 220 140 L 180 140 Z" fill="#2980B9" />
    `,
    font: 'Inter, sans-serif',
  },
];

for (const t of templates) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 360" width="400" height="360">
    <rect width="100%" height="100%" fill="${t.bg}" />
    ${t.decor}
    <g text-anchor="middle">
      <text x="200" y="${t.name === 'islamic-wedding' ? '125' : '150'}" font-family="${t.font}" font-size="11" font-weight="600" fill="${t.accent}" letter-spacing="2">${t.title}</text>
      <text x="200" y="${t.name === 'islamic-wedding' ? '195' : '205'}" font-family="${t.font}" font-size="24" font-weight="bold" fill="${t.primary}">${t.names}</text>
      <text x="200" y="${t.name === 'islamic-wedding' ? '230' : '235'}" font-family="Inter, sans-serif" font-size="11" fill="${t.primary}" opacity="0.75" letter-spacing="1">${t.subtitle}</text>
    </g>
  </svg>`;

  fs.writeFileSync(path.join(outDir, `${t.name}.svg`), svg);
  fs.writeFileSync(path.join(outDir, `${t.name}.jpg`), svg); // support jpg requests seamlessly
}

// Generate sample wedding demo cover
const demoCoverSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" width="800" height="600">
  <defs>
    <linearGradient id="coverGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#7B2D43" />
      <stop offset="100%" stop-color="#4A1423" />
    </linearGradient>
  </defs>
  <rect width="100%" height="100%" fill="url(#coverGrad)" />
  <circle cx="400" cy="300" r="220" fill="none" stroke="#D6B98C" stroke-width="2" stroke-dasharray="6 6" />
  <circle cx="400" cy="300" r="200" fill="#FAF8F6" fill-opacity="0.08" />
  <g text-anchor="middle" fill="#FFFFFF">
    <text x="400" y="240" font-family="Playfair Display, serif" font-size="20" fill="#D6B98C" letter-spacing="4">THE WEDDING OF</text>
    <text x="400" y="320" font-family="Playfair Display, serif" font-size="48" font-weight="bold">Rian &amp; Sinta</text>
    <text x="400" y="370" font-family="Inter, sans-serif" font-size="16" fill="#FAF8F6" opacity="0.85" letter-spacing="2">SABTU, 14 JUNI 2026</text>
  </g>
</svg>`;

fs.writeFileSync(path.join(coversDir, 'wedding-demo.jpg'), demoCoverSvg);
fs.writeFileSync(path.join(coversDir, 'wedding-demo.svg'), demoCoverSvg);

console.log('Successfully generated 10 template vector thumbnails and demo covers!');

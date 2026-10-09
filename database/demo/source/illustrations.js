// Artificial, clearly illustrative demo scenes for the DevelopmentDemoSeeder.
// They are NOT photographs of Merching and must never be used as real content.
// Rendered to JPEG by render-images.mjs (development only).

const label = (w, h) => `<g font-family="Arial, sans-serif" font-size="${Math.round(h * 0.028)}" font-weight="700">
  <rect x="${w - h * 0.2}" y="${h * 0.93}" width="${h * 0.18}" height="${h * 0.05}" rx="${h * 0.01}" fill="#ffffff" fill-opacity=".82"/>
  <text x="${w - h * 0.11}" y="${h * 0.964}" text-anchor="middle" fill="#16202e">Demobild</text></g>`;

const sky = (w, h, top = '#7fb6f2', bottom = '#e6f2ff', id = 'sky') => `<defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${top}"/><stop offset="1" stop-color="${bottom}"/></linearGradient></defs><rect width="${w}" height="${h}" fill="url(#${id})"/>`;

const cloud = (x, y, s, o = 0.9) => `<g fill="#fff" fill-opacity="${o}"><ellipse cx="${x}" cy="${y}" rx="${70 * s}" ry="${26 * s}"/><ellipse cx="${x + 45 * s}" cy="${y - 18 * s}" rx="${48 * s}" ry="${30 * s}"/><ellipse cx="${x - 40 * s}" cy="${y - 10 * s}" rx="${38 * s}" ry="${22 * s}"/></g>`;

const tree = (x, ground, s, c = '#3f7d4e', c2 = '#4f9460') => `<g><rect x="${x - 7 * s}" y="${ground - 70 * s}" width="${14 * s}" height="${70 * s}" fill="#6b4f36"/><circle cx="${x}" cy="${ground - 105 * s}" r="${52 * s}" fill="${c}"/><circle cx="${x - 30 * s}" cy="${ground - 80 * s}" r="${36 * s}" fill="${c2}"/><circle cx="${x + 32 * s}" cy="${ground - 84 * s}" r="${38 * s}" fill="${c2}"/></g>`;

const hills = (w, h, y, color, amp = 40) => `<path d="M0 ${y} C ${w * 0.2} ${y - amp}, ${w * 0.35} ${y + amp * 0.6}, ${w * 0.55} ${y - amp * 0.3} S ${w * 0.85} ${y - amp}, ${w} ${y - amp * 0.2} L ${w} ${h} L 0 ${h} Z" fill="${color}"/>`;

export const scenes = {
    rathaus: { width: 1600, height: 1067, draw(w, h) {
        const g = h * 0.8;
        let windows = '';
        for (let row = 0; row < 3; row++) for (let col = 0; col < 7; col++) {
            if (row === 2 && col === 3) continue;
            const x = 470 + col * 96, y = 380 + row * 125;
            windows += `<rect x="${x}" y="${y}" width="48" height="70" rx="3" fill="#2d4a6b"/><rect x="${x + 4}" y="${y + 4}" width="40" height="62" fill="#9cc4ea"/><line x1="${x + 24}" y1="${y + 4}" x2="${x + 24}" y2="${y + 66}" stroke="#f4f4f0" stroke-width="3"/><rect x="${x - 6}" y="${y + 70}" width="60" height="12" fill="#8a5a3c"/><g fill="#d64545"><circle cx="${x + 2}" cy="${y + 68}" r="7"/><circle cx="${x + 16}" cy="${y + 66}" r="7" fill="#e86a8a"/><circle cx="${x + 30}" cy="${y + 68}" r="7"/><circle cx="${x + 44}" cy="${y + 66}" r="7" fill="#e86a8a"/></g>`;
        }
        return `${sky(w, h)}${cloud(260, 170, 1.3)}${cloud(1300, 120, 1)}${hills(w, h, g - 40, '#9fcf8f', 30)}
        <polygon points="420,340 800,170 1180,340" fill="#7a4b39"/><polygon points="700,215 800,150 900,215 900,250 700,250" fill="#7a4b39"/>
        <rect x="430" y="335" width="740" height="${g - 335}" fill="#e7ebf0"/><rect x="430" y="335" width="740" height="16" fill="#cfd6df"/>
        <rect x="745" y="190" width="110" height="150" fill="#e7ebf0"/><circle cx="800" cy="255" r="30" fill="#fff" stroke="#4b4f58" stroke-width="5"/><line x1="800" y1="255" x2="800" y2="236" stroke="#16202e" stroke-width="5"/><line x1="800" y1="255" x2="815" y2="262" stroke="#16202e" stroke-width="5"/>
        ${windows}<path d="M760 ${g} V 655 a 40 40 0 0 1 80 0 V ${g} Z" fill="#5b3b2a"/><rect x="740" y="${g}" width="120" height="10" fill="#b8bfc8"/><rect x="720" y="${g + 10}" width="160" height="10" fill="#aab2bc"/>
        ${tree(250, g, 1.6)}${tree(1360, g, 1.8, '#356f45', '#468a58')}${tree(140, g + 10, 1.1)}
        <rect y="${g + 20}" width="${w}" height="${h - g}" fill="#c9c3b4"/><rect y="${g + 20}" width="${w}" height="8" fill="#b3ad9f"/>${label(w, h)}`;
    } },
    see: { width: 1920, height: 1080, draw(w, h) {
        const shore = h * 0.52;
        let reflections = '';
        for (let i = 0; i < 18; i++) reflections += `<rect x="${(i * 337) % w}" y="${shore + 40 + (i * 53) % (h * 0.4)}" width="${80 + (i * 31) % 140}" height="4" rx="2" fill="#fff" fill-opacity=".35"/>`;
        const boat = (x, y, s) => `<g><polygon points="${x},${y} ${x + 70 * s},${y} ${x + 58 * s},${y + 16 * s} ${x + 10 * s},${y + 16 * s}" fill="#f5f5f5"/><line x1="${x + 34 * s}" y1="${y}" x2="${x + 34 * s}" y2="${y - 120 * s}" stroke="#4b4f58" stroke-width="${3 * s}"/><polygon points="${x + 36 * s},${y - 116 * s} ${x + 36 * s},${y - 6 * s} ${x + 84 * s},${y - 8 * s}" fill="#fff"/><polygon points="${x + 32 * s},${y - 100 * s} ${x + 32 * s},${y - 8 * s} ${x - 4 * s},${y - 10 * s}" fill="#e9eef5"/></g>`;
        return `${sky(w, h, '#6aa8ee', '#d9ecff')}${cloud(400, 160, 1.6)}${cloud(1500, 210, 1.2)}${cloud(1000, 110, 0.8, 0.7)}
        <path d="M0 ${shore} ${Array.from({ length: 40 }, (_, i) => `Q ${i * 50 + 25} ${shore - 60 - (i % 3) * 18} ${i * 50 + 50} ${shore}`).join(' ')} L ${w} ${shore} L ${w} ${shore + 10} L 0 ${shore + 10} Z" fill="#2f6a45"/>
        <defs><linearGradient id="water" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4f8fcf"/><stop offset="1" stop-color="#2a5f99"/></linearGradient></defs>
        <rect y="${shore + 8}" width="${w}" height="${h - shore}" fill="url(#water)"/>${reflections}
        ${boat(560, shore + 120, 1)}${boat(1240, shore + 80, 0.7)}${boat(1500, shore + 170, 1.2)}
        <path d="M0 ${h} V ${h - 90} C 200 ${h - 140}, 380 ${h - 60}, 600 ${h - 100} S 900 ${h - 70}, 1000 ${h} Z" fill="#3d7a46"/>
        ${Array.from({ length: 26 }, (_, i) => `<line x1="${40 + i * 22}" y1="${h}" x2="${30 + i * 22 + (i % 4) * 6}" y2="${h - 150 - (i % 5) * 30}" stroke="#56924f" stroke-width="6" stroke-linecap="round"/>`).join('')}${label(w, h)}`;
    } },
    fest: { width: 1600, height: 1200, draw(w, h) {
        const g = h * 0.78;
        let bulbs = '';
        const colors = ['#ffd75e', '#ff9f5a', '#94c3f7', '#ffffff'];
        for (let i = 0; i <= 24; i++) { const x = i * (w / 24); const y = 230 + Math.sin((i / 24) * Math.PI * 2) * 40 + 40; bulbs += `<circle cx="${x}" cy="${y}" r="11" fill="${colors[i % 4]}"/><circle cx="${x}" cy="${y}" r="26" fill="${colors[i % 4]}" fill-opacity=".18"/>`; }
        return `${sky(w, h, '#1c2f5e', '#f0a46a', 'dusk')}<circle cx="1260" cy="${g - 120}" r="90" fill="#ffcf7a" fill-opacity=".7"/>
        ${hills(w, h, g - 30, '#3b4f45', 25)}
        <polygon points="300,${g} 300,520 620,380 940,520 940,${g}" fill="#f6f1e7"/><polygon points="270,530 620,360 970,530" fill="#e1d7c6"/>
        ${Array.from({ length: 8 }, (_, i) => `<rect x="${330 + i * 76}" y="520" width="38" height="${g - 520}" fill="#efe6d6"/>`).join('')}
        <rect x="560" y="600" width="120" height="${g - 600}" fill="#3a2f2a"/>
        <path d="M0 270 Q ${w / 2} 360 ${w} 270" stroke="#2b2b2b" stroke-width="3" fill="none"/>${bulbs}
        ${Array.from({ length: 12 }, (_, i) => `<polygon points="${i * 70 + 980},300 ${i * 70 + 1010},300 ${i * 70 + 995},340" fill="${i % 2 ? '#ffffff' : '#5a9be6'}"/>`).join('')}
        <rect y="${g}" width="${w}" height="${h - g}" fill="#5c5446"/>
        ${[1040, 1300].map(x => `<rect x="${x}" y="${g - 60}" width="200" height="16" fill="#8a6542"/><rect x="${x + 20}" y="${g - 44}" width="12" height="44" fill="#6b4f36"/><rect x="${x + 168}" y="${g - 44}" width="12" height="44" fill="#6b4f36"/>`).join('')}${label(w, h)}`;
    } },
    spielplatz: { width: 1200, height: 1200, draw(w, h) {
        const g = h * 0.72;
        return `${sky(w, h, '#86bdf5', '#eaf4ff')}${cloud(240, 160, 1)}${cloud(900, 220, 0.8)}
        ${tree(110, g, 1.5)}${tree(1080, g, 1.7, '#356f45', '#468a58')}${tree(960, g + 20, 1.1)}
        <rect y="${g}" width="${w}" height="${h - g}" fill="#e3cf9e"/>
        ${Array.from({ length: 40 }, (_, i) => `<circle cx="${(i * 97) % w}" cy="${g + 30 + (i * 41) % (h - g - 40)}" r="4" fill="#c9b37f"/>`).join('')}
        <path d="M250 ${g + 40} L 950 ${g + 40} L 880 ${g + 170} L 330 ${g + 170} Z" fill="#a8743f"/>
        ${Array.from({ length: 6 }, (_, i) => `<line x1="${260 + i * 6}" y1="${g + 60 + i * 20}" x2="${940 - i * 12}" y2="${g + 60 + i * 20}" stroke="#8a5a2f" stroke-width="4"/>`).join('')}
        <rect x="300" y="${g - 140}" width="160" height="180" fill="#b98550"/><polygon points="290,${g - 140} 380,${g - 210} 470,${g - 140}" fill="#8a5a2f"/>
        <rect x="590" y="${g - 380}" width="16" height="420" fill="#7a5230"/><polygon points="610,${g - 370} 610,${g - 90} 800,${g - 110}" fill="#f6f1e7"/><polygon points="586,${g - 330} 586,${g - 120} 450,${g - 130}" fill="#efe6d6"/>
        <polygon points="606,${g - 384} 660,${g - 370} 606,${g - 356}" fill="#d64545"/>
        <path d="M830 ${g + 40} L 960 ${g - 60} L 990 ${g - 40} L 870 ${g + 60} Z" fill="#5a9be6"/>${label(w, h)}`;
    } },
    baustelle: { width: 1600, height: 900, draw(w, h) {
        const g = h * 0.55;
        const barrier = (x, y, s) => `<g><rect x="${x}" y="${y}" width="${220 * s}" height="${36 * s}" fill="#fff" stroke="#c62828" stroke-width="${3 * s}"/>${Array.from({ length: 5 }, (_, i) => `<polygon points="${x + i * 44 * s},${y + 36 * s} ${x + (i * 44 + 22) * s},${y} ${x + (i * 44 + 44) * s},${y} ${x + (i * 44 + 22) * s},${y + 36 * s}" fill="#d32f2f"/>`).join('')}<rect x="${x + 16 * s}" y="${y + 36 * s}" width="${8 * s}" height="${70 * s}" fill="#4b4f58"/><rect x="${x + 196 * s}" y="${y + 36 * s}" width="${8 * s}" height="${70 * s}" fill="#4b4f58"/></g>`;
        const house = (x, s, c) => `<g><rect x="${x}" y="${g - 150 * s}" width="${170 * s}" height="${150 * s}" fill="${c}"/><polygon points="${x - 14 * s},${g - 150 * s} ${x + 85 * s},${g - 240 * s} ${x + 184 * s},${g - 150 * s}" fill="#8b4a3a"/><rect x="${x + 30 * s}" y="${g - 115 * s}" width="${36 * s}" height="${40 * s}" fill="#7da6cf"/><rect x="${x + 100 * s}" y="${g - 115 * s}" width="${36 * s}" height="${40 * s}" fill="#7da6cf"/></g>`;
        return `${sky(w, h)}${cloud(1200, 120, 1.1)}${house(80, 1, '#f1e4c8')}${house(330, 0.9, '#e4ecf3')}${house(1180, 1.1, '#f3dfd6')}${tree(620, g, 0.9)}${tree(1050, g, 1)}
        <rect y="${g}" width="${w}" height="${h - g}" fill="#8fbf7f"/><polygon points="560,${g} 1040,${g} 1500,${h} 100,${h}" fill="#6d737b"/>
        ${Array.from({ length: 5 }, (_, i) => `<polygon points="${795 - i * 4},${g + 20 + i * 70} ${805 + i * 4},${g + 20 + i * 70} ${808 + i * 6},${g + 60 + i * 70} ${792 - i * 6},${g + 60 + i * 70}" fill="#f4f4f0"/>`).join('')}
        ${barrier(460, h - 260, 1.4)}${barrier(860, h - 300, 1.2)}
        <polygon points="1330,${h - 90} 1370,${h - 230} 1410,${h - 90}" fill="#f57c00"/><rect x="1342" y="${h - 180}" width="56" height="16" fill="#fff"/><rect x="1310" y="${h - 95}" width="120" height="16" fill="#3a3a3a"/>
        <rect x="180" y="${g + 20}" width="12" height="210" fill="#4b4f58"/><rect x="90" y="${g + 20}" width="200" height="90" rx="6" fill="#ffd400" stroke="#16202e" stroke-width="5"/><text x="190" y="${g + 77}" font-family="Arial, sans-serif" font-size="36" font-weight="700" text-anchor="middle" fill="#16202e">Umleitung</text>${label(w, h)}`;
    } },
    maibaum: { width: 1067, height: 1600, draw(w, h) {
        const g = h * 0.86;
        let stripes = '';
        for (let y = 260; y < g; y += 70) stripes += `<polygon points="512,${y} 556,${y + 35} 512,${y + 70} 468,${y + 35}" fill="#5a9be6"/>`;
        let signs = '';
        for (let i = 0; i < 5; i++) { const y = 420 + i * 190; const side = i % 2 ? 1 : -1; signs += `<line x1="512" y1="${y}" x2="${512 + side * 150}" y2="${y}" stroke="#6b4f36" stroke-width="8"/><rect x="${side > 0 ? 600 : 304}" y="${y - 40}" width="110" height="80" rx="8" fill="#f6f1e7" stroke="#5a9be6" stroke-width="6"/>`; }
        return `${sky(w, h, '#5f9fe6', '#e4f1ff')}${cloud(220, 240, 1.2)}${cloud(860, 420, 0.9)}
        <rect x="468" y="200" width="88" height="${g - 200}" fill="#ffffff"/>${stripes}${signs}
        <circle cx="512" cy="230" r="80" fill="none" stroke="#3f7d4e" stroke-width="26"/><polygon points="512,40 540,190 484,190" fill="#3f7d4e"/>
        ${hills(w, h, g - 20, '#86b974', 30)}${tree(130, g + 10, 1.2)}${tree(940, g + 10, 1.3, '#356f45', '#468a58')}
        <rect y="${g}" width="${w}" height="${h - g}" fill="#7aa86a"/>${label(w, h)}`;
    } },
    feuerwehr: { width: 1600, height: 1067, draw(w, h) {
        const g = h * 0.8;
        return `${sky(w, h)}${cloud(320, 150, 1.1)}${cloud(1240, 190, 1.3)}
        <rect x="300" y="380" width="1000" height="${g - 380}" fill="#f2efe9"/><polygon points="280,385 800,250 1320,385" fill="#5b5f68"/>
        <rect x="1200" y="180" width="110" height="${g - 180}" fill="#e3ded5"/><polygon points="1190,185 1255,120 1320,185" fill="#5b5f68"/>
        ${[360, 620, 880].map(x => `<rect x="${x}" y="500" width="220" height="${g - 500}" fill="#c62828"/>${Array.from({ length: 4 }, (_, i) => `<rect x="${x}" y="${510 + i * ((g - 510) / 4)}" width="220" height="5" fill="#9e1f1f"/>`).join('')}<rect x="${x + 20}" y="520" width="180" height="40" fill="#9cc4ea"/>`).join('')}
        <rect x="560" y="410" width="480" height="60" rx="6" fill="#16202e"/><text x="800" y="452" font-family="Arial, sans-serif" font-size="38" font-weight="700" fill="#fff" text-anchor="middle">FEUERWEHR</text>
        <rect y="${g}" width="${w}" height="${h - g}" fill="#a9adb3"/>${tree(150, g, 1.4)}${tree(1460, g, 1.5, '#356f45', '#468a58')}${label(w, h)}`;
    } },
    herbst: { width: 1600, height: 1067, draw(w, h) {
        const g = h * 0.5;
        let rows = '';
        for (let i = 0; i < 14; i++) rows += `<path d="M ${-200 + i * 160} ${h} L ${700 + i * 20} ${g + 20}" stroke="#b98a3e" stroke-width="${18 - i * 0.6}" stroke-opacity=".55"/>`;
        return `${sky(w, h, '#8cc0f0', '#fbefd9')}${cloud(380, 140, 1.2)}${cloud(1280, 110, 0.9)}
        ${hills(w, h, g, '#9db56f', 50)}<rect y="${g + 20}" width="${w}" height="${h - g}" fill="#d9b25a"/>${rows}
        <polygon points="760,${g + 20} 840,${g + 20} 1100,${h} 500,${h}" fill="#c9b79a"/>
        ${tree(200, g + 30, 1.3, '#d9822b', '#e9a03b')}${tree(1350, g + 40, 1.6, '#c4572a', '#e07a34')}${tree(1100, g + 20, 0.8, '#d9a02b', '#e9b84b')}${tree(420, g + 15, 0.7, '#c4572a', '#e07a34')}${label(w, h)}`;
    } },
    markt: { width: 1600, height: 1067, draw(w, h) {
        const g = h * 0.78;
        const stall = (x, c) => `<g><rect x="${x}" y="470" width="300" height="${g - 470}" fill="#f6f1e7"/><polygon points="${x - 20},480 ${x + 320},480 ${x + 290},400 ${x + 10},400" fill="${c}"/>${Array.from({ length: 6 }, (_, i) => `<polygon points="${x - 20 + i * 57},480 ${x + 37 + i * 57},480 ${x + 39 + i * 52},400 ${x + 10 + i * 52},400" fill="#fff" fill-opacity="${i % 2 ? 0 : 0.85}"/>`).join('')}<rect x="${x + 10}" y="600" width="280" height="50" fill="#a8743f"/>${Array.from({ length: 7 }, (_, i) => `<circle cx="${x + 30 + i * 40}" cy="590" r="16" fill="${['#d64545', '#f2a33a', '#7cb342', '#ffd54f'][i % 4]}"/>`).join('')}</g>`;
        return `${sky(w, h)}${cloud(800, 150, 1.4)}
        <rect x="0" y="250" width="${w}" height="${g - 250}" fill="#ece3d2"/>${Array.from({ length: 9 }, (_, i) => `<rect x="${60 + i * 180}" y="290" width="70" height="90" fill="#9cc4ea"/>`).join('')}
        <rect y="240" width="${w}" height="20" fill="#8b4a3a"/>
        ${stall(120, '#2f73c9')}${stall(640, '#3f7d4e')}${stall(1160, '#c62828')}
        <rect y="${g}" width="${w}" height="${h - g}" fill="#c9c3b4"/>${label(w, h)}`;
    } },
    radweg: { width: 2400, height: 1000, draw(w, h) {
        const g = h * 0.55;
        return `${sky(w, h, '#7cb6f2', '#eef6ff')}${cloud(400, 150, 1.4)}${cloud(1500, 120, 1.1)}${cloud(2100, 200, 1)}
        ${hills(w, h, g - 30, '#8fbe7c', 40)}${hills(w, h, g + 20, '#a6c96a', 25)}
        <path d="M ${w * 0.1} ${h} C ${w * 0.4} ${h * 0.8}, ${w * 0.5} ${g + 60}, ${w * 0.9} ${g + 40}" stroke="#d8cdb6" stroke-width="70" fill="none"/>
        ${tree(300, g + 30, 1.2)}${tree(900, g + 10, 0.9)}${tree(1800, g + 20, 1.1, '#356f45', '#468a58')}${tree(2200, g + 30, 1.4)}
        <g transform="translate(1250 ${g + 120})"><circle cx="0" cy="0" r="38" fill="none" stroke="#16202e" stroke-width="8"/><circle cx="110" cy="0" r="38" fill="none" stroke="#16202e" stroke-width="8"/><path d="M0 0 L 45 -60 L 110 0 M 45 -60 L 95 -60" stroke="#c62828" stroke-width="10" fill="none"/><circle cx="60" cy="-150" r="20" fill="#16202e"/><path d="M60 -130 L 55 -70 L 85 -62" stroke="#2f73c9" stroke-width="18" fill="none" stroke-linecap="round"/></g>${label(w, h)}`;
    } },
    winter: { width: 640, height: 480, draw(w, h) {
        const g = h * 0.7;
        return `${sky(w, h, '#a9c8e6', '#f2f6fa')}${hills(w, h, g, '#ffffff', 20)}
        <rect x="220" y="${g - 140}" width="200" height="140" fill="#e9d8c3"/><polygon points="200,${g - 140} 320,${g - 220} 440,${g - 140}" fill="#f4f7fb" stroke="#c9d3de" stroke-width="4"/><rect x="300" y="${g - 70}" width="40" height="70" fill="#6b4f36"/>
        ${Array.from({ length: 40 }, (_, i) => `<circle cx="${(i * 71) % w}" cy="${(i * 37) % g}" r="3" fill="#fff"/>`).join('')}${label(w, h)}`;
    } },
};

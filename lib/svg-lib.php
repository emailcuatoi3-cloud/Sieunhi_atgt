<?php
declare(strict_types=1);

function svg_art_codes(): array {
    return ['den-tin-hieu','bien-bao','mu-bao-hiem','qua-duong','xe-dap','ngoi-xe','uu-tien',
            'bao-tang','cong-vien','vui-choi','thien-nhien','map-bmt'];
}

function svg_art(string $code): ?string {
    $arts = [
        'den-tin-hieu' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<rect x="82" y="14" width="36" height="92" rx="12" fill="#4B3325"/>'
            . '<circle cx="100" cy="34" r="11" fill="#E63946"/>'
            . '<circle cx="100" cy="60" r="11" fill="#FFB703"/>'
            . '<circle cx="100" cy="86" r="11" fill="#2A9D34"/>'
            . '<rect x="95" y="106" width="10" height="22" fill="#4B3325"/>'
            . '<ellipse cx="100" cy="130" rx="34" ry="6" fill="#E9D9B8"/></svg>',

        'mu-bao-hiem' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<path d="M52 82 Q52 34 100 34 Q148 34 148 82 Z" fill="#219EBC"/>'
            . '<path d="M52 82 Q52 34 100 34 Q100 34 100 82 Z" fill="#7BD3EA" opacity=".55"/>'
            . '<rect x="44" y="80" width="112" height="14" rx="7" fill="#126782"/>'
            . '<path d="M74 94 Q78 116 96 118" stroke="#4B3325" stroke-width="5" fill="none" stroke-linecap="round"/>'
            . '<path d="M126 94 Q122 116 104 118" stroke="#4B3325" stroke-width="5" fill="none" stroke-linecap="round"/>'
            . '<circle cx="100" cy="119" r="6" fill="#FFB703"/></svg>',

        'bien-bao' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<ellipse cx="100" cy="134" rx="32" ry="6" fill="#E9D9B8"/>'
            . '<rect x="94" y="50" width="12" height="82" rx="4" fill="#4B3325"/>'
            . '<rect x="38" y="46" width="124" height="8" rx="4" fill="#4B3325"/>'
            . '<circle cx="50" cy="28" r="20" fill="#FFFFFF" stroke="#E63946" stroke-width="6"/>'
            . '<line x1="38" y1="40" x2="62" y2="16" stroke="#E63946" stroke-width="5" stroke-linecap="round"/>'
            . '<path d="M100 6 L122 46 L78 46 Z" fill="#FFB703" stroke="#E63946" stroke-width="6" stroke-linejoin="round"/>'
            . '<rect x="132" y="8" width="38" height="38" rx="6" fill="#219EBC" stroke="#FFFFFF" stroke-width="4"/></svg>',

        'qua-duong' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<rect x="0" y="58" width="200" height="46" fill="#8B7965"/>'
            . '<rect x="18" y="58" width="16" height="46" rx="3" fill="#FFFFFF"/>'
            . '<rect x="52" y="58" width="16" height="46" rx="3" fill="#FFFFFF"/>'
            . '<rect x="86" y="58" width="16" height="46" rx="3" fill="#FFFFFF"/>'
            . '<rect x="120" y="58" width="16" height="46" rx="3" fill="#FFFFFF"/>'
            . '<rect x="154" y="58" width="16" height="46" rx="3" fill="#FFFFFF"/>'
            . '<rect x="14" y="12" width="32" height="42" rx="8" fill="#4B3325"/>'
            . '<circle cx="30" cy="26" r="6" fill="#2A9D34"/>'
            . '<path d="M22 48 Q30 32 38 48 Z" fill="#2A9D34"/>'
            . '<path d="M150 70 L172 70 L172 62 L184 78 L172 94 L172 86 L150 86 Z" fill="#FFB703" stroke="#4B3325" stroke-width="3" stroke-linejoin="round"/></svg>',

        'xe-dap' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<circle cx="55" cy="102" r="26" fill="none" stroke="#4B3325" stroke-width="6"/>'
            . '<circle cx="55" cy="102" r="5" fill="#4B3325"/>'
            . '<circle cx="148" cy="102" r="26" fill="none" stroke="#4B3325" stroke-width="6"/>'
            . '<circle cx="148" cy="102" r="5" fill="#4B3325"/>'
            . '<path d="M55 102 L88 48 L128 52 L148 102 M88 48 L104 102 M104 102 L55 102" fill="none" stroke="#219EBC" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<rect x="80" y="38" width="18" height="8" rx="4" fill="#4B3325"/>'
            . '<path d="M128 52 L142 40" stroke="#4B3325" stroke-width="6" stroke-linecap="round"/>'
            . '<circle cx="104" cy="102" r="6" fill="#4B3325"/>'
            . '<path d="M132 32 Q142 16 158 26 Q166 34 158 44 Q146 50 134 42 Z" fill="#FFB703" stroke="#4B3325" stroke-width="4"/>'
            . '<path d="M142 40 L140 32" stroke="#4B3325" stroke-width="3" stroke-linecap="round"/></svg>',

        'ngoi-xe' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<rect x="66" y="10" width="60" height="30" rx="14" fill="#219EBC"/>'
            . '<rect x="46" y="34" width="100" height="72" rx="18" fill="#219EBC"/>'
            . '<rect x="36" y="96" width="120" height="30" rx="14" fill="#126782"/>'
            . '<path d="M60 24 L146 116" stroke="#E63946" stroke-width="16" stroke-linecap="round"/>'
            . '<circle cx="103" cy="108" r="13" fill="#FFB703" stroke="#4B3325" stroke-width="4"/></svg>',

        'uu-tien' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<line x1="8" y1="60" x2="34" y2="60" stroke="#4B3325" stroke-width="5" stroke-linecap="round"/>'
            . '<line x1="4" y1="72" x2="30" y2="72" stroke="#4B3325" stroke-width="5" stroke-linecap="round"/>'
            . '<line x1="8" y1="84" x2="34" y2="84" stroke="#4B3325" stroke-width="5" stroke-linecap="round"/>'
            . '<rect x="52" y="56" width="106" height="46" rx="10" fill="#FFFFFF" stroke="#4B3325" stroke-width="4"/>'
            . '<path d="M152 58 L176 74 L176 100 L152 100 Z" fill="#FFFFFF" stroke="#4B3325" stroke-width="4" stroke-linejoin="round"/>'
            . '<rect x="158" y="72" width="14" height="14" rx="3" fill="#219EBC"/>'
            . '<rect x="99" y="66" width="8" height="26" fill="#E63946"/>'
            . '<rect x="88" y="75" width="30" height="8" fill="#E63946"/>'
            . '<rect x="92" y="46" width="38" height="12" rx="4" fill="#4B3325"/>'
            . '<circle cx="102" cy="46" r="6" fill="#E63946"/>'
            . '<circle cx="120" cy="46" r="6" fill="#219EBC"/>'
            . '<circle cx="82" cy="104" r="14" fill="#4B3325"/>'
            . '<circle cx="82" cy="104" r="5" fill="#FFF1D6"/>'
            . '<circle cx="140" cy="104" r="14" fill="#4B3325"/>'
            . '<circle cx="140" cy="104" r="5" fill="#FFF1D6"/></svg>',

        'bao-tang' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<path d="M18 52 L100 14 L182 52 L164 52 L100 26 L36 52 Z" fill="#8B5A34"/>'
            . '<rect x="34" y="52" width="132" height="16" rx="4" fill="#E9D9B8" stroke="#4B3325" stroke-width="3"/>'
            . '<rect x="44" y="68" width="8" height="40" fill="#4B3325"/>'
            . '<rect x="76" y="68" width="8" height="40" fill="#4B3325"/>'
            . '<rect x="116" y="68" width="8" height="40" fill="#4B3325"/>'
            . '<rect x="148" y="68" width="8" height="40" fill="#4B3325"/>'
            . '<ellipse cx="100" cy="112" rx="70" ry="6" fill="#E9D9B8"/>'
            . '<rect x="92" y="54" width="16" height="14" fill="#4B3325"/>'
            . '<path d="M60 68 L60 108 M64 108 L64 68 M60 76 L64 76 M60 88 L64 88 M60 100 L64 100" stroke="#4B3325" stroke-width="3" stroke-linecap="round"/>'
            . '<circle cx="132" cy="60" r="9" fill="#FFB703" stroke="#4B3325" stroke-width="3"/></svg>',

        'cong-vien' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<circle cx="172" cy="28" r="16" fill="#FFB703"/>'
            . '<line x1="172" y1="8" x2="172" y2="2" stroke="#FFB703" stroke-width="4" stroke-linecap="round"/>'
            . '<line x1="192" y1="28" x2="197" y2="28" stroke="#FFB703" stroke-width="4" stroke-linecap="round"/>'
            . '<line x1="185" y1="15" x2="190" y2="9" stroke="#FFB703" stroke-width="4" stroke-linecap="round"/>'
            . '<line x1="185" y1="41" x2="190" y2="47" stroke="#FFB703" stroke-width="4" stroke-linecap="round"/>'
            . '<rect x="44" y="88" width="10" height="34" fill="#4B3325"/>'
            . '<circle cx="34" cy="66" r="18" fill="#4CB94F"/>'
            . '<circle cx="66" cy="66" r="18" fill="#4CB94F"/>'
            . '<circle cx="49" cy="52" r="26" fill="#2A9D34"/>'
            . '<rect x="118" y="94" width="8" height="26" fill="#4B3325"/>'
            . '<circle cx="110" cy="76" r="14" fill="#4CB94F"/>'
            . '<circle cx="132" cy="76" r="14" fill="#4CB94F"/>'
            . '<circle cx="121" cy="66" r="20" fill="#2A9D34"/>'
            . '<rect x="70" y="106" width="60" height="8" rx="3" fill="#4B3325"/>'
            . '<rect x="76" y="122" width="6" height="14" fill="#4B3325"/>'
            . '<rect x="118" y="122" width="6" height="14" fill="#4B3325"/></svg>',

        'vui-choi' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<ellipse cx="100" cy="132" rx="50" ry="6" fill="#E9D9B8"/>'
            . '<rect x="96" y="30" width="8" height="94" rx="3" fill="#4B3325"/>'
            . '<circle cx="100" cy="28" r="10" fill="#4B3325"/>'
            . '<path d="M100 28 L55 46 M100 28 L100 52 M100 28 L145 46" stroke="#4B3325" stroke-width="4" stroke-linecap="round" fill="none"/>'
            . '<path d="M55 46 L48 80 M100 52 L100 90 M145 46 L152 80" stroke="#4B3325" stroke-width="3" stroke-linecap="round" fill="none"/>'
            . '<rect x="38" y="80" width="20" height="14" rx="5" fill="#FFB703"/>'
            . '<rect x="90" y="90" width="20" height="14" rx="5" fill="#E63946"/>'
            . '<rect x="142" y="80" width="20" height="14" rx="5" fill="#219EBC"/></svg>',

        'thien-nhien' => '<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="200" height="140" rx="18" fill="#FFF1D6"/>'
            . '<ellipse cx="45" cy="28" rx="26" ry="12" fill="#FFFFFF"/>'
            . '<circle cx="34" cy="20" r="12" fill="#FFFFFF"/>'
            . '<circle cx="52" cy="16" r="14" fill="#FFFFFF"/>'
            . '<circle cx="66" cy="22" r="10" fill="#FFFFFF"/>'
            . '<ellipse cx="155" cy="18" rx="18" ry="8" fill="#FFFFFF"/>'
            . '<circle cx="148" cy="14" r="8" fill="#FFFFFF"/>'
            . '<circle cx="162" cy="12" r="9" fill="#FFFFFF"/>'
            . '<path d="M108 32 L116 24 L124 32" stroke="#4B3325" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M136 42 L142 37 L148 42" stroke="#4B3325" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<path d="M-5 95 L45 35 L85 75 L125 25 L175 70 L205 95 Z" fill="#4CB94F"/>'
            . '<path d="M-5 108 L35 62 L75 100 L115 58 L155 100 L205 108 Z" fill="#2A9D34"/>'
            . '<ellipse cx="100" cy="120" rx="90" ry="18" fill="#219EBC"/>'
            . '<path d="M40 116 Q60 110 80 116" stroke="#7BD3EA" stroke-width="3" fill="none" stroke-linecap="round"/>'
            . '<path d="M110 122 Q130 116 150 122" stroke="#7BD3EA" stroke-width="3" fill="none" stroke-linecap="round"/></svg>',

        'map-bmt' => '<svg viewBox="0 0 600 420" xmlns="http://www.w3.org/2000/svg">'
            . '<rect width="600" height="420" rx="40" fill="#FFF8EC"/>'
            . '<rect x="40" y="40" width="100" height="70" rx="16" fill="#FFE8BF"/>'
            . '<rect x="160" y="30" width="80" height="60" rx="16" fill="#FFE8BF"/>'
            . '<rect x="60" y="130" width="90" height="60" rx="16" fill="#FFE8BF"/>'
            . '<rect x="340" y="40" width="110" height="70" rx="16" fill="#FFE8BF"/>'
            . '<rect x="470" y="60" width="90" height="80" rx="16" fill="#FFE8BF"/>'
            . '<rect x="360" y="140" width="80" height="50" rx="16" fill="#FFE8BF"/>'
            . '<rect x="40" y="250" width="90" height="70" rx="16" fill="#FFE8BF"/>'
            . '<rect x="150" y="280" width="100" height="80" rx="16" fill="#FFE8BF"/>'
            . '<rect x="40" y="340" width="80" height="60" rx="16" fill="#FFE8BF"/>'
            . '<rect x="470" y="250" width="80" height="60" rx="16" fill="#FFE8BF"/>'
            . '<path d="M-10 30 Q60 10 100 60 Q145 105 90 135 Q35 160 60 195 L20 195 Q0 150 45 120 Q90 95 55 60 Q25 30 -10 55 Z" fill="#7BD3EA"/>'
            . '<path d="M385 255 Q425 225 475 235 Q545 232 575 280 Q595 332 552 372 Q498 402 438 388 Q388 372 372 322 Q362 285 385 255 Z" fill="#2A9D34"/>'
            . '<rect x="0" y="190" width="600" height="40" fill="#FFFFFF" stroke="#4B3325" stroke-width="4"/>'
            . '<rect x="280" y="0" width="40" height="420" fill="#FFFFFF" stroke="#4B3325" stroke-width="4"/>'
            . '<circle cx="300" cy="210" r="55" fill="#FFE8BF" stroke="#4B3325" stroke-width="5"/>'
            . '<circle cx="300" cy="210" r="16" fill="#FFB703" stroke="#4B3325" stroke-width="3"/></svg>',
    ];
    return $arts[$code] ?? null;
}

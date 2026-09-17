<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Inline SVG icon set (no icon-font download, no external requests).
 * Icons are 24x24 stroke icons unless noted.
 */
final class Icon
{
    private const PATHS = [
        // ------------------------------------------------ Trade motifs --
        // Industry marks. A card that shows a fork over a warm gradient is
        // read as a restaurant before a word of it is scanned, which is what
        // a visiting card is for. Drawn on the same 24x24 stroke grid as the
        // interface icons so they sit consistently.
        'utensils'    => '<path d="M4 2v7a3 3 0 0 0 3 3v10"/><path d="M7 2v7"/><path d="M10 2v7a3 3 0 0 1-3 3"/><path d="M17 2c-1.7 1.4-2.5 3.3-2.5 5.5 0 2.2.8 4.1 2.5 5.5v9"/>',
        'chef'        => '<path d="M6 13a4 4 0 1 1 1.6-7.7 4.5 4.5 0 0 1 8.8 0A4 4 0 1 1 18 13z"/><path d="M6 13v6a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-6"/><path d="M9 17h6"/>',
        'cup'         => '<path d="M4 8h13v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z"/><path d="M17 9h2a2 2 0 0 1 0 5h-2"/><path d="M5 21h12"/>',
        'scissors'    => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.1" y2="15.9"/><line x1="14.5" y1="14.5" x2="20" y2="20"/><line x1="8.1" y1="8.1" x2="12" y2="12"/>',
        'stethoscope' => '<path d="M5 3v5a4 4 0 0 0 8 0V3"/><path d="M5 3H3"/><path d="M13 3h2"/><path d="M9 12v3a5 5 0 0 0 5 5h1"/><circle cx="18" cy="19" r="3"/>',
        'tooth'       => '<path d="M12 3c-2 0-3 1-4.5 1S5 3.2 4 4.5C2.8 6 3 8.5 3.6 11c.6 2.4.9 4.6 1.2 6.6.2 1.6.6 3.4 1.9 3.4 1.2 0 1.6-1.6 1.9-3.2.3-1.6.6-3.3 1.4-3.3s1.1 1.7 1.4 3.3c.3 1.6.7 3.2 1.9 3.2 1.3 0 1.7-1.8 1.9-3.4.3-2 .6-4.2 1.2-6.6.6-2.5.8-5-.4-6.5C17.5 3.2 16.5 4 15 4s-1-1-3-1z"/>',
        'camera'      => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'cctv'        => '<path d="M3 7l14-4 2 6-14 4z"/><path d="M5.5 13.5 4 19"/><path d="M2 19h6"/><path d="M17 9l4 1.5a2 2 0 0 1 1 2.5"/>',
        'monitor'     => '<rect x="2" y="3" width="20" height="13" rx="2"/><path d="M8 21h8"/><path d="M12 16v5"/>',
        'wrench'      => '<path d="M14.7 6.3a4 4 0 1 0 5 5L21 21l-2 2-9.7-9.7a4 4 0 0 1-5-5z"/>',
        'hammer'      => '<path d="M14 5 9 10l-6 6 3 3 6-6 5-5"/><path d="M13 4l4-2 5 5-2 4z"/>',
        'droplet'     => '<path d="M12 2.7 6.5 9a7.5 7.5 0 1 0 11 0z"/>',
        'paint'       => '<rect x="3" y="3" width="18" height="6" rx="2"/><path d="M12 9v4a2 2 0 0 0 2 2h1v6h-6v-6h1a2 2 0 0 0 2-2"/>',
        'brick'       => '<rect x="2" y="4" width="20" height="5"/><rect x="2" y="10" width="20" height="5"/><rect x="2" y="16" width="20" height="5"/><path d="M8 4v5M16 10v5M8 16v5"/>',
        'graduation'  => '<path d="M22 9 12 4 2 9l10 5z"/><path d="M6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/>',
        'scale'       => '<path d="M12 3v18"/><path d="M6 21h12"/><path d="M4 8h16"/><path d="M4 8 2 14h4z"/><path d="M20 8l-2 6h4z"/>',
        'chart-up'    => '<path d="M3 21h18"/><polyline points="4 16 9 11 13 15 20 8"/><polyline points="20 12 20 8 16 8"/>',
        'car'         => '<path d="M5 17h14"/><path d="M3 12h18l-2-5H5z"/><path d="M3 12v4h18v-4"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/>',
        'truck'       => '<path d="M1 7h12v9H1z"/><path d="M13 10h5l3 3v3h-8z"/><circle cx="5" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'plane'       => '<path d="M2 13l20-7-7 20-3-8z"/><path d="M12 18l-2 4"/>',
        'bed'         => '<path d="M2 18V9h10a5 5 0 0 1 5 5v4"/><path d="M2 18h20"/><path d="M17 14h5v4"/><circle cx="7" cy="12" r="2"/>',
        'shopping'    => '<path d="M6 8h12l-1 12H7z"/><path d="M9 8V5a3 3 0 0 1 6 0v3"/>',
        'shirt'       => '<path d="M8 3 4 6v5h3v10h10V11h3V6l-4-3-4 3z"/>',
        'gem'         => '<path d="M6 3h12l3 6-9 12L3 9z"/><path d="M3 9h18"/><path d="M9 3l3 6 3-6"/>',
        'ring'        => '<circle cx="12" cy="15" r="6"/><path d="M9 6h6l-3 3z"/><path d="M9 6l3-3 3 3"/>',
        'dumbbell'    => '<path d="M6 8v8"/><path d="M18 8v8"/><path d="M3 10v4"/><path d="M21 10v4"/><path d="M6 12h12"/>',
        'flower'      => '<circle cx="12" cy="9" r="2.5"/><path d="M12 6.5a2.5 2.5 0 1 1 0-1"/><path d="M12 9c-3 0-5-1.5-5-3.5S9 3 12 3s5 .5 5 2.5S15 9 12 9z"/><path d="M12 11v10"/><path d="M12 16c2 0 4-1 5-3"/>',
        'leaf'        => '<path d="M20 4C10 4 4 9 4 17c0 1 .2 2 .5 3C7 13 13 9 20 9z"/><path d="M4.5 20C8 14 13 11 20 10"/>',
        'palette'     => '<path d="M12 3a9 9 0 1 0 0 18c1.5 0 2-1 2-2s-.7-2-.7-3 .8-2 2.2-2H19a3 3 0 0 0 3-3 9 9 0 0 0-10-8z"/><circle cx="8" cy="9" r="1"/><circle cx="12" cy="7" r="1"/><circle cx="7" cy="14" r="1"/>',
        'mic'         => '<rect x="9" y="2" width="6" height="11" rx="3"/><path d="M6 11a6 6 0 0 0 12 0"/><path d="M12 17v4"/><path d="M9 21h6"/>',
        'book'        => '<path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4z"/><path d="M20 4h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6z"/>',
        'printer'     => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 16h10v5H7z"/>',
        'pill'        => '<path d="M10.5 3.5a5 5 0 0 1 7 7l-7 7a5 5 0 0 1-7-7z"/><path d="M7 7l10 10"/>',
        'paw'         => '<circle cx="7" cy="8" r="2"/><circle cx="12" cy="6" r="2"/><circle cx="17" cy="8" r="2"/><path d="M12 11c-3 0-5 2-5 4.5S9 21 12 21s5-3 5-5.5S15 11 12 11z"/>',
        'diya'        => '<path d="M4 15h16a8 8 0 0 1-16 0z"/><path d="M12 12c1.5-1 2-2 2-3.5S13 6 12 5c-1 1-2 2-2 3.5S10.5 11 12 11z"/><path d="M6 19h12"/>',
        'temple'      => '<path d="M12 2 4 8h16z"/><path d="M6 8v11"/><path d="M18 8v11"/><path d="M3 19h18"/><path d="M10 19v-6h4v6"/>',
        'crop'        => '<path d="M4 20 20 4"/><path d="M8 4h8a4 4 0 0 1 4 4v8"/><circle cx="6" cy="6" r="2"/><circle cx="18" cy="18" r="2"/>',
        'bolt-wire'   => '<path d="M13 2 6 13h5l-1 9 8-12h-5z"/>',
        'briefcase-md'=> '<rect x="2" y="7" width="20" height="13" rx="2"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/><path d="M2 13h20"/>',

        'phone'       => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'whatsapp'    => '<path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.64-2.05-.17-.3-.02-.46.13-.61.14-.14.3-.35.45-.53.15-.18.2-.3.3-.5.1-.2.05-.38-.02-.53-.08-.15-.67-1.6-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.38-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.22 3.08c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.09 1.75-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35z"/><path d="M20.52 3.48A11.9 11.9 0 0 0 12.05 0C5.5 0 .16 5.33.16 11.89c0 2.1.55 4.14 1.6 5.95L0 24l6.3-1.65a11.86 11.86 0 0 0 5.74 1.46h.01c6.55 0 11.89-5.33 11.89-11.89 0-3.18-1.24-6.17-3.42-8.44zM12.05 21.8h-.01a9.86 9.86 0 0 1-5.03-1.38l-.36-.21-3.74.98 1-3.64-.24-.37a9.86 9.86 0 0 1-1.51-5.29c0-5.45 4.44-9.89 9.9-9.89 2.64 0 5.12 1.03 6.99 2.9a9.82 9.82 0 0 1 2.89 6.99c0 5.45-4.44 9.9-9.89 9.9z"/>',
        'mail'        => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'globe'       => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'map-pin'     => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'navigation'  => '<polygon points="3 11 22 2 13 21 11 13 3 11"/>',
        'clock'       => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'share'       => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
        'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'user-plus'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
        'qr'          => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM18 18h3v3h-3zM14 20h2M20 14h1"/>',
        'eye'         => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'edit'        => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash'       => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'plus'        => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'check'       => '<polyline points="20 6 9 17 4 12"/>',
        'check-circle'=> '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'x'           => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'alert'       => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'info'        => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'grid'        => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'layers'      => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'credit-card' => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'bar-chart'   => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'log-out'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu'        => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'search'      => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'image'       => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'video'       => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
        'play'        => '<polygon points="5 3 19 12 5 21 5 3"/>',
        'package'     => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/>',
        'briefcase'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'award'       => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
        'zap'         => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'home'        => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'refresh'     => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'database'    => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'file-text'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'inbox'       => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'wallet'      => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
        'chevron-right'=> '<polyline points="9 18 15 12 9 6"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'arrow-left'  => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'external'    => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'copy'        => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'bell'        => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'upload'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'save'        => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'rupee'       => '<path d="M6 3h12M6 8h12M6 13h5a5 5 0 0 0 0-10"/><path d="M6 13l8 8"/>',
        'sun'         => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'moon'        => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'facebook'    => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'instagram'   => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>',
        'youtube'     => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/>',
        'linkedin'    => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
        'twitter'     => '<path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>',
        'telegram'    => '<path d="M21.5 4.5L2.8 11.3c-.9.3-.9 1.5.1 1.8l4.6 1.4 1.7 5.4c.3.8 1.3 1 1.9.4l2.6-2.5 4.7 3.4c.7.5 1.7.1 1.9-.8l3-14.2c.2-1-.8-1.8-1.8-1.7z"/>',
        'pinterest'   => '<circle cx="12" cy="12" r="10"/><path d="M8 19c1.5-3 2-5.5 2.2-7 .3-1.8 1.4-3 3-3 1.8 0 3 1.4 3 3.3 0 2-1.2 3.7-2.8 3.7-.8 0-1.4-.7-1.2-1.5"/>',
        'x-social'    => '<path d="M18.9 2H22l-7 8 8.2 12h-6.4l-5-7.3L5.9 22H2.8l7.5-8.6L2.4 2h6.6l4.5 6.7z"/>',
        'google'      => '<path d="M21.35 11.1H12v2.9h5.35c-.25 1.35-1.85 3.95-5.35 3.95A6 6 0 1 1 12 6a5.3 5.3 0 0 1 3.75 1.45l2.05-2.05A8.9 8.9 0 1 0 12 21c5.15 0 8.55-3.6 8.55-8.7 0-.6-.05-1-.2-1.2z"/>',
        'threads'     => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm2.7 13.6c-.9.7-2.2.9-3.4.6-1.4-.4-2.3-1.5-2.2-2.7.1-1.4 1.5-2.3 3.3-2.1 1 .1 1.8.4 2.4.8"/>',
        'snapchat'    => '<path d="M12 2c3 0 5 2.2 5 5v2.4c.6.2 1.3 0 1.8-.2.6-.2 1.2.4.9 1-.3.7-1.2 1.2-2 1.5.4 1.4 1.7 2.9 3.3 3.3.5.1.6.8.1 1-1 .5-2 .7-2.6.8-.2.5-.2 1.1-.8 1.1-.7 0-1.5-.3-2.5-.1-.9.2-1.7 1.2-3.2 1.2s-2.3-1-3.2-1.2c-1-.2-1.8.1-2.5.1-.6 0-.6-.6-.8-1.1-.6-.1-1.6-.3-2.6-.8-.5-.2-.4-.9.1-1 1.6-.4 2.9-1.9 3.3-3.3-.8-.3-1.7-.8-2-1.5-.3-.6.3-1.2.9-1 .5.2 1.2.4 1.8.2V7c0-2.8 2-5 5-5z"/>',
        'layout'      => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
        'sparkles'    => '<path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9L12 3z"/><path d="M19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2z"/>',
        'rocket'      => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91 0z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
        'link'        => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'activity'    => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'clipboard'   => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/>',
        'git'         => '<circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><line x1="6" y1="9" x2="6" y2="21"/>',
        'archive'     => '<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>',
        'folder'      => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'tag'         => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'star'        => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'lock'        => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'key'         => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3"/>',
    ];

    /** Whether the set contains this icon. */
    public static function has(string $name): bool
    {
        return array_key_exists($name, self::PATHS);
    }

    public static function render(string $name, int $size = 20, string $class = '', string $stroke = '2'): string
    {
        $path = self::PATHS[$name] ?? self::PATHS['info'];
        $filled = in_array($name, ['whatsapp', 'facebook', 'telegram', 'google', 'x-social', 'snapchat', 'threads'], true);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="%s" stroke="%s" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round" class="%s" aria-hidden="true" focusable="false">%s</svg>',
            $size,
            $size,
            $filled ? 'currentColor' : 'none',
            $filled ? 'none' : 'currentColor',
            $stroke,
            htmlspecialchars($class, ENT_QUOTES),
            $path
        );
    }

    public static function exists(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }

    /** @return array<int,string> */
    public static function names(): array
    {
        return array_keys(self::PATHS);
    }
}

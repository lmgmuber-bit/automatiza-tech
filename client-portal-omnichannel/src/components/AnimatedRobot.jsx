import { useMemo } from 'react';

export default function AnimatedRobot({ state = 'waving' }) {
  const eyeAnim = useMemo(() => {
    if (state === 'thinking') return 'robot-eyes-scan';
    if (state === 'celebrating') return 'robot-eyes-happy';
    return 'robot-eyes-blink';
  }, [state]);

  return (
    <svg
      viewBox="0 0 220 250"
      width="150"
      height="170"
      xmlns="http://www.w3.org/2000/svg"
      className="animated-robot"
      style={{ overflow: 'visible' }}
    >
      <defs>
        {/* Dark navy body */}
        <linearGradient id="bodyDark" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#1e3a5f" />
          <stop offset="100%" stopColor="#0f2340" />
        </linearGradient>
        {/* Head shell */}
        <linearGradient id="headShell" x1="0.2" y1="0" x2="0.8" y2="1">
          <stop offset="0%" stopColor="#2a6b7c" />
          <stop offset="40%" stopColor="#1a4a5e" />
          <stop offset="100%" stopColor="#0f2d3d" />
        </linearGradient>
        {/* Visor / face screen */}
        <linearGradient id="visorGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#0a1628" />
          <stop offset="100%" stopColor="#111d2e" />
        </linearGradient>
        {/* Teal accent */}
        <linearGradient id="tealAccent" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#3dd6c5" />
          <stop offset="100%" stopColor="#1ab5a3" />
        </linearGradient>
        {/* Eye glow */}
        <radialGradient id="eyeGlow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#7df3ff" stopOpacity="1" />
          <stop offset="60%" stopColor="#4dd8e8" stopOpacity="0.6" />
          <stop offset="100%" stopColor="#4dd8e8" stopOpacity="0" />
        </radialGradient>
        {/* Chest logo bg */}
        <linearGradient id="chestPanel" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#1a4a5e" />
          <stop offset="100%" stopColor="#0f2d3d" />
        </linearGradient>
        {/* Shine */}
        <linearGradient id="shineHL" x1="0" y1="0" x2="0.5" y2="1">
          <stop offset="0%" stopColor="rgba(255,255,255,0.2)" />
          <stop offset="100%" stopColor="rgba(255,255,255,0)" />
        </linearGradient>
        {/* Shadow filter */}
        <filter id="robotShadow" x="-20%" y="-10%" width="140%" height="130%">
          <feDropShadow dx="0" dy="4" stdDeviation="8" floodColor="#4dd8e8" floodOpacity="0.25" />
        </filter>
        {/* Eye LED glow filter */}
        <filter id="ledGlow" x="-50%" y="-50%" width="200%" height="200%">
          <feGaussianBlur in="SourceGraphic" stdDeviation="3" result="blur" />
          <feMerge>
            <feMergeNode in="blur" />
            <feMergeNode in="SourceGraphic" />
          </feMerge>
        </filter>
        {/* Antenna glow */}
        <radialGradient id="antennaGlow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#4dd8e8" stopOpacity="1" />
          <stop offset="100%" stopColor="#4dd8e8" stopOpacity="0" />
        </radialGradient>
      </defs>

      <g filter="url(#robotShadow)">

        {/* ===== ANTENNA ===== */}
        <g className="robot-antenna">
          <rect x="107" y="10" width="6" height="18" rx="3" fill="#1a4a5e" />
          <circle cx="110" cy="8" r="6" fill="#3dd6c5" className="antenna-ball" />
          <circle cx="110" cy="8" r="11" fill="url(#antennaGlow)" className="antenna-pulse" opacity="0.5" />
        </g>

        {/* ===== HEAD ===== */}
        {/* Head outer shell - rounded like Tech */}
        <rect x="52" y="26" width="116" height="82" rx="32" ry="32" fill="url(#headShell)" />
        {/* Teal top cap */}
        <rect x="68" y="22" width="84" height="20" rx="10" ry="10" fill="url(#tealAccent)" />
        {/* Head shine */}
        <rect x="52" y="26" width="116" height="40" rx="32" ry="32" fill="url(#shineHL)" opacity="0.4" />

        {/* ===== HEADPHONES / EARS ===== */}
        {/* Left headphone */}
        <ellipse cx="50" cy="62" rx="12" ry="16" fill="#0f2d3d" />
        <ellipse cx="50" cy="62" rx="8" ry="12" fill="#1a4a5e" />
        <circle cx="50" cy="62" r="5" fill="#3dd6c5" opacity="0.4" className="robot-core" />
        {/* Right headphone */}
        <ellipse cx="170" cy="62" rx="12" ry="16" fill="#0f2d3d" />
        <ellipse cx="170" cy="62" rx="8" ry="12" fill="#1a4a5e" />
        <circle cx="170" cy="62" r="5" fill="#3dd6c5" opacity="0.4" className="robot-core" />

        {/* ===== VISOR / FACE ===== */}
        <rect x="66" y="38" width="88" height="58" rx="18" ry="18" fill="url(#visorGrad)" />
        {/* Visor reflection */}
        <rect x="70" y="41" width="40" height="10" rx="5" fill="rgba(255,255,255,0.06)" />

        {/* ===== EYES (LED grid style) ===== */}
        <g className={eyeAnim} filter="url(#ledGlow)">
          {/* Left eye */}
          <ellipse cx="92" cy="60" rx="12" ry="13" fill="#4dd8e8" opacity="0.15" />
          <ellipse cx="92" cy="60" rx="10" ry="11" fill="#7df3ff" className="robot-eye-bg" opacity="0.9" />
          <ellipse cx="92" cy="60" rx="6" ry="7" fill="#ffffff" className="robot-pupil" opacity="0.5" />
          {/* Eye grid lines */}
          <line x1="84" y1="57" x2="100" y2="57" stroke="#4dd8e8" strokeWidth="0.5" opacity="0.3" />
          <line x1="84" y1="60" x2="100" y2="60" stroke="#4dd8e8" strokeWidth="0.5" opacity="0.3" />
          <line x1="84" y1="63" x2="100" y2="63" stroke="#4dd8e8" strokeWidth="0.5" opacity="0.3" />

          {/* Right eye */}
          <ellipse cx="128" cy="60" rx="12" ry="13" fill="#4dd8e8" opacity="0.15" />
          <ellipse cx="128" cy="60" rx="10" ry="11" fill="#7df3ff" className="robot-eye-bg" opacity="0.9" />
          <ellipse cx="128" cy="60" rx="6" ry="7" fill="#ffffff" className="robot-pupil" opacity="0.5" />
          {/* Eye grid lines */}
          <line x1="120" y1="57" x2="136" y2="57" stroke="#4dd8e8" strokeWidth="0.5" opacity="0.3" />
          <line x1="120" y1="60" x2="136" y2="60" stroke="#4dd8e8" strokeWidth="0.5" opacity="0.3" />
          <line x1="120" y1="63" x2="136" y2="63" stroke="#4dd8e8" strokeWidth="0.5" opacity="0.3" />
        </g>

        {/* ===== MOUTH / SMILE ===== */}
        {state === 'celebrating' ? (
          <path d="M94 80 Q110 92 126 80" stroke="#4dd8e8" strokeWidth="2.5" fill="none" strokeLinecap="round" filter="url(#ledGlow)" className="robot-mouth-happy" />
        ) : state === 'thinking' ? (
          <ellipse cx="110" cy="82" rx="6" ry="3" fill="#4dd8e8" opacity="0.6" className="robot-mouth-think" />
        ) : (
          <path d="M96 80 Q110 88 124 80" stroke="#4dd8e8" strokeWidth="2" fill="none" strokeLinecap="round" filter="url(#ledGlow)" />
        )}

        {/* ===== NECK ===== */}
        <rect x="98" y="108" width="24" height="12" rx="4" fill="#0f2d3d" />
        {/* Neck rings */}
        <rect x="96" y="110" width="28" height="3" rx="1.5" fill="#1a4a5e" />
        <rect x="96" y="115" width="28" height="3" rx="1.5" fill="#1a4a5e" />

        {/* ===== BODY ===== */}
        <rect x="56" y="118" width="108" height="72" rx="20" ry="20" fill="url(#bodyDark)" />
        {/* Teal shoulder stripes */}
        <rect x="56" y="118" width="108" height="8" rx="4" fill="url(#tealAccent)" opacity="0.6" />
        {/* Body shine */}
        <rect x="56" y="118" width="108" height="35" rx="20" ry="20" fill="url(#shineHL)" opacity="0.3" />

        {/* ===== CHEST PANEL (AT Logo area) ===== */}
        <rect x="82" y="134" width="56" height="42" rx="8" fill="url(#chestPanel)" stroke="#3dd6c5" strokeWidth="1" opacity="0.8" />
        {/* AT text */}
        <text x="110" y="160" textAnchor="middle" fill="#3dd6c5" fontSize="18" fontWeight="700" fontFamily="Arial, sans-serif" className="robot-core">AT</text>
        {/* Small orbit ring around AT */}
        <circle cx="110" cy="156" r="16" fill="none" stroke="#3dd6c5" strokeWidth="0.8" opacity="0.4" className="core-ring" />

        {/* ===== ARMS ===== */}
        {/* Left arm */}
        <g className={`robot-arm-left ${state === 'waving' ? 'arm-wave' : state === 'celebrating' ? 'arm-celebrate' : ''}`}>
          <rect x="30" y="124" width="26" height="16" rx="8" fill="#1a4a5e" />
          <rect x="22" y="128" width="14" height="14" rx="7" fill="#0f2d3d" />
          {/* Hand */}
          <circle cx="24" cy="145" r="8" fill="#1a4a5e" />
          <circle cx="24" cy="145" r="4" fill="#3dd6c5" opacity="0.3" />
        </g>

        {/* Right arm */}
        <g className={`robot-arm-right ${state === 'waving' ? 'arm-wave-right' : state === 'celebrating' ? 'arm-celebrate-right' : ''}`}>
          <rect x="164" y="124" width="26" height="16" rx="8" fill="#1a4a5e" />
          <rect x="184" y="128" width="14" height="14" rx="7" fill="#0f2d3d" />
          {/* Hand */}
          <circle cx="196" cy="145" r="8" fill="#1a4a5e" />
          <circle cx="196" cy="145" r="4" fill="#3dd6c5" opacity="0.3" />
        </g>

        {/* ===== WAIST ACCENT ===== */}
        <rect x="70" y="186" width="80" height="6" rx="3" fill="url(#tealAccent)" opacity="0.5" />

        {/* ===== LEGS ===== */}
        <rect x="80" y="190" width="18" height="24" rx="6" fill="#0f2d3d" />
        <rect x="122" y="190" width="18" height="24" rx="6" fill="#0f2d3d" />
        {/* Knee accents */}
        <circle cx="89" cy="200" r="3" fill="#3dd6c5" opacity="0.3" />
        <circle cx="131" cy="200" r="3" fill="#3dd6c5" opacity="0.3" />
        {/* Feet */}
        <rect x="74" y="210" width="26" height="12" rx="6" fill="#1a4a5e" />
        <rect x="120" y="210" width="26" height="12" rx="6" fill="#1a4a5e" />
        {/* Foot teal line */}
        <rect x="78" y="216" width="18" height="3" rx="1.5" fill="#3dd6c5" opacity="0.3" />
        <rect x="124" y="216" width="18" height="3" rx="1.5" fill="#3dd6c5" opacity="0.3" />
      </g>
    </svg>
  );
}

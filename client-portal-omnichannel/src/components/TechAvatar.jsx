/**
 * TechAvatar — el "Tech" real (asistente IA de AutomatizaTech) con la identidad
 * premium del Home: avatar circular, anillos teal pulsantes, anillo punteado que
 * gira y punto de estado. Reemplaza al robot caricatura en el login.
 * `state`: 'waving' | 'thinking' | 'celebrating' (cambia el glow/pulso).
 */
export default function TechAvatar({ state = 'waving' }) {
  const base = import.meta.env.BASE_URL || '/';
  return (
    <div className={`tech-avatar tech-${state}`} aria-hidden="true">
      <span className="tech-ring tech-ring-a" />
      <span className="tech-ring tech-ring-b" />
      <span className="tech-ring-dashed" />
      <div className="tech-avatar-frame">
        <img src={`${base}tech-avatar.png`} alt="" draggable="false" />
      </div>
      <span className="tech-status-dot" />
    </div>
  );
}

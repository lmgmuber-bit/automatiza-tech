# Decisión: las predicciones pertenecen al evento

**Fecha:** 2026-08-25  
**Estado:** aprobada por Luis e implementada localmente  
**Alcance:** modalidad `baby_shower`

## Contexto

El plan inicial dibujaba `cc_predictions.invitation_id`. Al contrastarlo con el
flujo real apareció una incompatibilidad: la cabina se abre por
`cc_parties.public_slug`, no por un token de invitación, y un mismo evento puede
tener varias invitaciones. Elegir una de ellas desde el kiosco sería arbitrario y
podría fragmentar el tablero de una sola fiesta.

## Decisión

- `cc_predictions` pertenece a `cc_parties` mediante `party_id`.
- El endpoint público recibe el slug, vuelve a resolver la fiesta activa en el
  servidor y persiste su id interno. Nunca acepta un `party_id` aportado por el
  navegador.
- El tablero de los papás se abre con un token opaco de
  `cc_invitation_tokens`. El token resuelve primero su invitación y luego la
  fiesta, y muestra todas las predicciones de ese `party_id`.
- `cc_gift_items` y `cc_invitation_tokens` sí continúan perteneciendo a la
  invitación, porque la lista y sus permisos forman parte de esa experiencia
  pública concreta.
- La retención elimina explícitamente `cc_predictions` antes de anonimizar la
  fiesta. La FK también declara `ON DELETE CASCADE` como segunda barrera.

## Por qué es mejor

Evita inventar una invitación canónica para la cabina, no mezcla datos entre
eventos y permite emitir o revocar varios enlaces de papás sin duplicar el
tablero. También mantiene la navegación actual de cumpleaños infantiles sin
cambios: la rama baby shower se activa únicamente por `event_type`.

## Alternativa descartada

Guardar `invitation_id` habría seguido literalmente el primer borrador del plan,
pero obliga a cambiar el contrato del kiosco o a elegir una invitación al azar.
Si esa elección cambia, las predicciones del mismo baby shower quedan divididas.

## Consecuencias

- Una predicción representa una apuesta hecha durante un evento, no durante la
  apertura de una invitación.
- Revocar un enlace de papás no borra apuestas; sólo corta ese acceso.
- Anonimizar el evento sí elimina las apuestas, de acuerdo con la política de
  retención.
- Si en el futuro se necesitan encuestas que nazcan dentro de una invitación,
  deben modelarse como otra entidad o añadir una referencia opcional; no se debe
  reinterpretar `party_id`.

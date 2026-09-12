export const PERSONAJES = ['heroe', 'amiga', 'amigo'];
export const ROLES = { heroe: 'El héroe', amiga: 'La amiga', amigo: 'El amigo' };
export const personajeValido = id => PERSONAJES.includes(id) ? id : 'heroe';

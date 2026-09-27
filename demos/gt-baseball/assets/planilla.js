/* Planilla de inscripción en PDF (A4), dibujada con jsPDF a partir de los datos del formulario.
 * Sigue la planilla de papel de la academia: foto arriba a la izquierda, logo a la derecha, campos con
 * línea y firma en blanco para firmar a mano al imprimir.
 * Uso: GTPlanilla.crear({ datos, edad, foto, logo, id, fecha }) → documento jsPDF. */
(function (global) {
  'use strict';

  var ORO = [214, 142, 12];
  var GRIS = [110, 116, 124];
  var TINTA = [20, 22, 26];
  var M = 15; // margen
  var ANCHO = 210 - M * 2;

  // La fuente estándar del PDF (WinAnsi) no dibuja emojis ni la mayoría de los caracteres fuera del latín:
  // saldrían como símbolos rotos. Se quitan antes de escribir; el correo y el Sheet guardan el texto original.
  var WINANSI_EXTRA = '€‚ƒ„…†‡ˆ‰Š‹ŒŽ‘’“”•–—˜™š›œžŸ';
  function limpiar(s) {
    var t = String(s == null ? '' : s).normalize ? String(s == null ? '' : s).normalize('NFC') : String(s == null ? '' : s);
    var out = '';
    for (var i = 0; i < t.length; i++) {
      var c = t.charAt(i), code = t.charCodeAt(i);
      if ((code >= 32 && code <= 126) || (code >= 160 && code <= 255) || WINANSI_EXTRA.indexOf(c) !== -1) out += c;
    }
    return out.replace(/\s+/g, ' ').trim();
  }

  // El CDN puede entregar WebP aunque el archivo sea .jpg: el formato sale del data URL, no del nombre.
  function formato(dataUrl) {
    var m = /^data:image\/(png|webp|jpe?g)/i.exec(dataUrl || '');
    if (!m) return 'JPEG';
    var f = m[1].toUpperCase();
    return f === 'JPG' ? 'JPEG' : f;
  }

  function crear(o) {
    var jsPDF = global.jspdf && global.jspdf.jsPDF;
    if (!jsPDF) throw new Error('jsPDF no cargó');
    var d = {};
    Object.keys(o.datos).forEach(function (k) { d[k] = limpiar(o.datos[k]); });
    var doc = new jsPDF({ unit: 'mm', format: 'a4', compress: true });
    doc.setProperties({ title: 'Planilla de inscripción - ' + d.nombre, author: 'GT Baseball Academy', creator: 'AutomatizaTech' });

    // Encabezado: foto, título y logo
    var fotoW = 30, fotoH = 40, y0 = 12;
    doc.setDrawColor.apply(doc, ORO); doc.setLineWidth(0.6);
    if (o.foto) doc.addImage(o.foto, formato(o.foto), M, y0, fotoW, fotoH, undefined, 'FAST');
    doc.rect(M, y0, fotoW, fotoH);
    if (o.logo) {
      try { doc.addImage(o.logo, formato(o.logo), 210 - M - 34, y0 + 2, 34, 34, undefined, 'FAST'); }
      catch (e) { /* sin logo antes que sin planilla */ }
    }

    var cx = 105;
    doc.setTextColor.apply(doc, TINTA);
    doc.setFont('helvetica', 'bold'); doc.setFontSize(17);
    doc.text('PLANILLA DE INSCRIPCIÓN', cx, y0 + 14, { align: 'center' });
    doc.setFontSize(10.5); doc.setTextColor.apply(doc, ORO);
    doc.text('GARCÍA TRAINING · GT BASEBALL ACADEMY', cx, y0 + 21, { align: 'center' });
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9); doc.setTextColor.apply(doc, GRIS);
    doc.text('N.º ' + o.id + '   ·   Recibida el ' + o.fecha, cx, y0 + 27, { align: 'center' });

    var y = y0 + fotoH + 10;

    function seccion(titulo) {
      doc.setFillColor.apply(doc, ORO);
      doc.rect(M, y, 3, 6, 'F');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(11); doc.setTextColor.apply(doc, TINTA);
      doc.text(titulo, M + 6, y + 4.6);
      doc.setDrawColor(220, 222, 226); doc.setLineWidth(0.3);
      doc.line(M + 6 + doc.getTextWidth(titulo) + 3, y + 3, 210 - M, y + 3);
      y += 10;
    }

    // Una fila de campos: [[etiqueta, valor, fracción del ancho], ...]
    function fila(campos) {
      var x = M, gap = 6, alto = 12, util = ANCHO - gap * (campos.length - 1);
      var lineas = 1;
      campos.forEach(function (c) {
        var w = util * c[2];
        doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, GRIS);
        doc.text(c[0].toUpperCase(), x, y);
        doc.setFont('helvetica', 'normal'); doc.setFontSize(11); doc.setTextColor.apply(doc, TINTA);
        var txt = doc.splitTextToSize(String(c[1] || ''), w - 1);
        if (txt.length > 2) txt = txt.slice(0, 2);
        doc.text(txt, x, y + 5.5);
        lineas = Math.max(lineas, txt.length);
        c._x = x; c._w = w;
        x += w + gap;
      });
      var base = y + 7 + (lineas - 1) * 4.6;
      doc.setDrawColor(150, 154, 160); doc.setLineWidth(0.25);
      campos.forEach(function (c) { doc.line(c._x, base, c._x + c._w, base); });
      y = base + alto - 7;
    }

    seccion('DATOS DEL ATLETA');
    fila([['Nombre completo', d.nombre, 1]]);
    fila([['Fecha de nacimiento', d.fecha_nac_txt, 0.6], ['Edad', o.edad !== '' ? o.edad + ' años' : '', 0.4]]);
    fila([['Cédula o pasaporte', d.documento, 0.5], ['Nacionalidad', d.nacionalidad, 0.5]]);
    fila([['Dirección', d.direccion, 1]]);
    fila([['Teléfono del atleta', d.telefono, 0.5]]);

    y += 2;
    seccion('DATOS DEL REPRESENTANTE');
    fila([['Nombre y apellido', d.rep_nombre, 0.6], ['Teléfono', d.rep_telefono, 0.4]]);
    fila([['Correo electrónico', d.correo, 1]]);

    y += 2;
    seccion('DATOS DE BÉISBOL');
    fila([['Liga o programa', d.liga, 1]]);
    fila([['Posición', d.posicion, 0.46], ['Batea', d.batea, 0.27], ['Lanza', d.lanza, 0.27]]);
    fila([['Estatura', d.estatura, 0.34], ['Peso', d.peso, 0.33], ['Referencia millas', d.millas, 0.33]]);
    fila([['Año de firma', d.anio_firma, 0.34]]);

    // Firma a mano al imprimir
    var yf = Math.max(y + 16, 245);
    doc.setDrawColor(90, 94, 100); doc.setLineWidth(0.35);
    doc.line(M, yf, M + 105, yf);
    doc.line(M + 120, yf, 210 - M, yf);
    doc.setFont('helvetica', 'bold'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, GRIS);
    doc.text('FIRMA DEL REPRESENTANTE', M, yf + 4.5);
    doc.text('FECHA', M + 120, yf + 4.5);

    // Pie
    doc.setFont('helvetica', 'normal'); doc.setFontSize(7.5); doc.setTextColor.apply(doc, GRIS);
    doc.text('Las informaciones aquí recogidas son para el uso interno y exclusivo de la academia, para fines de evaluación.', 105, 283, { align: 'center' });
    doc.setTextColor(160, 164, 170);
    doc.text('Planilla digital · AutomatizaTech', 105, 288, { align: 'center' });
    return doc;
  }

  global.GTPlanilla = { crear: crear };
})(window);

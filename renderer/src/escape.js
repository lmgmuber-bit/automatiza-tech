function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// Turns the http(s) URLs of an already-escaped string into links. It runs
// after escapeHtml on purpose: the text stays inert and only the URL, which
// the regex guarantees has no quotes or angle brackets (raw or escaped),
// becomes markup. A
// closing bracket or sentence punctuation right after the URL is left out of
// the link so «(ver https://x.cl/demo)» does not swallow the parenthesis.
function linkify(escaped) {
  return String(escaped).replace(/https?:\/\/(?:(?!&lt;|&gt;|&quot;|&#39;)[^\s<>"'])+/g, (raw) => {
    const m = raw.match(/^(.*?)([.,;:!?)\]]*)$/);
    const url = m[1];
    const tail = m[2];
    const label = url.replace(/^https?:\/\//, '');
    return `<a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a>${tail}`;
  });
}

module.exports = { escapeHtml, linkify };

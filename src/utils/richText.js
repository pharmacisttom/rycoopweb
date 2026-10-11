import DOMPurify from 'dompurify';

export function safeRichText(value = '') {
  return DOMPurify.sanitize(String(value), {
    ALLOWED_TAGS: ['p','br','strong','b','em','i','u','s','h2','h3','blockquote','ul','ol','li','a'],
    ALLOWED_ATTR: ['href','title'], ALLOW_DATA_ATTR: false,
  });
}

export function richTextPlain(value = '') {
  const element = document.createElement('div');
  element.innerHTML = safeRichText(value);
  return (element.textContent || '').trim();
}

export function textToRichText(value = '') {
  const element = document.createElement('div');
  element.textContent = value;
  return `<p>${element.innerHTML.replace(/\n/g, '<br>')}</p>`;
}

import DOMPurify from 'dompurify';
import { Marked } from 'marked';

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
}

const parser = new Marked({
    gfm: true,
    breaks: true,
    renderer: {
        code({ text, lang }) {
            const label = lang ? `<span class="code-lang">${escapeHtml(lang)}</span>` : '';
            return `<div class="code-block">${label}<button type="button" class="copy-btn">Copiar</button><pre><code>${escapeHtml(text)}</code></pre></div>`;
        }
    }
});

export function renderMarkdown(text) {
    return DOMPurify.sanitize(parser.parse(String(text ?? '')), {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'del', 's', 'blockquote',
            'ul', 'ol', 'li', 'a', 'pre', 'code', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'div', 'span', 'button'],
        ALLOWED_ATTR: ['href', 'title', 'class', 'type', 'start'],
        ALLOW_DATA_ATTR: false,
        ALLOW_ARIA_ATTR: false,
        // No remote images, styles, embeds or forms in model output.
    });
}

export function plainText(text) {
    return escapeHtml(text).replace(/\r?\n/g, '<br>');
}

export function safeUrl(value) {
    try {
        const url = new URL(String(value ?? ''), window.location.href);
        return ['https:', 'http:'].includes(url.protocol) ? url.href : '';
    } catch {
        return '';
    }
}

window.CiriloContent = Object.freeze({ renderMarkdown, escapeHtml, plainText, safeUrl });

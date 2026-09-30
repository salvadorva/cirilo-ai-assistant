import { after, before, test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import puppeteer from 'puppeteer';

let browser;
let page;

before(async () => {
    const bundle = await build({ entryPoints: ['resources/js/safe-content.js'], bundle: true, write: false, format: 'iife' });
    browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox', '--disable-setuid-sandbox'] });
    page = await browser.newPage();
    await page.setRequestInterception(true);
    page.on('request', request => request.abort());
    await page.setContent('<!doctype html><html><body><main id="output"></main></body></html>');
    await page.addScriptTag({ content: bundle.outputFiles[0].text });
});

after(async () => { await browser?.close(); });

for (const payload of [
    '<img src=x onerror="window.compromised=true"><script>window.compromised=true</script>',
    '<svg onload="window.compromised=true"><a href="javascript:alert(1)">click</a></svg>',
    '<iframe srcdoc="<script>parent.compromised=true</script>"></iframe><object data="https://example.test"></object>',
    '[click](javascript:alert(1)) <a href="javascript:alert(1)" onclick="window.compromised=true">click</a>',
    '<math><mtext><table><mglyph><style><!--</style><img title="--><img src=1 onerror=window.compromised=true>">',
    '<form action="https://example.test"><input name="password"></form><div style="position:fixed" onmouseover="window.compromised=true">text</div>',
]) {
    test(`sanitizes active HTML: ${payload.slice(0, 48)}`, async () => {
        const result = await page.evaluate(async text => {
            window.compromised = false;
            const output = document.getElementById('output');
            output.innerHTML = window.CiriloContent.renderMarkdown(text);
            await new Promise(resolve => requestAnimationFrame(resolve));
            return {
                compromised: window.compromised,
                dangerous: output.querySelectorAll('script,img,svg,math,iframe,object,embed,form,input,style').length,
                attributes: [...output.querySelectorAll('*')].flatMap(el => [...el.attributes].filter(a => /^on|^style$|^srcdoc$/i.test(a.name)).map(a => a.name)),
                hrefs: [...output.querySelectorAll('a[href]')].map(a => a.getAttribute('href')),
            };
        }, payload);
        assert.equal(result.compromised, false);
        assert.equal(result.dangerous, 0);
        assert.deepEqual(result.attributes, []);
        assert.ok(result.hrefs.every(href => !/^\s*(javascript|data|vbscript):/i.test(href)));
    });
}

test('code blocks escape both source and language label and retain copy button', async () => {
    const code = '<img src=x onerror="window.compromised=true"> & <script>alert(1)</script>';
    const result = await page.evaluate(({ code }) => {
        const output = document.getElementById('output');
        output.innerHTML = window.CiriloContent.renderMarkdown('```html\"><img/src=x>\n' + code + '\n```');
        return { text: output.querySelector('pre code')?.textContent, active: output.querySelectorAll('img,script').length, buttons: output.querySelectorAll('button.copy-btn').length };
    }, { code });
    assert.equal(result.text, code);
    assert.equal(result.active, 0);
    assert.equal(result.buttons, 1);
});

test('preserves useful Markdown formatting', async () => {
    const tags = await page.evaluate(() => {
        const output = document.getElementById('output');
        output.innerHTML = window.CiriloContent.renderMarkdown('# Title\n\n**bold** and *italic*\n\n- item\n\n[link](https://example.test)\n\n| A | B |\n| - | - |\n| 1 | 2 |');
        return ['h1', 'strong', 'em', 'li', 'a[href="https://example.test"]', 'table'].map(tag => !!output.querySelector(tag));
    });
    assert.ok(tags.every(Boolean));
});

test('plain text and attribute interpolation cannot inject HTML', async () => {
    const result = await page.evaluate(() => {
        const output = document.getElementById('output');
        output.innerHTML = window.CiriloContent.plainText('<img src=x onerror=alert(1)>\n"quoted" & text') +
            '<span title="' + window.CiriloContent.escapeHtml('\" onmouseover=alert(1) x=\"') + '">test</span>';
        return { active: output.querySelectorAll('img,script,[onmouseover]').length, breaks: output.querySelectorAll('br').length, text: output.textContent };
    });
    assert.equal(result.active, 0);
    assert.equal(result.breaks, 1);
    assert.match(result.text, /<img src=x/);
});

test('image/download URL helper rejects executable protocols', async () => {
    const urls = await page.evaluate(() => ['javascript:alert(1)', 'data:text/html,hello', 'vbscript:msgbox(1)', 'https://example.test/image.png'].map(window.CiriloContent.safeUrl));
    assert.deepEqual(urls, ['', '', '', 'https://example.test/image.png']);
});

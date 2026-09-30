import { after, before, beforeEach, test } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import puppeteer from 'puppeteer';

let browser;
let page;
const id = '01234567-89ab-4cde-8fab-0123456789ab';

before(async () => {
    const bundle = await build({ entryPoints: ['resources/js/interaction-feedback.js'], bundle: true, write: false, format: 'iife' });
    browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox', '--disable-setuid-sandbox'] });
    page = await browser.newPage();
    await page.setRequestInterception(true);
    page.on('request', request => request.abort());
    await page.setContent('<!doctype html><meta name="csrf-token" content="fake-csrf"><main></main>');
    await page.addScriptTag({ content: bundle.outputFiles[0].text });
});
beforeEach(async () => {
    await page.evaluate(() => {
        document.querySelector('main').replaceChildren();
        window.requests = [];
        window.fetch = async (url, options) => { window.requests.push({ url, ...options }); return { ok: true }; };
    });
});
after(async () => { await browser?.close(); });

test('feedback is optional, with no preselected judgment or default correction count', async () => {
    const result = await page.evaluate(id => {
        window.CiriloFeedback.mount(document.querySelector('main'), id);
        return { values: [...document.querySelector('form').elements].slice(0, 3).map(el => el.value), requests: window.requests.length };
    }, id);
    assert.deepEqual(result, { values: ['', '', ''], requests: 0 });
});

test('rejects invalid interaction identifiers without creating HTML', async () => {
    const count = await page.evaluate(() => {
        for (const value of [undefined, '', '<img src=x onerror=alert(1)>', '../../admin']) window.CiriloFeedback.mount(document.querySelector('main'), value);
        return document.querySelector('main').children.length;
    });
    assert.equal(count, 0);
});

test('submits only numeric/boolean feedback, with CSRF and same-origin session', async () => {
    await page.evaluate(id => {
        window.CiriloFeedback.mount(document.querySelector('main'), id);
        const form = document.querySelector('form');
        form.elements.useful.value = '1';
        form.elements.task_achieved.value = '0';
        form.elements.corrections.value = '2';
        form.requestSubmit();
    }, id);
    const result = await page.evaluate(() => ({ request: window.requests[0], status: document.querySelector('[role=status]').textContent }));
    assert.equal(result.request.url, `/interactions/${id}/feedback`);
    assert.equal(result.request.headers['X-CSRF-TOKEN'], 'fake-csrf');
    assert.equal(result.request.credentials, 'same-origin');
    assert.deepEqual(JSON.parse(result.request.body), { useful: true, task_achieved: false, corrections: 2 });
    assert.match(result.status, /guardada/);
});

test('network failure preserves feedback and allows retry', async () => {
    await page.evaluate(id => {
        window.fetch = async () => { throw new Error('offline'); };
        window.CiriloFeedback.mount(document.querySelector('main'), id);
        const form = document.querySelector('form');
        form.elements.useful.value = '0';
        form.elements.task_achieved.value = '0';
        form.elements.corrections.value = '3';
        form.requestSubmit();
    }, id);
    const result = await page.evaluate(() => ({ status: document.querySelector('[role=status]').textContent, disabled: document.querySelector('button').disabled, corrections: document.querySelector('input').value }));
    assert.match(result.status, /reintentar/);
    assert.equal(result.disabled, false);
    assert.equal(result.corrections, '3');
});

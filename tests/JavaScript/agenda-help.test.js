const test = require('node:test');
const assert = require('node:assert/strict');
const { start } = require('../../public/js/scheduling/agenda-help');

function button(title, text) {
    const node = {
        attrs: { 'data-help-title': title, 'data-help-text': text },
        listeners: {},
        focused: false,
        children: [],
        setAttribute(name, value) { this.attrs[name] = value; },
        getAttribute(name) { return this.attrs[name]; },
        addEventListener(name, fn) { this.listeners[name] = fn; },
        focus() { this.focused = true; },
        getBoundingClientRect() { return { left: 12, bottom: 40, top: 14, width: 26 }; },
        click() { this.listeners.click({ preventDefault() {}, stopPropagation() {}, target: this }); },
    };
    return node;
}

function documentWith(buttons) {
    const listeners = {};
    const body = { children: [], append(...nodes) { this.children.push(...nodes); } };
    const document = {
        body,
        querySelectorAll() { return buttons; },
        addEventListener(name, fn) { listeners[name] = fn; },
        createElement() {
            const node = { children: [], style: {}, hidden: false, attrs: {}, listeners: {} };
            node.setAttribute = (name, value) => { node.attrs[name] = value; };
            node.append = (...kids) => { node.children.push(...kids); kids.forEach((kid) => { kid.parent = node; }); };
            node.contains = (target) => target === node || node.children.some((kid) => kid === target || (kid.contains && kid.contains(target)));
            node.addEventListener = (name, fn) => { node.listeners[name] = fn; };
            node.focus = () => { node.focused = true; };
            return node;
        },
        listeners,
    };
    return document;
}

test('el popover abre con clic, cierra con ×, Escape y clic fuera, y solo queda uno', () => {
    const first = button('Cita adicional', 'Paciente extra en una hora que ya tiene una cita regular.');
    const second = button('Retiro', 'El paciente llegó pero se va antes de atenderse.');
    const document = documentWith([first, second]);
    const help = start(document);
    first.click();
    assert.equal(help.pop.hidden, false);
    assert.equal(first.attrs['aria-expanded'], 'true');
    assert.equal(help.pop.children[0].children[0].textContent, 'Cita adicional');
    second.click();
    assert.equal(first.attrs['aria-expanded'], 'false');
    assert.equal(second.attrs['aria-expanded'], 'true');
    assert.equal(help.pop.children[0].children[0].textContent, 'Retiro');
    const close = help.pop.children[0].children[1];
    close.listeners.click({ preventDefault() {}, stopPropagation() {} });
    assert.equal(help.pop.hidden, true);
    assert.equal(second.focused, true);
    first.click();
    document.listeners.keydown({ key: 'Escape' });
    assert.equal(help.pop.hidden, true);
    first.click();
    document.listeners.click({ target: document.body });
    assert.equal(help.pop.hidden, true);
    assert.equal(second.attrs['aria-expanded'], 'false');
});

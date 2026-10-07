(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.PatientPhone = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    var DEFAULT_PREFIX = '+51';
    var PREFIXES = ['+598', '+595', '+593', '+591', '+58', '+57', '+56', '+55', '+54', '+52', '+51', '+34', '+1'];

    function text(value) {
        return value === null || value === undefined ? '' : String(value).trim();
    }

    function split(stored) {
        var value = text(stored);

        if (value === '') {
            return { parsed: true, prefijo: DEFAULT_PREFIX, numero: '', raw: '' };
        }

        var compact = value.replace(/[\s-]+/g, '');
        var match = PREFIXES.find(function (prefix) {
            return compact.indexOf(prefix) === 0 && /^\d{6,15}$/.test(compact.slice(prefix.length));
        });

        if (!match) {
            return { parsed: false, prefijo: DEFAULT_PREFIX, numero: '', raw: value };
        }

        return {
            parsed: true,
            prefijo: match,
            numero: compact.slice(match.length),
            raw: '',
        };
    }

    function compose(prefix, number) {
        var digits = text(number).replace(/[\s-]+/g, '');

        if (digits === '') {
            return null;
        }

        return (text(prefix) || DEFAULT_PREFIX) + digits;
    }

    function emailMessage(value) {
        var email = text(value);

        if (email === '') {
            return '';
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return 'Ingresa un correo electrónico válido.';
        }

        return '';
    }

    return {
        DEFAULT_PREFIX: DEFAULT_PREFIX,
        split: split,
        compose: compose,
        emailMessage: emailMessage,
    };
}));

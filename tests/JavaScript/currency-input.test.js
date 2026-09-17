import test from 'node:test';
import assert from 'node:assert/strict';
import currencyInput, { parseCurrency } from '../../resources/js/currency-input.js';

test('Vietnamese money becomes an exact canonical decimal string', () => {
    const cases = [
        ['', ''], ['0', '0'], ['1.234', '1234'], ['1234', '1234'],
        ['1.234,56', '1234.56'], ['0,01', '0.01'], ['12,', '12'],
        ['1.234.567,89', '1234567.89'], ['9.999.999.999.999,99', '9999999999999.99'],
    ];
    for (const [display, canonical] of cases) assert.equal(parseCurrency(display), canonical, display);
});

test('malformed grouping, signs, extra decimals and foreign characters are rejected', () => {
    for (const display of ['1.23', '1..234', '1,234.56', '-10', '1e3', 'NaN', '12,345', '1,2,3', 'abc1.234,56xyz']) {
        assert.equal(parseCurrency(display), null, display);
    }
});

test('invalid money marks the field invalid instead of silently submitting a different number', () => {
    const field = currencyInput('1.234,56');
    assert.equal(field.valid, true);
    assert.equal(field.canonicalValue, '1234.56');
    field.display = '1..234';
    assert.equal(field.valid, false);
    assert.equal(field.canonicalValue, '');
    field.display = '';
    assert.equal(field.valid, true);
    assert.equal(field.canonicalValue, '');
});

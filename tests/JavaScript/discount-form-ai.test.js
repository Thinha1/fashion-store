import test from 'node:test';
import assert from 'node:assert/strict';
import { DISCOUNT_FIELD_LABELS, formatAmount } from '../../resources/js/discount-form-ai.js';

test('money is shown the way the form shows it', () => {
    assert.strictEqual(formatAmount(500000), '500.000');
    assert.strictEqual(formatAmount(1250000), '1.250.000');
    assert.strictEqual(formatAmount(100), '100');
    assert.strictEqual(formatAmount(12.5), '12,5');
    assert.strictEqual(formatAmount('1250.75'), '1.250,75');
});

test('every field the server may send has a label for the chat bubble', () => {
    for (const field of ['name', 'code', 'discount_type', 'discount_value', 'starts_at', 'ends_at', 'applies_to', 'brands', 'products']) {
        assert.ok(DISCOUNT_FIELD_LABELS[field], field);
    }
});

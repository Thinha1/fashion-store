import test from 'node:test';
import assert from 'node:assert/strict';
import productDetail from '../../resources/js/product-detail.js';

const variants = [
    { id: 1, size: 'M', color: 'Đen', stock: 3, price: 100 },
    { id: 2, size: 'M', color: 'Trắng', stock: 1, price: 120 },
    { id: 3, size: 'L', color: 'Đen', stock: 0, price: 110 },
];
const images = [
    { id: 10, variantId: null, color: null, url: '/shared.jpg' },
    { id: 11, variantId: 1, color: 'Đen', url: '/black-front.jpg' },
    { id: 12, variantId: 1, color: 'Đen', url: '/black-back.jpg' },
    { id: 13, variantId: 2, color: 'Trắng', url: '/white.jpg' },
];

test('color changes the gallery, price, stock and resets the selected thumbnail and quantity', () => {
    const detail = productDetail({ variants, images });
    assert.deepEqual(detail.images.map((image) => image.url), ['/black-front.jpg', '/black-back.jpg']);
    detail.nextImage();
    detail.inc();
    assert.equal(detail.activeImage, 1);
    detail.pickColor('Trắng');
    assert.deepEqual(detail.images.map((image) => image.url), ['/white.jpg']);
    assert.equal(detail.activeImage, 0);
    assert.equal(detail.qty, 1);
    assert.equal(detail.variant.price, 120);
    detail.inc();
    assert.equal(detail.qty, 1);
});

test('picking a size keeps browsing the same color\'s gallery, and stock updates for that size', () => {
    const detail = productDetail({ variants, images });
    detail.pickSize('L');
    assert.deepEqual(detail.images.map((image) => image.url), ['/black-front.jpg', '/black-back.jpg']);
    assert.equal(detail.inStock, false);
    detail.pickColor('Trắng');
    assert.equal(detail.selectedSize, 'M');
    assert.equal(detail.variant.id, 2);
    detail.pickSize('L');
    assert.equal(detail.selectedColor, 'Đen');
});

test('gallery navigation wraps within the current color and quantities stay within bounds', () => {
    const detail = productDetail({ variants, images });
    detail.prevImage();
    assert.equal(detail.activeImage, 1);
    detail.nextImage();
    assert.equal(detail.activeImage, 0);
    for (let i = 0; i < 5; i++) detail.inc();
    assert.equal(detail.qty, 3);
    for (let i = 0; i < 5; i++) detail.dec();
    assert.equal(detail.qty, 1);
});

test('a color with no tagged photos and no shared fallback yields an empty gallery', () => {
    const noGeneralImages = images.filter((image) => image.variantId !== null);
    const extraVariants = [...variants, { id: 4, size: 'L', color: 'Xanh', stock: 2, price: 90 }];
    const detail = productDetail({ variants: extraVariants, images: noGeneralImages });
    detail.pickColor('Xanh');
    assert.deepEqual(detail.images, []);
    detail.nextImage();
    detail.prevImage();
    assert.equal(detail.activeImage, 0);
    const empty = productDetail();
    assert.equal(empty.variant, null);
    assert.equal(empty.maxQty, 0);
    assert.deepEqual(empty.images, []);
});

test('initial selection prefers stock and supports quotes and Vietnamese text', () => {
    const detail = productDetail({ variants: [variants[2], { ...variants[0], color: "Xanh 'đậm'" }], images });
    assert.equal(detail.selectedColor, "Xanh 'đậm'");
    assert.equal(detail.variant.id, 1);
    assert.equal(productDetail({ variants: [variants[2]] }).variant.id, 3);
});

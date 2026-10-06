const test = require('node:test');
const assert = require('node:assert/strict');
const heatmap = require('../../public/js/scheduling/agenda-heatmap');

test('los buckets de extremos mantienen el centro dentro del canvas y mayor densidad da más intensidad', () => {
    const centers = [], colors = [];
    const ctx = { clearRect() {}, fillRect() {}, createRadialGradient(x, y) {
        centers.push([x, y]); return { addColorStop(stop, color) { if (stop === 0) { colors.push(color); } } };
    } };
    const maximum = heatmap.draw(ctx, 1000, 650, { grid_size: 40, points: [
        { bucket_x: 0, bucket_y: 0, clicks: 1 }, { bucket_x: 39, bucket_y: 39, clicks: 10 },
    ] });
    assert.equal(maximum, 10);
    assert.ok(centers[0][0] > 0 && centers[0][1] > 0);
    assert.ok(centers[1][0] < 1000 && centers[1][1] < 650);
    assert.ok(Number(colors[1].match(/,([\d.]+)\)$/)[1]) > Number(colors[0].match(/,([\d.]+)\)$/)[1]));
});
test('un rango sin eventos se limpia sin errores ni puntos inventados', () => {
    let cleared = 0;
    const ctx = { clearRect() { cleared++; }, fillRect() {}, createRadialGradient() { assert.fail('no events'); } };
    heatmap.draw(ctx, 1000, 650, { grid_size: 40, points: [] });
    assert.equal(cleared, 1);
});

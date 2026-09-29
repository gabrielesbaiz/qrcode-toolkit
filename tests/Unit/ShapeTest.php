<?php

declare(strict_types=1);

use Gabrielesbaiz\QrcodeToolkit\Geometry\Path;
use Gabrielesbaiz\QrcodeToolkit\Geometry\PathOp;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Polygon;
use Gabrielesbaiz\QrcodeToolkit\Geometry\Shape;

it('clamps a radius to half the shorter side', function (): void {
    $clamped = Shape::square(0, 0, 10, 50)->clampRadii();

    expect($clamped->tl)->toBe(5.0)->and($clamped->br)->toBe(5.0);
});

it('scales down two radii that would overlap on one edge', function (): void {
    $clamped = (new Shape(0, 0, 10, 10, 8, 8, 0, 0))->clampRadii();

    expect($clamped->tl + $clamped->tr)->toBeLessThanOrEqual(10.0);
});

it('leaves sane radii alone', function (): void {
    $clamped = Shape::square(0, 0, 10, 2)->clampRadii();

    expect($clamped->tl)->toBe(2.0);
});

it('shrinks about the centre', function (): void {
    $scaled = Shape::square(0, 0, 10)->scaledAbout(0.5);

    expect($scaled->x)->toBe(2.5)
        ->and($scaled->y)->toBe(2.5)
        ->and($scaled->w)->toBe(5.0);
});

it('emits no curves for a square', function (): void {
    $path = new Path;
    (new Shape(0, 0, 1, 1))->writeTo($path);

    expect($path->isStraight())->toBeTrue();
});

it('emits four corners for a rounded shape', function (): void {
    $path = new Path;
    Shape::square(0, 0, 10, 2)->writeTo($path);

    $corners = array_filter($path->ops(), static fn (PathOp $op): bool => $op->op === PathOp::CORNER);

    expect($corners)->toHaveCount(4);
});

it('carries the elbow on every corner', function (): void {
    $path = new Path;
    Shape::square(0, 0, 10, 2)->writeTo($path);

    foreach ($path->ops() as $op) {
        if ($op->op !== PathOp::CORNER) {
            continue;
        }

        // The elbow always shares one coordinate with the end point,
        // which is what makes the bezier derivation exact.
        expect($op->elbowX === $op->x || $op->elbowY === $op->y)->toBeTrue();
    }
});

it('builds a four point diamond', function (): void {
    expect(Polygon::diamond(5, 5, 2, 2)->points)->toHaveCount(4);
});

it('reports whether a shape has any radius', function (): void {
    expect((new Shape(0, 0, 1, 1))->isRectangular())->toBeTrue()
        ->and(Shape::square(0, 0, 1, 0.5)->isRectangular())->toBeFalse();
});

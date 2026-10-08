<?php

use App\Enums\TypeAliment;

it('normalizes only recognized aliment type aliases', function (mixed $input, ?TypeAliment $expected) {
    expect(TypeAliment::tryFromLoose($input))->toBe($expected);
})->with([
    ['fourrage', TypeAliment::Fourrage],
    [' Fourrage ', TypeAliment::Fourrage],
    ['FOURRAGES', TypeAliment::Fourrage],
    ['concentre', TypeAliment::Concentre],
    ['Conc', TypeAliment::Concentre],
    ['CONCENTRÉS', TypeAliment::Concentre],
    ['mineral', TypeAliment::Mineral],
    ['Minéral', TypeAliment::Mineral],
    ['MINÉRAUX', TypeAliment::Mineral],
    [TypeAliment::Concentre, TypeAliment::Concentre],
    ['melange', null],
    ['Perso', null],
    ['fourrage inconnu', null],
    ['non concentre', null],
    ['concombre', null],
    ['mineralise', null],
    [null, null],
    ['', null],
    [23, null],
    [['Conc'], null],
]);

it('uses the same three canonical values and labels', function () {
    expect(TypeAliment::values())->toBe(['fourrage', 'concentre', 'mineral']);
    foreach (TypeAliment::cases() as $type) {
        expect($type->label())->toBe($type->value);
    }
});

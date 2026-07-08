<?php

use App\Enums\CategorieAnimal;
use App\Enums\Espece;

test('fromLoose maps legacy, french and canonical labels to the right category', function (string $input, CategorieAnimal $expected) {
    expect(CategorieAnimal::fromLoose($input))->toBe($expected);
})->with([
    'legacy camelCase laitière' => ['vacheLaitiere', CategorieAnimal::VacheLaitiere],
    'french label laitière' => ['Vache laitière', CategorieAnimal::VacheLaitiere],
    'canonical laitière' => ['vache_laitiere', CategorieAnimal::VacheLaitiere],
    'legacy camelCase allaitante' => ['vacheAllaitante', CategorieAnimal::VacheAllaitante],
    'french label allaitante' => ['Vache allaitante', CategorieAnimal::VacheAllaitante],
    'brebis laitière' => ['Brebis laitière', CategorieAnimal::BrebisLaitiere],
    'brebis allaitante' => ['brebis allaitante', CategorieAnimal::BrebisAllaitante],
    'agnelle' => ['agnelle de renouvellement', CategorieAnimal::AgneauCroissance],
    'chèvre' => ['Chèvre laitière', CategorieAnimal::ChevreLaitiere],
    'chevrette' => ['chevrette', CategorieAnimal::ChevretteCroissance],
    'bovin croissance' => ['bovin en croissance', CategorieAnimal::BovinCroissance],
    'bovin engraissement' => ['jeune bovin à l\'engraissement', CategorieAnimal::BovinEngraissement],
]);

test('fromLoose falls back to vache laitière for empty, null and unknown values', function () {
    expect(CategorieAnimal::fromLoose(''))->toBe(CategorieAnimal::VacheLaitiere);
    expect(CategorieAnimal::fromLoose(null))->toBe(CategorieAnimal::VacheLaitiere);
    expect(CategorieAnimal::fromLoose('valeur inconnue xyz'))->toBe(CategorieAnimal::VacheLaitiere);
});

test('encombrement unit follows the INRA 2018 reference per category', function (CategorieAnimal $cat, string $ue) {
    expect($cat->uniteEncombrement())->toBe($ue);
})->with([
    [CategorieAnimal::VacheLaitiere, 'uel'],
    [CategorieAnimal::VacheAllaitante, 'ueb'],
    [CategorieAnimal::BovinCroissance, 'ueb'],
    [CategorieAnimal::BovinEngraissement, 'ueb'],
    [CategorieAnimal::BrebisLaitiere, 'uem'],
    [CategorieAnimal::BrebisAllaitante, 'uem'],
    [CategorieAnimal::AgneauCroissance, 'uem'],
    [CategorieAnimal::ChevreLaitiere, 'uel'],
    [CategorieAnimal::ChevretteCroissance, 'uel'],
]);

test('energy unit is UFV only for fattening cattle and lambs', function (CategorieAnimal $cat, string $uf) {
    expect($cat->uniteFourragere())->toBe($uf);
})->with([
    [CategorieAnimal::VacheLaitiere, 'ufl'],
    [CategorieAnimal::VacheAllaitante, 'ufl'],
    [CategorieAnimal::BovinCroissance, 'ufl'],
    [CategorieAnimal::BovinEngraissement, 'ufv'],
    [CategorieAnimal::AgneauCroissance, 'ufv'],
    [CategorieAnimal::ChevreLaitiere, 'ufl'],
]);

test('poidsParDefaut returns a species-appropriate default weight (kg)', function (CategorieAnimal $cat, int $poids) {
    expect($cat->poidsParDefaut())->toBe($poids);
})->with([
    [CategorieAnimal::VacheLaitiere, 650],
    [CategorieAnimal::VacheAllaitante, 650],
    [CategorieAnimal::BovinCroissance, 400],
    [CategorieAnimal::BovinEngraissement, 450],
    [CategorieAnimal::BrebisLaitiere, 70],
    [CategorieAnimal::BrebisAllaitante, 70],
    [CategorieAnimal::AgneauCroissance, 30],
    [CategorieAnimal::ChevreLaitiere, 60],
    [CategorieAnimal::ChevretteCroissance, 40],
]);

test('estLaitiere flags the three dairy categories', function () {
    expect(CategorieAnimal::VacheLaitiere->estLaitiere())->toBeTrue();
    expect(CategorieAnimal::BrebisLaitiere->estLaitiere())->toBeTrue();
    expect(CategorieAnimal::ChevreLaitiere->estLaitiere())->toBeTrue();
    expect(CategorieAnimal::VacheAllaitante->estLaitiere())->toBeFalse();
    expect(CategorieAnimal::BovinEngraissement->estLaitiere())->toBeFalse();
});

test('estEnCroissance flags growing and fattening categories', function () {
    expect(CategorieAnimal::BovinCroissance->estEnCroissance())->toBeTrue();
    expect(CategorieAnimal::BovinEngraissement->estEnCroissance())->toBeTrue();
    expect(CategorieAnimal::AgneauCroissance->estEnCroissance())->toBeTrue();
    expect(CategorieAnimal::ChevretteCroissance->estEnCroissance())->toBeTrue();
    expect(CategorieAnimal::VacheLaitiere->estEnCroissance())->toBeFalse();
});

test('every ruminant category has a calculation engine', function () {
    foreach (CategorieAnimal::cases() as $categorie) {
        expect($categorie->estImplementee())->toBeTrue();
    }
});

test('espece groups every category under one of the three ruminant species', function () {
    expect(CategorieAnimal::VacheLaitiere->espece())->toBe(Espece::Bovin);
    expect(CategorieAnimal::BrebisLaitiere->espece())->toBe(Espece::Ovin);
    expect(CategorieAnimal::ChevretteCroissance->espece())->toBe(Espece::Caprin);
});

test('optionsGroupedBySpecies exposes a frontend-ready payload', function () {
    $groups = CategorieAnimal::optionsGroupedBySpecies();

    expect($groups)->toHaveCount(3);
    expect(array_column($groups, 'espece'))->toBe(['bovin', 'ovin', 'caprin']);

    $bovins = collect($groups)->firstWhere('espece', 'bovin');
    expect($bovins['categories'])->toHaveCount(4);

    $vacheLaitiere = collect($bovins['categories'])->firstWhere('value', 'vache_laitiere');
    expect($vacheLaitiere)->toMatchArray([
        'value' => 'vache_laitiere',
        'label' => 'Vache laitière',
        'disponible' => true,
        'est_laitiere' => true,
        'est_croissance' => false,
        'unite_encombrement' => 'UEL',
        'unite_fourragere' => 'UFL',
        'poids_defaut' => 650,
    ]);

    $chevre = collect($groups)->firstWhere('espece', 'caprin');
    $chevreLaitiere = collect($chevre['categories'])->firstWhere('value', 'chevre_laitiere');
    expect($chevreLaitiere['poids_defaut'])->toBe(60);
});

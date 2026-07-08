<?php

use App\Models\Ration;
use App\Services\RationHelper;

/**
 * Le poids vif par défaut doit dépendre de l'espèce : une brebis sans poids saisi
 * ne doit pas être calculée comme une vache de 650 kg.
 */
test('poidsVif falls back to the species default when no weight is stored', function (string $categorie, float $attendu) {
    $ration = Ration::factory()->make([
        'categorie_animal' => $categorie,
        'poids_vif' => null,
    ]);

    expect(RationHelper::poidsVif($ration))->toBe($attendu);
})->with([
    'vache laitière' => ['vache_laitiere', 650.0],
    'brebis laitière' => ['brebis_laitiere', 70.0],
    'agneau' => ['agneau_croissance', 30.0],
    'chèvre laitière' => ['chevre_laitiere', 60.0],
    'chevrette' => ['chevrette_croissance', 40.0],
]);

test('poidsVif returns the stored weight when it is set', function () {
    $ration = Ration::factory()->make([
        'categorie_animal' => 'brebis_laitiere',
        'poids_vif' => 82,
    ]);

    expect(RationHelper::poidsVif($ration))->toBe(82.0);
});

<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Catégorie d'animal pilotant tout le routage du moteur de calcul INRA 2018 :
 * espèce, unité d'encombrement (UE), unité fourragère (UF) et branches de besoins.
 *
 * Les valeurs canoniques (snake_case) sont celles stockées en base ;
 * {@see self::fromLoose()} accepte en plus les anciens libellés historiques.
 */
enum CategorieAnimal: string
{
    // Bovins
    case VacheLaitiere = 'vache_laitiere';
    case VacheAllaitante = 'vache_allaitante';
    case BovinCroissance = 'bovin_croissance';       // femelles/mâles légers, UFL, GMQ ≤ 1 kg/j
    case BovinEngraissement = 'bovin_engraissement'; // finition, UFV, GMQ > 1 kg/j

    // Ovins
    case BrebisLaitiere = 'brebis_laitiere';
    case BrebisAllaitante = 'brebis_allaitante';     // brebis suitée
    case AgneauCroissance = 'agneau_croissance';     // agneaux + agnelles de renouvellement, UFV

    // Caprins
    case ChevreLaitiere = 'chevre_laitiere';
    case ChevretteCroissance = 'chevrette_croissance';

    public function espece(): Espece
    {
        return match ($this) {
            self::VacheLaitiere,
            self::VacheAllaitante,
            self::BovinCroissance,
            self::BovinEngraissement => Espece::Bovin,
            self::BrebisLaitiere,
            self::BrebisAllaitante,
            self::AgneauCroissance => Espece::Ovin,
            self::ChevreLaitiere,
            self::ChevretteCroissance => Espece::Caprin,
        };
    }

    /**
     * Unité d'encombrement propre à la catégorie (référentiel INRA 2018).
     */
    public function uniteEncombrement(): string
    {
        return match ($this) {
            self::VacheLaitiere,
            self::ChevreLaitiere,
            self::ChevretteCroissance => 'uel',
            self::BrebisLaitiere,
            self::BrebisAllaitante,
            self::AgneauCroissance => 'uem',
            self::VacheAllaitante,
            self::BovinCroissance,
            self::BovinEngraissement => 'ueb',
        };
    }

    /**
     * Unité fourragère énergie : UFL (lait/croissance lente) ou UFV (engraissement/agneaux).
     */
    public function uniteFourragere(): string
    {
        return match ($this) {
            self::BovinEngraissement,
            self::AgneauCroissance => 'ufv',
            default => 'ufl',
        };
    }

    public function uniteEncombrementLabel(): string
    {
        return strtoupper($this->uniteEncombrement());
    }

    public function uniteFourragereLabel(): string
    {
        return strtoupper($this->uniteFourragere());
    }

    public function estLaitiere(): bool
    {
        return match ($this) {
            self::VacheLaitiere,
            self::BrebisLaitiere,
            self::ChevreLaitiere => true,
            default => false,
        };
    }

    public function estEnCroissance(): bool
    {
        return match ($this) {
            self::BovinCroissance,
            self::BovinEngraissement,
            self::AgneauCroissance,
            self::ChevretteCroissance => true,
            default => false,
        };
    }

    /**
     * Le moteur de calcul dispose-t-il d'une implémentation vérifiée pour cette catégorie ?
     * Les catégories non encore implémentées restent sélectionnables mais signalées côté UI.
     */
    public function estImplementee(): bool
    {
        // Toutes les catégories ruminants du référentiel INRA 2018 disposent d'un moteur de calcul.
        return true;
    }

    /**
     * Poids vif par défaut (kg) proposé lorsqu'aucun poids n'est saisi.
     * Sert de repli au moteur de calcul et de valeur initiale du formulaire, afin qu'une brebis
     * ou une chèvre ne soit jamais calculée avec le poids d'une vache.
     */
    public function poidsParDefaut(): int
    {
        return match ($this) {
            self::VacheLaitiere,
            self::VacheAllaitante => 650,
            self::BovinCroissance => 400,
            self::BovinEngraissement => 450,
            self::BrebisLaitiere,
            self::BrebisAllaitante => 70,
            self::AgneauCroissance => 30,
            self::ChevreLaitiere => 60,
            self::ChevretteCroissance => 40,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::VacheLaitiere => 'Vache laitière',
            self::VacheAllaitante => 'Vache allaitante',
            self::BovinCroissance => 'Bovin en croissance',
            self::BovinEngraissement => 'Bovin à l\'engraissement',
            self::BrebisLaitiere => 'Brebis laitière',
            self::BrebisAllaitante => 'Brebis allaitante (suitée)',
            self::AgneauCroissance => 'Agneau / agnelle en croissance',
            self::ChevreLaitiere => 'Chèvre laitière',
            self::ChevretteCroissance => 'Chevrette en croissance',
        };
    }

    /**
     * Résout n'importe quelle représentation (valeur canonique, ancien libellé camelCase
     * « vacheLaitiere », libellé français « Vache laitière »…) vers une catégorie.
     * Repli sur {@see self::VacheLaitiere} pour préserver le comportement historique.
     */
    public static function fromLoose(?string $categorie): self
    {
        $categorie = (string) $categorie;

        if ($enum = self::tryFrom($categorie)) {
            return $enum;
        }

        $n = Str::of($categorie)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();

        return match (true) {
            str_contains($n, 'chevrette') => self::ChevretteCroissance,
            str_contains($n, 'chevre') => self::ChevreLaitiere,
            str_contains($n, 'agneau'), str_contains($n, 'agnel') => self::AgneauCroissance,
            str_contains($n, 'brebis') && str_contains($n, 'allait') => self::BrebisAllaitante,
            str_contains($n, 'brebis') => self::BrebisLaitiere,
            str_contains($n, 'engrais') => self::BovinEngraissement,
            str_contains($n, 'bovin') && str_contains($n, 'croissance') => self::BovinCroissance,
            str_contains($n, 'allait') => self::VacheAllaitante,
            default => self::VacheLaitiere,
        };
    }

    /**
     * Options de sélection regroupées par espèce, prêtes à être exposées au frontend Inertia.
     *
     * @return array<int, array{espece: string, label: string, categories: array<int, array{value: string, label: string, disponible: bool, est_laitiere: bool, est_croissance: bool, unite_encombrement: string, unite_fourragere: string, poids_defaut: int}>}>
     */
    public static function optionsGroupedBySpecies(): array
    {
        $groups = [];

        foreach (Espece::cases() as $espece) {
            $categories = [];

            foreach (self::cases() as $categorie) {
                if ($categorie->espece() !== $espece) {
                    continue;
                }

                $categories[] = [
                    'value' => $categorie->value,
                    'label' => $categorie->label(),
                    'disponible' => $categorie->estImplementee(),
                    'est_laitiere' => $categorie->estLaitiere(),
                    'est_croissance' => $categorie->estEnCroissance(),
                    'unite_encombrement' => $categorie->uniteEncombrementLabel(),
                    'unite_fourragere' => $categorie->uniteFourragereLabel(),
                    'poids_defaut' => $categorie->poidsParDefaut(),
                ];
            }

            $groups[] = [
                'espece' => $espece->value,
                'label' => $espece->label(),
                'categories' => $categories,
            ];
        }

        return $groups;
    }
}

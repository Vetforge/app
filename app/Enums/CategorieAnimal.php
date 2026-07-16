<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\RationScientificMatrix;
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
    case AgneauCroissance = 'agneau_croissance';     // agneau à l'engraissement, UFV (nom de clé historique)

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
            self::BrebisAllaitante => 'uem',
            // L'agneau d'engraissement est piloté par une ingestion de MS (Éq. 20.55),
            // les UE étant déclarées non applicables aux rations concentrées.
            self::AgneauCroissance => 'kg_ms',
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
        return $this->uniteEncombrement() === 'kg_ms' ? 'kg MS' : strtoupper($this->uniteEncombrement());
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
        return RationScientificMatrix::isAvailable($this);
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
            self::AgneauCroissance => 'Agneau à l\'engraissement',
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
        return self::tryFromLoose($categorie) ?? self::VacheLaitiere;
    }

    /**
     * Résout une représentation vers une catégorie via une liste fermée d'alias reconnus,
     * ou {@see null} si l'entrée n'est pas reconnaissable. À utiliser à la validation d'une saisie
     * utilisateur pour rejeter l'inconnu, plutôt que de le convertir silencieusement (cf. FOR-02).
     */
    public static function tryFromLoose(?string $categorie): ?self
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

        if ($n === '') {
            return null;
        }

        return match (true) {
            str_contains($n, 'chevrette') => self::ChevretteCroissance,
            str_contains($n, 'chevre') => self::ChevreLaitiere,
            str_contains($n, 'agneau'), str_contains($n, 'agnel') => self::AgneauCroissance,
            str_contains($n, 'brebis') && str_contains($n, 'allait') => self::BrebisAllaitante,
            str_contains($n, 'brebis') => self::BrebisLaitiere,
            str_contains($n, 'engrais') => self::BovinEngraissement,
            str_contains($n, 'bovin') && str_contains($n, 'croissance') => self::BovinCroissance,
            str_contains($n, 'genisse') => self::BovinCroissance,
            str_contains($n, 'vache') && str_contains($n, 'allait') => self::VacheAllaitante,
            str_contains($n, 'allait') => self::VacheAllaitante,
            str_contains($n, 'vache') => self::VacheLaitiere,
            str_contains($n, 'laitiere') => self::VacheLaitiere,
            default => null,
        };
    }

    /**
     * Options de sélection regroupées par espèce, prêtes à être exposées au frontend Inertia.
     *
     * @return array<int, array{espece: string, label: string, categories: array<int, array<string, mixed>>}>
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
                    'validation' => RationScientificMatrix::for($categorie),
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

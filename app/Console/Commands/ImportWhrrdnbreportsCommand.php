<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Aliment;
use App\Models\Analysis;
use App\Models\Breeder;
use App\Models\User;
use App\Models\UserModuleSetting;
use App\Services\VeterinaryAnalysisCalculator;
use App\Support\LegacyHtmlCleaner;
use App\Support\VeterinaryModules;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ImportWhrrdnbreportsCommand extends Command
{
    protected $signature = 'legacy:import-whrrdnbreports
        {path : Chemin du fichier PHPMyAdmin exporte en PHP array}
        {--user= : ID ou email de l utilisateur cible}
        {--cabinet=rieupeyroux : identification_cabinet a importer}
        {--memory=1G : memory_limit applique pendant le chargement}
        {--dry-run : Compte les lignes importables sans ecrire en base}';

    protected $description = 'Importe les donnees utiles de l ancien export whrrdnbreports sans conserver de mapping legacy.';

    private const MAPPING_TABLE = 'legacy_import_mappings';

    private const ANALYSIS_TABLES = [
        'coproscopie_parasitaire' => 'coproscopie-parasitaire',
        'diarrhee_neonatale' => 'diarrhee-neonatale',
        'gaz_sang' => 'gaz-du-sang',
        'tests_cellules' => 'comptage-cellulaire',
        'bacteriologie_antibiogramme' => 'diagnostic-bacteriologique',
        'analyses_diverses' => 'analyse-diverse',
        'tests_rapides' => 'tests-rapides',
        'tests_biochimie' => 'tests-biochimie',
        'hematologie' => 'hemogramme',
        'autopsie' => 'autopsie',
        'compte_rendu' => 'compte-rendu',
        'bse_laitier' => 'bse-laitier',
        'bse_allaitant' => 'bse-allaitant',
    ];

    private const BSE_NUMERIC_CONFIG_COLUMNS = [
        'prix_mal_diar1' => ['prixMalDiar1', 'prixStdMalDiar1'],
        'prix_mal_diar2et3' => ['prixMalDiar2Et3', 'prixStdMalDiar2Et3'],
        'prix_mal_diar4' => ['prixMalDiar4', 'prixStdMalDiar4'],
        'prix_perf_diar' => ['prixPerfDiar', 'prixStdPerfDiar'],
        'prix_mal_respi' => ['prixMalRespi', 'prixStdMalRespi'],
        'prix_mal_omphalite' => ['prixMalOmphalite', 'prixStdMalOmphalite'],
        'prix_mort_diar1' => ['prixMortDiar1', 'prixStdMortDiar1'],
        'prix_mort_diar2et3' => ['prixMortDiar2Et3', 'prixStdMortDiar2Et3'],
        'prix_mort_diar4' => ['prixMortDiar4', 'prixStdMortDiar4'],
        'prix_mort_respi' => ['prixMortRespi', 'prixStdMortRespi'],
        'prix_mort_omphalite' => ['prixMortOmphalite', 'prixStdMortOmphalite'],
        'prix_veau_ivv' => ['prixVeauIVV', 'prixStdVeauIVV'],
        'prix_veau_avortement' => ['prixVeauAvortement', 'prixStdVeauAvortement'],
        'prix_veau_accident_velage' => ['prixVeauAccidentVelage', 'prixStdVeauAccidentVelage'],
        'prix_mort_autres' => ['prixMortAutres', 'prixStdMortAutres'],
        'prix_mort_subite' => ['prixMortSubite', 'prixStdMortSubite'],
        'prix_ha_foin' => ['prixHaFoin', 'prixStdHaFoin'],
        'prix_ha_ensilage_herbe' => ['prixHaEnsilageHerbe', 'prixStdHaEnsilageHerbe'],
        'prix_ha_ensilage_mais' => ['prixHaEnsilageMais', 'prixStdHaEnsilageMais'],
        'prix_production_cereales_tonnes' => ['prixProductionCerealesTonnes', 'prixStdProductionCerealesTonnes'],
    ];

    private const BSE_TEXT_CONFIG_COLUMNS = [
        'txt_tx_mortalite_total_veaux_s' => ['txt_txMortaliteTotalVeauxS', 'txtStd_txMortaliteTotalVeauxS'],
        'txt_tx_mortalite_total_veaux_ns' => ['txt_txMortaliteTotalVeauxNS', 'txtStd_txMortaliteTotalVeauxNS'],
        'txt_tx_diarrhee_veaux_total_s' => ['txt_txDiarrheeVeauxTotalS', 'txtStd_txDiarrheeVeauxTotalS'],
        'txt_tx_diarrhee_veaux_total_ns' => ['txt_txDiarrheeVeauxTotalNS', 'txtStd_txDiarrheeVeauxTotalNS'],
        'txt_tx_respi_veaux_s' => ['txt_txRespiVeauxS', 'txtStd_txRespiVeauxS'],
        'txt_tx_respi_veaux_ns' => ['txt_txRespiVeauxNS', 'txtStd_txRespiVeauxNS'],
        'txt_tx_omphalite_veaux_s' => ['txt_txOmphaliteVeauxS', 'txtStd_txOmphaliteVeauxS'],
        'txt_tx_omphalite_veaux_ns' => ['txt_txOmphaliteVeauxNS', 'txtStd_txOmphaliteVeauxNS'],
        'txt_ivv_s' => ['txt_IVVS', 'txtStd_IVVS'],
        'txt_ivv_ns' => ['txt_IVVNS', 'txtStd_IVVNS'],
        'txt_cout_alimentaire_vache_s' => ['txt_coutAlimentaireVacheS', 'txtStd_coutAlimentaireVacheS'],
        'txt_cout_alimentaire_vache_ns' => ['txt_coutAlimentaireVacheNS', 'txtStd_coutAlimentaireVacheNS'],
        'txt_tx_mortalite_neonatale_s' => ['txt_txMortaliteNeonataleS', 'txtStd_txMortaliteNeonataleS'],
        'txt_tx_mortalite_neonatale_ns' => ['txt_txMortaliteNeonataleNS', 'txtStd_txMortaliteNeonataleNS'],
        'txt_tx_mammites_s' => ['txt_txMammitesS', 'txtStd_txMammitesS'],
        'txt_tx_mammites_ns' => ['txt_txMammitesNS', 'txtStd_txMammitesNS'],
        'txt_tx_boiteries_s' => ['txt_txBoiteriesS', 'txtStd_txBoiteriesS'],
        'txt_tx_boiteries_ns' => ['txt_txBoiteriesNS', 'txtStd_txBoiteriesNS'],
        'txt_tx_metaboliques_s' => ['txt_txMetaboliquesS', 'txtStd_txMetaboliquesS'],
        'txt_tx_metaboliques_ns' => ['txt_txMetaboliquesNS', 'txtStd_txMetaboliquesNS'],
        'txt_cout_reproduction_s' => ['txt_coutReproductionS', 'txtStd_coutReproductionS'],
        'txt_cout_reproduction_ns' => ['txt_coutReproductionNS', 'txtStd_coutReproductionNS'],
        'txt_cout_alimentaire_vache_l_s' => ['txt_coutAlimentaireVacheLS', 'txtStd_coutAlimentaireVacheLS'],
        'txt_cout_alimentaire_vache_l_ns' => ['txt_coutAlimentaireVacheLNS', 'txtStd_coutAlimentaireVacheLNS'],
    ];

    private const BSE_LAITIER_CONFIG_KEYS = [
        'prix_mal_diar1',
        'prix_mal_diar2et3',
        'prix_mal_diar4',
        'prix_perf_diar',
        'prix_mal_respi',
        'prix_mal_omphalite',
        'prix_mort_diar1',
        'prix_mort_diar2et3',
        'prix_mort_diar4',
        'prix_mort_respi',
        'prix_mort_omphalite',
        'prix_veau_ivv',
        'prix_ha_foin',
        'prix_ha_ensilage_herbe',
        'prix_ha_ensilage_mais',
        'prix_production_cereales_tonnes',
        'txt_tx_mortalite_neonatale_s',
        'txt_tx_mortalite_neonatale_ns',
        'txt_tx_mammites_s',
        'txt_tx_mammites_ns',
        'txt_tx_boiteries_s',
        'txt_tx_boiteries_ns',
        'txt_tx_metaboliques_s',
        'txt_tx_metaboliques_ns',
        'txt_cout_reproduction_s',
        'txt_cout_reproduction_ns',
        'txt_cout_alimentaire_vache_l_s',
        'txt_cout_alimentaire_vache_l_ns',
    ];

    private const BSE_ALLAITANT_CONFIG_KEYS = [
        'prix_mal_diar1',
        'prix_mal_diar2et3',
        'prix_mal_diar4',
        'prix_perf_diar',
        'prix_mal_respi',
        'prix_mal_omphalite',
        'prix_mort_diar1',
        'prix_mort_diar2et3',
        'prix_mort_diar4',
        'prix_mort_respi',
        'prix_mort_omphalite',
        'prix_mort_autres',
        'prix_mort_subite',
        'prix_veau_ivv',
        'prix_veau_avortement',
        'prix_veau_accident_velage',
        'prix_ha_foin',
        'prix_ha_ensilage_herbe',
        'prix_ha_ensilage_mais',
        'prix_production_cereales_tonnes',
        'txt_tx_mortalite_total_veaux_s',
        'txt_tx_mortalite_total_veaux_ns',
        'txt_tx_diarrhee_veaux_total_s',
        'txt_tx_diarrhee_veaux_total_ns',
        'txt_tx_respi_veaux_s',
        'txt_tx_respi_veaux_ns',
        'txt_tx_omphalite_veaux_s',
        'txt_tx_omphalite_veaux_ns',
        'txt_ivv_s',
        'txt_ivv_ns',
        'txt_cout_alimentaire_vache_s',
        'txt_cout_alimentaire_vache_ns',
    ];

    private const BIOCHIMIE_PARAM_COLUMNS = [
        'ALB' => 'ALBbioch',
        'ALKP' => 'ALKPbioch',
        'ALT' => 'ALTbioch',
        'AMYL' => 'AMYLbioch',
        'AST' => 'ASTbioch',
        'UREE' => 'UREEbioch',
        'Ca' => 'Cabioch',
        'CHOL' => 'CHOLbioch',
        'CK' => 'CKbioch',
        'Cl' => 'Clbioch',
        'CREA' => 'CREAbioch',
        'UREA_CREA' => 'UREECREAbioch',
        'FRU' => 'FRUbioch',
        'GGT' => 'GGTbioch',
        'GLOB' => 'GLOBbioch',
        'ALB_GLOB' => 'ALBGLOBbioch',
        'GLU' => 'GLUbioch',
        'K' => 'Kbioch',
        'LAC' => 'LACbioch',
        'LDH' => 'LDHbioch',
        'LIPA' => 'LIPAbioch',
        'Mg' => 'Mgbioch',
        'Na' => 'Nabioch',
        'Na_K' => 'NaKbioch',
        'NH3' => 'NH3bioch',
        'OSMOL' => 'Osmolalitebioch',
        'PHBR' => 'PHBRbioch',
        'PHOS' => 'PHOSbioch',
        'TBIL' => 'TBILbioch',
        'TP' => 'TPbioch',
        'TRIG' => 'TRIGbioch',
        'UCREA' => 'UCREAbioch',
        'UPC' => 'UPCbioch',
        'UPRO' => 'UPRObioch',
        'URIC' => 'URICbioch',
    ];

    private const HEMOGRAMME_PARAM_COLUMNS = [
        'GR' => 'GRhemato',
        'HCT' => 'HCThemato',
        'HGB' => 'HGBhemato',
        'VGM' => 'VGMhemato',
        'TCMH' => 'TCMHhemato',
        'CCMH' => 'CCMHhemato',
        'IDR' => 'IDRhemato',
        'PRETIC' => 'PRETIChemato',
        'RETIC' => 'RETIChemato',
        'GB' => 'GBhemato',
        'PNEU' => 'PNEUhemato',
        'PLYM' => 'PLYMhemato',
        'PMONO' => 'PMONOhemato',
        'PEOS' => 'PEOShemato',
        'PBASO' => 'PBASOhemato',
        'NEU' => 'NEUhemato',
        'LYM' => 'LYMhemato',
        'MONO' => 'MONOhemato',
        'EOS' => 'EOShemato',
        'BASO' => 'BASOhemato',
        'PLT' => 'PLThemato',
        'VMP' => 'VMPhemato',
        'IDP' => 'IDPhemato',
        'PCT' => 'PCThemato',
        'PGRA' => 'PGRAhemato',
        'GRA' => 'GRAhemato',
    ];

    private const HEMOGRAMME_UNIT_COLUMNS = [
        'GR' => 'UniteGR',
        'HCT' => 'UniteHCT',
        'HGB' => 'UniteHGB',
        'VGM' => 'UniteVGM',
        'TCMH' => 'UniteTCMH',
        'CCMH' => 'UniteCCMH',
        'IDR' => 'UniteIDR',
        'RETIC' => 'UniteRETIC',
        'GB' => 'UniteGB',
        'NEU' => 'UniteNEU',
        'LYM' => 'UniteLYM',
        'MONO' => 'UniteMONO',
        'EOS' => 'UniteEOS',
        'BASO' => 'UniteBASO',
        'PLT' => 'UnitePLT',
        'VMP' => 'UniteVMP',
        'IDP' => 'UniteIDP',
        'PCT' => 'UnitePCT',
        'GRA' => 'UniteGRA',
    ];

    /** @var array<string, int> */
    private array $breederMap = [];

    /** @var array<string, string> */
    private array $animalNames = [];

    /** @var array<string, bool> */
    private array $reservedBreederNames = [];

    /** @var array<string, Carbon> */
    private array $breederArchiveDates = [];

    /** @var array<string, array<string, mixed>> */
    private array $moduleSettings = [];

    private ?Carbon $globalArchiveDate = null;

    private string $importToken = '';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $cabinet = (string) $this->option('cabinet');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("Fichier introuvable: {$path}");

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if (! $user) {
            $this->error('Option --user obligatoire. Utilise un ID ou un email existant.');

            return self::FAILURE;
        }

        ini_set('memory_limit', (string) $this->option('memory'));

        $stats = $this->emptyStats();

        if ($dryRun) {
            $stats = $this->dryRunStats($path, $cabinet);
            $this->printStats($stats, true);

            return self::SUCCESS;
        }

        $this->importToken = (string) str()->uuid();

        try {
            $this->createTemporaryMappingTable();
            $this->buildArchiveDateMaps($path, $cabinet);
            $clientRows = $this->loadTable($path, 'clients_cabinets');
            $this->importLegacyModuleSettings($path, $clientRows, $user, $cabinet);
            $this->animalNames = $this->buildAnimalNameMap($this->loadTable($path, 'liste_animaux'), $cabinet);
            $this->reserveExistingBreederNames($user);

            $stats['breeders'] = $this->importBreeders($clientRows, $user, $cabinet);
            $stats['aliments'] = $this->importAliments($this->loadTable($path, 'aliments_ration'), $user, $cabinet);

            foreach (self::ANALYSIS_TABLES as $table => $module) {
                $stats['analyses'][$module] = $this->importAnalyses($this->loadTable($path, $table), $table, $module, $user, $cabinet);
                gc_collect_cycles();
            }

            $this->printStats($stats, false);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            Schema::dropIfExists(self::MAPPING_TABLE);
        }
    }

    private function resolveUser(): ?User
    {
        $value = (string) $this->option('user');

        if ($value === '') {
            return null;
        }

        return ctype_digit($value)
            ? User::query()->find((int) $value)
            : User::query()->where('email', $value)->first();
    }

    /**
     * @return array{breeders: int, aliments: int, analyses: array<string, int>}
     */
    private function emptyStats(): array
    {
        return [
            'breeders' => 0,
            'aliments' => 0,
            'analyses' => array_fill_keys(array_values(self::ANALYSIS_TABLES), 0),
        ];
    }

    /**
     * @return array{breeders: int, aliments: int, analyses: array<string, int>}
     */
    private function dryRunStats(string $path, string $cabinet): array
    {
        $stats = $this->emptyStats();

        $stats['breeders'] = count($this->matchingRows($this->loadTable($path, 'clients_cabinets'), $cabinet));
        $stats['aliments'] = count($this->matchingAlimentRows($this->loadTable($path, 'aliments_ration'), $cabinet));

        foreach (self::ANALYSIS_TABLES as $table => $module) {
            $stats['analyses'][$module] = count($this->matchingRows($this->loadTable($path, $table), $cabinet));
            gc_collect_cycles();
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadTable(string $path, string $table): array
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'legacy_table_');

        if ($tmpPath === false) {
            throw new \RuntimeException('Impossible de creer un fichier temporaire pour importer la table legacy.');
        }

        $input = fopen($path, 'rb');
        $output = fopen($tmpPath, 'wb');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }

            if (is_resource($output)) {
                fclose($output);
            }

            @unlink($tmpPath);

            throw new \RuntimeException('Impossible de lire le fichier export legacy.');
        }

        fwrite($output, "<?php\n");

        $capturing = false;
        $found = false;
        $start = '$'.$table.' = array(';

        while (($line = fgets($input)) !== false) {
            if (! $capturing && str_starts_with(trim($line), $start)) {
                $capturing = true;
                $found = true;
            }

            if ($capturing) {
                fwrite($output, $line);

                if (trim($line) === ');') {
                    break;
                }
            }
        }

        fclose($input);
        fclose($output);

        if (! $found) {
            @unlink($tmpPath);

            return [];
        }

        $loader = static function (string $__path, string $__table): array {
            include $__path;

            return is_array($$__table ?? null) ? $$__table : [];
        };

        try {
            return $loader($tmpPath, $table);
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function matchingRows(array $rows, string $cabinet): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => (string) ($row['identification_cabinet'] ?? '') === $cabinet,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function matchingAlimentRows(array $rows, string $cabinet): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => in_array((string) ($row['identification_cabinet'] ?? ''), [$cabinet, 'tous'], true),
        ));
    }

    private function createTemporaryMappingTable(): void
    {
        Schema::dropIfExists(self::MAPPING_TABLE);

        Schema::create(self::MAPPING_TABLE, function (Blueprint $table): void {
            $table->id();
            $table->uuid('import_token');
            $table->string('source_table');
            $table->string('legacy_id');
            $table->string('target_table');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['import_token', 'source_table', 'legacy_id']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function buildAnimalNameMap(array $rows, string $cabinet): array
    {
        $map = [];

        foreach ($this->matchingRows($rows, $cabinet) as $row) {
            $id = $this->legacyId($row, 'idAnimal');
            $name = $this->str($row['nomAnimal'] ?? null);

            if ($id !== '' && $name !== null) {
                $map[$id] = $name;
            }
        }

        return $map;
    }

    private function reserveExistingBreederNames(User $user): void
    {
        $this->reservedBreederNames = Breeder::query()
            ->where('user_id', $user->id)
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => true])
            ->all();
    }

    private function buildArchiveDateMaps(string $path, string $cabinet): void
    {
        $this->breederArchiveDates = [];
        $this->globalArchiveDate = null;

        foreach (array_keys(self::ANALYSIS_TABLES) as $table) {
            foreach ($this->matchingRows($this->loadTable($path, $table), $cabinet) as $row) {
                $date = $this->archiveDate($row);

                if (! $date) {
                    continue;
                }

                $this->globalArchiveDate = $this->earliestDate($this->globalArchiveDate, $date);

                $breederId = $this->legacyId($row, 'idEleveur');

                if ($breederId !== '') {
                    $this->breederArchiveDates[$breederId] = $this->earliestDate(
                        $this->breederArchiveDates[$breederId] ?? null,
                        $date,
                    );
                }
            }

            gc_collect_cycles();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importBreeders(array $rows, User $user, string $cabinet): int
    {
        $count = 0;

        foreach ($this->matchingRows($rows, $cabinet) as $row) {
            $legacyId = $this->legacyId($row, 'idEleveur');

            if ($legacyId === '') {
                continue;
            }

            $breeder = $this->firstOrCreateBreeder($row, $user);
            $this->breederMap[$legacyId] = $breeder->id;
            $this->insertMapping('clients_cabinets', $legacyId, 'breeders', $breeder->id);
            $count++;
        }

        return $count;
    }

    private function firstOrCreateBreeder(array $row, User $user): Breeder
    {
        $name = $this->str($row['nomEleveur'] ?? null) ?? 'Eleveur sans nom';
        $name = $this->uniqueBreederName($name, $row);
        $timestamp = $this->timestampForBreeder($row);

        /** @var Breeder $breeder */
        $breeder = Breeder::query()->firstOrCreate(
            ['user_id' => $user->id, 'name' => $name],
            [
                'address' => $this->str($row['adresseEleveur'] ?? null),
                'postal_code' => $this->str($row['codePostalEleveur'] ?? null),
                'city' => $this->str($row['villeEleveur'] ?? null),
                'phone' => $this->firstString($row, ['telephoneEleveur', 'telephoneEleveur2', 'telephoneEleveur3']),
                'email' => $this->firstString($row, ['emailEleveur', 'emailEleveur2', 'emailEleveur3']),
                'herd_number' => $this->str($row['numeroCheptelEleveur'] ?? null),
                'notes' => $this->breederNotes($row),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        if ($breeder->wasRecentlyCreated) {
            $this->applyArchiveTimestamps($breeder, $timestamp);
        }

        $this->reservedBreederNames[$name] = true;

        return $breeder;
    }

    private function uniqueBreederName(string $name, array $row): string
    {
        if (! isset($this->reservedBreederNames[$name])) {
            return $name;
        }

        foreach ([
            trim($name.' - '.($this->str($row['villeEleveur'] ?? null) ?? '')),
            trim($name.' ('.($this->str($row['numeroCheptelEleveur'] ?? null) ?? '').')'),
        ] as $candidate) {
            if ($candidate !== $name && $candidate !== '' && ! isset($this->reservedBreederNames[$candidate])) {
                return $candidate;
            }
        }

        $counter = 2;

        do {
            $candidate = "{$name} ({$counter})";
            $counter++;
        } while (isset($this->reservedBreederNames[$candidate]));

        return $candidate;
    }

    private function breederNotes(array $row): ?string
    {
        $parts = [];

        foreach (['telephoneEleveur2', 'telephoneEleveur3', 'telephoneEleveur4', 'telephoneEleveur5', 'telephoneEleveur6'] as $key) {
            $value = $this->str($row[$key] ?? null);

            if ($value !== null) {
                $parts[] = "Tel supplementaire: {$value}";
            }
        }

        foreach (['emailEleveur2', 'emailEleveur3', 'emailEleveur4', 'emailEleveur5', 'emailEleveur6'] as $key) {
            $value = $this->str($row[$key] ?? null);

            if ($value !== null) {
                $parts[] = "Email supplementaire: {$value}";
            }
        }

        return $parts === [] ? null : implode("\n", $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importAliments(array $rows, User $user, string $cabinet): int
    {
        $count = 0;

        foreach ($this->matchingAlimentRows($rows, $cabinet) as $row) {
            $legacyId = $this->legacyId($row, 'idAliment');
            $name = $this->str($row['nom_aliment'] ?? null);

            if ($legacyId === '' || $name === null) {
                continue;
            }

            /** @var Aliment $aliment */
            $aliment = Aliment::query()->firstOrCreate(
                ['user_id' => $user->id, 'libelle0' => $name],
                $this->legacyAlimentAttributes($row, $user),
            );

            if ($aliment->wasRecentlyCreated) {
                $this->applyArchiveTimestamps($aliment, $this->timestampForAliment($row));
            }

            $this->insertMapping('aliments_ration', $legacyId, 'aliments', $aliment->id);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyAlimentAttributes(array $row, User $user): array
    {
        $timestamp = $this->timestampForAliment($row);

        return [
            'user_id' => $user->id,
            'code_inra' => null,
            'type' => $this->str($row['Type'] ?? null) ?? $this->str($row['type_alim'] ?? null),
            'libelle0' => $this->str($row['nom_aliment'] ?? null),
            'libelle1' => $this->str($row['type_alim'] ?? null),
            'prix' => $this->float($row['Prix'] ?? null),
            'usage_aliment' => null,
            'ms' => $this->float($row['matiere_seche'] ?? null),
            'mat' => $this->float($row['proteine_MS'] ?? null),
            'cb' => $this->float($row['cellulose_brute'] ?? null),
            'ndf' => $this->float($row['NDF'] ?? null),
            'adf' => $this->float($row['ADF'] ?? null),
            'amidon' => $this->float($row['amidon_MS'] ?? null),
            'ufl' => $this->float($row['UFL'] ?? null),
            'uel' => $this->float($row['UEL'] ?? null),
            'ueb' => $this->float($row['UEB'] ?? null),
            'ca' => $this->float($row['ca'] ?? null),
            'p' => $this->float($row['p'] ?? null),
            'mg' => $this->float($row['mg'] ?? null),
            'ufl2007' => $this->float($row['UFL'] ?? null),
            'pdie2007' => $this->float($row['PDIE'] ?? null),
            'pdin2007' => $this->float($row['PDIN'] ?? null),
            'uel2007' => $this->float($row['UEL'] ?? null),
            'ueb2007' => $this->float($row['UEB'] ?? null),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $clientRows
     */
    private function importLegacyModuleSettings(string $path, array $clientRows, User $user, string $cabinet): void
    {
        $holdingCandidates = $this->legacyHoldingCandidates($path, $clientRows, $cabinet);
        $settingsByModule = $this->legacyModuleSettings($path, $holdingCandidates);
        $this->moduleSettings = [];

        foreach ($settingsByModule as $module => $settings) {
            $settings = VeterinaryModules::normalizeSettings($module, $settings);

            UserModuleSetting::query()->updateOrCreate(
                ['user_id' => $user->id, 'module' => $module],
                ['settings' => $settings],
            );

            $this->moduleSettings[$module] = $settings;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $clientRows
     * @return array<int, string>
     */
    private function legacyHoldingCandidates(string $path, array $clientRows, string $cabinet): array
    {
        $candidates = [];
        $add = function (mixed $value) use (&$candidates): bool {
            $candidate = $this->str($value);

            if ($candidate !== null && ! in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;

                return true;
            }

            return false;
        };

        foreach ($this->matchingRows($clientRows, $cabinet) as $row) {
            $add($row['nom_holding'] ?? null);
        }

        foreach ($this->matchingRows($this->loadTable($path, 'cabinets_veterinaires'), $cabinet) as $row) {
            $add($row['nom_holding'] ?? null);
        }

        if ($candidates === []) {
            foreach (array_keys(self::ANALYSIS_TABLES) as $table) {
                foreach ($this->matchingRows($this->loadTable($path, $table), $cabinet) as $row) {
                    if ($add($row['nom_holding'] ?? null)) {
                        break 2;
                    }
                }
            }
        }

        $add($cabinet);

        return $candidates;
    }

    /**
     * @param  array<int, string>  $holdingCandidates
     * @return array<string, array<string, mixed>>
     */
    private function legacyModuleSettings(string $path, array $holdingCandidates): array
    {
        $settings = [];

        $bseRow = $this->selectLegacyConfigRow($this->loadTable($path, 'config_vetoapplis'), $holdingCandidates);

        if ($bseRow !== null) {
            $settings['bse-laitier'] = $this->legacyBseSettings('bse-laitier', $bseRow);
            $settings['bse-allaitant'] = $this->legacyBseSettings('bse-allaitant', $bseRow);
        }

        $biochimieRows = $this->loadTable($path, 'config_vetoapplis_bioch');

        if ($biochimieRows !== []) {
            $settings['tests-biochimie'] = $this->legacyNormSettings(
                'tests-biochimie',
                $biochimieRows,
                $holdingCandidates,
                'EspBioch',
                self::BIOCHIMIE_PARAM_COLUMNS,
                fn (string $column, string $key): string => "Unite{$column}",
            );
        }

        $hemogrammeRows = $this->loadTable($path, 'config_vetoapplis_hemato');

        if ($hemogrammeRows !== []) {
            $settings['hemogramme'] = $this->legacyNormSettings(
                'hemogramme',
                $hemogrammeRows,
                $holdingCandidates,
                'EspHemato',
                self::HEMOGRAMME_PARAM_COLUMNS,
                fn (string $column, string $key): ?string => self::HEMOGRAMME_UNIT_COLUMNS[$key] ?? null,
            );
        }

        return $settings;
    }

    /**
     * @param  array<int, string>  $holdingCandidates
     */
    private function selectLegacyConfigRow(array $rows, array $holdingCandidates, ?string $speciesColumn = null, ?string $species = null): ?array
    {
        foreach ($rows as $row) {
            if ($this->legacyConfigRowMatches($row, $speciesColumn, $species) && $this->matchesLegacyHolding($row, $holdingCandidates)) {
                return $row;
            }
        }

        foreach ($rows as $row) {
            if ($this->legacyConfigRowMatches($row, $speciesColumn, $species) && $this->isLegacyDefaultConfigRow($row)) {
                return $row;
            }
        }

        foreach ($rows as $row) {
            if ($this->legacyConfigRowMatches($row, $speciesColumn, $species) && ! array_key_exists('nom_holding', $row)) {
                return $row;
            }
        }

        return null;
    }

    private function legacyConfigRowMatches(array $row, ?string $speciesColumn, ?string $species): bool
    {
        if ($speciesColumn === null) {
            return true;
        }

        return $this->str($row[$speciesColumn] ?? null) === $species;
    }

    /**
     * @param  array<int, string>  $holdingCandidates
     */
    private function matchesLegacyHolding(array $row, array $holdingCandidates): bool
    {
        $holding = $this->str($row['nom_holding'] ?? null);

        return $holding !== null && in_array($holding, $holdingCandidates, true);
    }

    private function isLegacyDefaultConfigRow(array $row): bool
    {
        $holding = mb_strtolower($this->str($row['nom_holding'] ?? null) ?? '');

        return $this->legacyId($row, 'idStd') !== ''
            || $holding === ''
            || $holding === 'tous';
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyBseSettings(string $module, array $row): array
    {
        $settings = VeterinaryModules::defaultSettings($module);
        $keys = $module === 'bse-laitier' ? self::BSE_LAITIER_CONFIG_KEYS : self::BSE_ALLAITANT_CONFIG_KEYS;

        foreach ($keys as $key) {
            if (isset(self::BSE_NUMERIC_CONFIG_COLUMNS[$key])) {
                $value = $this->legacyConfigFloat($row, self::BSE_NUMERIC_CONFIG_COLUMNS[$key]);

                if ($value !== null || $this->legacyConfigColumnExists($row, self::BSE_NUMERIC_CONFIG_COLUMNS[$key])) {
                    $settings[$key] = $value;
                }

                continue;
            }

            if (isset(self::BSE_TEXT_CONFIG_COLUMNS[$key])) {
                $value = $this->legacyConfigString($row, self::BSE_TEXT_CONFIG_COLUMNS[$key]);

                if ($value !== null || $this->legacyConfigColumnExists($row, self::BSE_TEXT_CONFIG_COLUMNS[$key])) {
                    $settings[$key] = $value !== null ? LegacyHtmlCleaner::plainText($value) : '';
                }
            }
        }

        return $settings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $holdingCandidates
     * @param  array<string, string>  $paramColumns
     * @param  callable(string, string): ?string  $unitColumn
     * @return array<string, mixed>
     */
    private function legacyNormSettings(
        string $module,
        array $rows,
        array $holdingCandidates,
        string $speciesColumn,
        array $paramColumns,
        callable $unitColumn,
    ): array {
        $settings = VeterinaryModules::defaultSettings($module);

        foreach ($this->legacyConfigSpecies($rows, $speciesColumn) as $legacySpecies) {
            $row = $this->selectLegacyConfigRow($rows, $holdingCandidates, $speciesColumn, $legacySpecies);

            if ($row === null) {
                continue;
            }

            $species = $this->legacySpecies($legacySpecies);
            $this->addSpeciesOption($settings, $species);
            $settings['norms'][$species] = $settings['norms'][$species] ?? [];

            foreach ($paramColumns as $key => $column) {
                $settings['norms'][$species][$key] = $this->legacyNorm(
                    $row,
                    "{$column}min",
                    "{$column}max",
                    $unitColumn($column, $key),
                    $settings['norms'][$species][$key] ?? ['min' => null, 'max' => null, 'unit' => ''],
                );
            }
        }

        return $settings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function legacyConfigSpecies(array $rows, string $speciesColumn): array
    {
        $species = [];

        foreach ($rows as $row) {
            $value = $this->str($row[$speciesColumn] ?? null);

            if ($value !== null && ! in_array($value, $species, true)) {
                $species[] = $value;
            }
        }

        return $species;
    }

    /**
     * @param  array{min?: mixed, max?: mixed, unit?: mixed}  $fallback
     * @return array{min: mixed, max: mixed, unit: mixed}
     */
    private function legacyNorm(array $row, string $minColumn, string $maxColumn, ?string $unitColumn, array $fallback): array
    {
        $norm = [
            'min' => $fallback['min'] ?? null,
            'max' => $fallback['max'] ?? null,
            'unit' => $fallback['unit'] ?? '',
        ];

        if (array_key_exists($minColumn, $row)) {
            $norm['min'] = $this->float($row[$minColumn]);
        }

        if (array_key_exists($maxColumn, $row)) {
            $norm['max'] = $this->float($row[$maxColumn]);
        }

        if ($unitColumn !== null && array_key_exists($unitColumn, $row)) {
            $norm['unit'] = $this->str($row[$unitColumn]) ?? '';
        }

        return $norm;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function addSpeciesOption(array &$settings, string $species): void
    {
        $options = is_array($settings['species_options'] ?? null) ? $settings['species_options'] : [];

        if (! in_array($species, $options, true)) {
            $options[] = $species;
        }

        $settings['species_options'] = $options;
    }

    private function legacySpecies(mixed $value): string
    {
        $species = mb_strtolower($this->str($value) ?? '');

        return match (true) {
            str_contains($species, 'porcin') => 'Porcin',
            str_contains($species, 'volaille') => 'Volaille',
            str_contains($species, 'lapin') => 'Lapin',
            str_contains($species, 'nac') => 'NAC',
            str_contains($species, 'poney'), str_contains($species, 'ane') => 'Equin',
            default => $this->species($value),
        };
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function legacyConfigFloat(array $row, array $columns): ?float
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $row) && $this->str($row[$column]) !== null) {
                return $this->float($row[$column]);
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function legacyConfigString(array $row, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $row) && $this->str($row[$column]) !== null) {
                return $this->str($row[$column]);
            }
        }

        return null;
    }

    private function legacyPlainText(mixed $value): string
    {
        return $this->str($value) ?? '';
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function legacyConfigColumnExists(array $row, array $columns): bool
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsForImport(string $module): array
    {
        return $this->moduleSettings[$module] ?? VeterinaryModules::defaultSettings($module);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importAnalyses(array $rows, string $sourceTable, string $module, User $user, string $cabinet): int
    {
        $count = 0;

        foreach ($this->matchingRows($rows, $cabinet) as $row) {
            $breeder = $this->resolveBreederForAnalysis($row, $user);

            if (! $breeder) {
                continue;
            }

            $settings = $this->settingsForImport($module);
            $payload = $this->payloadFor($module, $row, $settings);
            $results = VeterinaryAnalysisCalculator::calculate($module, $payload, $settings);
            $timestamp = $this->timestampForAnalysis($row);

            $analysis = Analysis::create([
                'user_id' => $user->id,
                'breeder_id' => $breeder->id,
                'animal_nom' => $this->animalName($row, $module),
                'module' => $module,
                'status' => 'complete',
                'sampled_at' => $this->date($row['datePrelevement'] ?? null),
                'analyzed_at' => $this->date($row['dateAnalyse'] ?? null),
                'intervenant' => $this->str($row['nomIntervenant'] ?? null),
                'payload' => $payload,
                'settings_snapshot' => $settings,
                'results' => $results,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $this->applyArchiveTimestamps($analysis, $timestamp);

            $legacyId = $this->legacyId($row, 'idAnalyse');

            if ($legacyId !== '') {
                $this->insertMapping($sourceTable, $legacyId, 'analyses', $analysis->id, ['module' => $module]);
            }

            $count++;
        }

        return $count;
    }

    private function resolveBreederForAnalysis(array $row, User $user): ?Breeder
    {
        $legacyId = $this->legacyId($row, 'idEleveur');

        if ($legacyId !== '' && isset($this->breederMap[$legacyId])) {
            return Breeder::query()->find($this->breederMap[$legacyId]);
        }

        $name = $this->str($row['nomEleveur'] ?? null);

        if ($name === null) {
            return null;
        }

        $breeder = $this->firstOrCreateBreeder($row, $user);

        if ($legacyId !== '') {
            $this->breederMap[$legacyId] = $breeder->id;
            $this->insertMapping('clients_cabinets', $legacyId, 'breeders', $breeder->id);
        }

        return $breeder;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function payloadFor(string $module, array $row, array $settings): array
    {
        return match ($module) {
            'coproscopie-parasitaire' => $this->coproscopyPayload($row),
            'diarrhee-neonatale' => $this->neonatalDiarrheaPayload($row),
            'gaz-du-sang' => $this->bloodGasPayload($row, $settings),
            'comptage-cellulaire' => $this->cellCountPayload($row),
            'diagnostic-bacteriologique' => $this->bacteriologyPayload($row),
            'analyse-diverse' => $this->miscAnalysisPayload($row),
            'tests-rapides' => $this->rapidTestsPayload($row),
            'tests-biochimie' => $this->biochimiePayload($row),
            'hemogramme' => $this->hemogrammePayload($row),
            'autopsie' => $this->autopsyPayload($row),
            'compte-rendu' => $this->reportPayload($row),
            'bse-laitier' => $this->bseLaitierPayload($row, $settings),
            'bse-allaitant' => $this->bseAllaitantPayload($row, $settings),
            default => VeterinaryModules::payloadTemplate($module, $settings),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function coproscopyPayload(array $row): array
    {
        $sampleCount = max(1, min(5, $this->int($row['nombreEchantillonCopro'] ?? null) ?? 1));
        $samples = [];

        $parasiteColumns = [
            'paramphistome' => ['Paramphistome'],
            'grande_douve' => ['GrandeDouve'],
            'petite_douve' => ['PetiteDouve'],
            'coccidies' => ['Coccidies'],
            'strongles_digestifs' => ['StronglesDigestifs', 'StronglesIntestinaux'],
            'nematodirus' => ['Nematodirus'],
            'strongyloides' => ['Strongyloides'],
            'trichure' => ['Trichure'],
            'taenia' => ['Taenia'],
            'strongles_pulmonaires' => ['StronglesPulmonaires'],
            'marshallagia_marshalli' => ['MarshallagiaMarshalli'],
            'cryptosporidie' => ['Cryptosporidie'],
            'eimeria_leuckarti' => ['EimeriaLeuckarti'],
            'parascaris_equorum' => ['ParascarisEquorum'],
            'anoplocephalides' => ['Anoplocephalides'],
            'habronema_sp' => ['HabronemaSp'],
            'strongyloides_westeri' => ['StrongyloidesWesteri'],
            'dictyocaulus_arnfieldi' => ['DictyocaulusArnfieldi'],
            'strongylus_spp' => ['StrongylusSpp'],
            'cyathostomes' => ['Cyathostomes'],
            'oxyuris_equi' => ['OxyurisEqui'],
            'cryptosporidium_parvum' => ['CryptosporidiumParvum'],
            'diphyllobotrium_latum' => ['Diphyllobotrium'],
            'dipylidium_caninum' => ['Dipylidium'],
            'taenia_cnct' => ['TaeniaCNCT'],
            'toxocara' => ['Toxocara'],
            'toxascaris_leonina' => ['Toxascaris'],
            'capillaria' => ['Capillaria'],
            'strongyloides_cnct' => ['StrongyloidesCNCT'],
            'giardia' => ['Giardia'],
            'sarcocystis' => ['Sarcocystis'],
            'toxoplasma' => ['Toxoplasma'],
            'cryptosporidium_cnct' => ['CryptosporidiumCNCT'],
        ];

        for ($i = 1; $i <= $sampleCount; $i++) {
            $results = [];

            foreach ($parasiteColumns as $key => $prefixes) {
                $results[$key] = $this->firstNumericColumn($row, array_map(fn (string $prefix): string => "{$prefix}Echantillon{$i}", $prefixes)) ?? 0;
            }

            $samples[] = [
                'name' => $this->str($row["nomEchantillonCopro{$i}"] ?? null) ?? "Echantillon {$i}",
                'results' => $results,
            ];
        }

        return [
            'species' => $this->species($row['especeCopro'] ?? null),
            'sample_nature' => $this->str($row['natureEchantillonCopro'] ?? null) ?? '',
            'sample_count' => $sampleCount,
            'options' => [
                'dictyocaules' => $this->bool($row['dictyoEchantillonCopro'] ?? null),
                'cryptosporidies' => $this->bool($row['cryptoEchantillonCopro'] ?? null),
                'comptage' => $this->bool($row['comptageEchantillonCopro'] ?? true),
            ],
            'samples' => $samples,
            'advice_preventive' => $this->str($row['conseilsJeunesAnimauxCopro'] ?? null) ?? '',
            'advice_curative' => $this->str($row['conseilsAnimauxAdultesCopro'] ?? null) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function neonatalDiarrheaPayload(array $row): array
    {
        return [
            'species' => $this->species($row['especeDiarrhee'] ?? null),
            'test_name' => $this->str($row['nomTestDiarrhee'] ?? null) ?? '',
            'sample_nature' => $this->str($row['natureEchantillonDiarrhee'] ?? null) ?? '',
            'sample_name' => $this->str($row['nomEchantillonDiarrhee'] ?? null) ?? '',
            'coccidiosis_test' => $this->bool($row['booleanTestCoccidiose'] ?? null),
            'pathogens' => [
                'rotavirus' => $this->result($row['RotavirusEchantillon'] ?? null),
                'coronavirus' => $this->result($row['CoronavirusEchantillon'] ?? null),
                'ecoli_k99' => $this->result($row['EColiK99Echantillon'] ?? null),
                'ecoli_cs31a' => $this->result($row['EColiCS31AEchantillon'] ?? null),
                'clostridium_perfringens' => $this->result($row['ClostridiumPerfringensEchantillon'] ?? null),
                'cryptosporidies' => $this->result($row['CryptosporidiesEchantillon'] ?? null),
                'giardia' => $this->result($row['GiardiaEchantillon'] ?? null),
                'coccidies' => $this->result($row['CoccidiesEchantillon'] ?? null),
            ],
            'advice_preventive' => $this->str($row['conseilsPreventifDiarrhee'] ?? null) ?? '',
            'advice_curative' => $this->str($row['conseilsCuratifDiarrhee'] ?? null) ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function bloodGasPayload(array $row, array $settings): array
    {
        return [
            'species' => $this->species($row['especeGaz'] ?? null),
            'weight' => $this->float($row['poidsVifAnimalGaz'] ?? null),
            'enophtalmie' => $this->float($row['enophtalmieAnimalGaz'] ?? null),
            'dehydration' => $this->float($row['deshydratationAnimalGaz'] ?? null),
            'ph' => $this->float($row['pHAnimalGaz'] ?? null),
            'pco2' => $this->float($row['PCO2AnimalGaz'] ?? null),
            'hco3' => $this->float($row['HCO3AnimalGaz'] ?? null),
            'angap' => $this->float($row['AnGapAnimalGaz'] ?? null),
            'tco2' => $this->float($row['TCO2AnimalGaz'] ?? null),
            'na' => $this->float($row['NaAnimalGaz'] ?? null),
            'k' => $this->float($row['KAnimalGaz'] ?? null),
            'cl' => $this->float($row['ClAnimalGaz'] ?? null),
            'glycemia' => $this->float($row['GlycemieAnimalGaz'] ?? null),
            'treatment' => $this->str($row['traitementAnimalGaz'] ?? null) ?? '',
            'perfusions' => [
                'bica_iso_1l' => $this->float($row['BicaIso1l'] ?? null) ?? 0,
                'speciale' => $this->float($row['Speciale'] ?? null) ?? 0,
                'carbi' => $this->float($row['Carbi'] ?? null) ?? 0,
                'dhydrat' => $this->float($row['Dhydrat'] ?? null) ?? 0,
                'lodevil' => $this->float($row['Lodevil'] ?? null) ?? 0,
                'glucose_5_1l' => $this->float($row['Glucose51l'] ?? null) ?? 0,
                'glucose_30_100ml' => $this->float($row['Glucose30100ml'] ?? null) ?? 0,
                'nacl_10_100ml' => $this->float($row['NaCl10100ml'] ?? null) ?? 0,
                'ringer_1l' => $this->float($row['Ringer1l'] ?? null) ?? 0,
                'nacl_1l' => $this->float($row['NaCl1l'] ?? null) ?? 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cellCountPayload(array $row): array
    {
        $sampleCount = max(1, min(5, $this->int($row['nombreEchantilloncellules'] ?? null) ?? 1));
        $samples = [];

        for ($i = 1; $i <= $sampleCount; $i++) {
            $samples[] = [
                'name' => $this->str($row["identificationEchantillonTestscellules{$i}"] ?? null) ?? "Echantillon {$i}",
                'count' => $this->float($row["comptageCellulaire{$i}"] ?? null),
            ];
        }

        return [
            'species' => $this->species($row['especeTestscellules'] ?? null),
            'sample_nature' => $this->str($row['natureEchantillonTestscellules'] ?? null) ?? '',
            'sample_count' => $sampleCount,
            'commemoratives' => $this->str($row['nomCommemoratifTestscellules'] ?? null) ?? '',
            'samples' => $samples,
            'comments' => $this->str($row['commentairesTestscellules'] ?? null) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bacteriologyPayload(array $row): array
    {
        $germCount = max(1, min(2, $this->int($row['nombreGermeBacterio'] ?? null) ?? 1));
        $germs = [];
        $antibiotics = [
            'AMX' => 'amoxicilline',
            'AMC' => 'amoxicillineAc',
            'GM' => 'gentamicine15u',
            'GEN' => 'gentamicine500u',
            'K' => 'kanamycine',
            'NEO' => 'neomycine',
            'CN' => 'cefalexine',
            'FOX' => 'cefoxitine',
            'XNL' => 'ceftiofur',
            'CXM' => 'cefuroxime',
            'CEQ' => 'cefquinome',
            'L' => 'lincomycine',
            'E' => 'erythromycine',
            'SP' => 'spiramycine',
            'TY' => 'tylosine',
            'P' => 'penicilline',
            'OX' => 'oxacilline',
            'FFC' => 'florfenicol',
            'NA' => 'acnalidixique',
            'ENR' => 'enrofloxacine',
            'MAR' => 'marbofloxacine',
            'SXT' => 'trimethosulfa',
            'TE' => 'tetracycline',
            'CT' => 'colistine',
            'TIP' => 'tildipirosine',
            'DAN' => 'danofloxacine',
            'CPR' => 'cefapirine',
            'CZN' => 'cefazoline',
            'CNM' => 'cefalonium',
            'RIF' => 'rifamycine',
            'BLA' => 'blactamase',
            'PS' => 'penistrepto',
            'PIR' => 'pirlimicine',
            'CFK' => 'cefakana',
            'NBT' => 'nbt',
            'NDP' => 'ndp',
        ];

        for ($i = 1; $i <= $germCount; $i++) {
            $values = [];

            foreach ($antibiotics as $code => $legacyPrefix) {
                $value = $this->float($row["{$legacyPrefix}{$i}"] ?? null);

                if ($value !== null) {
                    $values[$code] = $value;
                }
            }

            $germs[] = [
                'family' => $this->str($row["familleGermeBacterio{$i}"] ?? null) ?? 'Autre',
                'antibiotics' => $values,
            ];
        }

        return [
            'species' => $this->species($row['especeBacterio'] ?? null),
            'sample_nature' => $this->str($row['natureEchantillonBacterio'] ?? null) ?? '',
            'sample_identification' => $this->str($row['identificationEchantillonBacterio'] ?? null) ?? '',
            'commemoratives' => $this->str($row['nomCommemoratifBacterio'] ?? null) ?? '',
            'germ_count' => $germCount,
            'germs' => $germs,
            'advice' => $this->str($row['conseilsBacterio'] ?? null) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function miscAnalysisPayload(array $row): array
    {
        $count = max(1, min(6, $this->int($row['nbAnalysesDiverses'] ?? null) ?? 1));
        $analyses = [];

        for ($i = 1; $i <= $count; $i++) {
            $analyses[] = [
                'type' => $this->str($row["typeAnalysesDiverses{$i}"] ?? null) ?? '',
                'results' => $this->str($row["resultatsAnalysesDiverses{$i}"] ?? null) ?? '',
            ];
        }

        return [
            'species' => $this->species($row['especeAnalysesDiverses'] ?? null),
            'sample_count' => $count,
            'commemoratifs' => $this->str($row['commemoratifsAnalysesDiverses'] ?? null) ?? '',
            'analyses' => $analyses,
            'commentaires' => $this->str($row['commentairesAnalysesDiverses'] ?? null) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rapidTestsPayload(array $row): array
    {
        return [
            'species' => $this->species($row['especeTestsRapides'] ?? null),
            'sample_nature' => $this->str($row['natureEchantillonTestsRapides'] ?? null) ?? '',
            'identification' => $this->str($row['identificationEchantillonTestsRapides'] ?? null) ?? '',
            'commemoratifs' => $this->str($row['nomCommemoratifTestsRapides'] ?? null) ?? '',
            'elisa' => $this->mappedResults($row, [
                'parvovirus_cn' => 'parvovirusAgCN',
                'coronavirus_cn' => 'coronavirusAgCN',
                'giardia_cn' => 'giardiaAgCN',
                'leptospirose_cn' => 'leptospiroseIgMCN',
                'ehrlichiose_cn' => 'ehrlichioseAcCN',
                'leishmaniose_cn' => 'leishmanioseAcCN',
                'parvovirose_cn' => 'parvoviroseAcCN',
                'dirofilariose_cn' => 'dirofilarioseAgCN',
                'maladie_carre_cn' => 'maladieCarreAgCN',
                'adenovirus_cn' => 'adenovirusAgCN',
                'maladie_lyme_cn' => 'maladieLymeAcCN',
                'felv_ct' => 'felvAgCT',
                'fiv_ct' => 'fivAcCT',
                'giardia_ct' => 'giardiaAgCT',
                'coronavirus_ac_ct' => 'coronavirusAcCT',
                'panleucopenie_ct' => 'panleucopenieAgCT',
                'dirofilariose_ct' => 'dirofilarioseAgCT',
                'rotavirus_bv' => 'RotavirusAgBV',
                'coronavirus_bv' => 'CoronavirusAgBV',
                'ecoli_k99' => 'EcoliK99',
                'ecoli_cs31a' => 'EcoliCS31A',
                'cryptosporidium_bv' => 'CryptosporidiumAg',
                'bvd_ag' => 'BVDAg',
                'angiostrongylose_cn' => 'AngiostrongyloseAgCN',
                'cpl_cn' => 'cPLCN',
                'fpl_ct' => 'fPLCT',
                'anaplasmose_cn' => 'AnaplasmoseAcCN',
                'chlamydophila_ct' => 'ChlamydophilaAgCT',
                'rsv_bv' => 'RSVAgBV',
                'giardia_eq' => 'giardiaAgCV',
                'igg_poulain' => 'FoalIgGCV',
                'babesia_equi' => 'BabesiaEqui',
                'borrelia_eq' => 'Borrelia',
                'anaplasma_eq' => 'AnaplasmaCV',
                'leptospira_eq' => 'LeptospiraCV',
            ]),
            'biochem_rapide' => $this->mappedResults($row, [
                'glycemie' => 'glycemieSG',
                'cetones' => 'corpscetoniqueSG',
                'uree' => 'ureeSG',
                'lactate' => 'lactateSG',
                'igg_colostrum_q' => 'IgGColostrum',
                'igg_sg_veau_q' => 'IgGSgVeau',
                't4' => 'T4CN',
                'acides_biliaires' => 'AcidesBiliairesCN',
                'cortisol' => 'CortisolCN',
            ]),
            'pcr' => [],
            'bandelette' => $this->mappedResults($row, [
                'densite' => 'DensiteUrinaireBAN',
                'ph' => 'pHBAN',
                'leucocytes' => 'LeucocytesBAN',
                'nitrite' => 'NitriteBAN',
                'proteine' => 'ProteineBAN',
                'glucose' => 'GlucoseBAN',
                'cetone' => 'CetoneBAN',
                'urobilinogene' => 'UrobilinogeneBAN',
                'bilirubine' => 'BilirubineBAN',
                'sang' => 'SangBAN',
                'hemoglobine' => 'HemoglobineBAN',
            ]),
            'frottis' => $this->mappedResults($row, [
                'babesia_canis' => 'BabesiaCanis',
                'hemobartonnella_felis' => 'HemobartonnellaFelis',
                'ehrlichia_canis' => 'EhrlichiaCanis',
                'dirofilaria_immitis' => 'DirofilariaImmitis',
                'hepatozoon_canis' => 'HepatozoonCanis',
                'babesia_bovis' => 'BabesiaBovis',
                'anaplasma_phago' => 'AnaplasmaPhagocytophilumBV',
                'babesia_equi' => 'BabesiaEqui',
                'borrelia' => 'Borrelia',
                'anaplasma_cv' => 'AnaplasmaCV',
            ]),
            'commentaires' => $this->str($row['commentairesTestsRapides'] ?? null) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function biochimiePayload(array $row): array
    {
        $params = $this->mappedNumericResults($row, [
            'ALB' => 'ALBbioch',
            'ALKP' => 'ALKPbioch',
            'ALT' => 'ALTbioch',
            'AMYL' => 'AMYLbioch',
            'AST' => 'ASTbioch',
            'UREE' => 'UREEbioch',
            'Ca' => 'Cabioch',
            'CHOL' => 'CHOLbioch',
            'CK' => 'CKbioch',
            'Cl' => 'Clbioch',
            'CREA' => 'CREAbioch',
            'UREA_CREA' => 'UREECREAbioch',
            'FRU' => 'FRUbioch',
            'GGT' => 'GGTbioch',
            'GLOB' => 'GLOBbioch',
            'ALB_GLOB' => 'ALBGLOBbioch',
            'GLU' => 'GLUbioch',
            'K' => 'Kbioch',
            'LAC' => 'LACbioch',
            'LDH' => 'LDHbioch',
            'LIPA' => 'LIPAbioch',
            'Mg' => 'Mgbioch',
            'Na' => 'Nabioch',
            'Na_K' => 'NaKbioch',
            'NH3' => 'NH3bioch',
            'OSMOL' => 'Osmolalitebioch',
            'PHBR' => 'PHBRbioch',
            'PHOS' => 'PHOSbioch',
            'TBIL' => 'TBILbioch',
            'TP' => 'TPbioch',
            'TRIG' => 'TRIGbioch',
            'UCREA' => 'UCREAbioch',
            'UPC' => 'UPCbioch',
            'UPRO' => 'UPRObioch',
            'URIC' => 'URICbioch',
        ]);

        return [
            'species' => $this->species($row['especeBioch'] ?? null),
            'sample_nature' => $this->str($row['natureEchantillonBioch'] ?? null) ?? '',
            'identification' => $this->str($row['identificationEchantillonBioch'] ?? null) ?? '',
            'commemoratifs' => $this->str($row['nomCommemoratifBioch'] ?? null) ?? '',
            'params' => $params,
            'commentaires' => $this->str($row['commentairesBioch'] ?? null) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hemogrammePayload(array $row): array
    {
        $params = [];

        foreach (['GR', 'HCT', 'HGB', 'VGM', 'TCMH', 'CCMH', 'IDR', 'PRETIC', 'RETIC', 'GB', 'PNEU', 'PLYM', 'PMONO', 'PEOS', 'PBASO', 'NEU', 'LYM', 'MONO', 'EOS', 'BASO', 'PLT', 'VMP', 'IDP', 'PCT', 'PGRA', 'GRA'] as $key) {
            $value = $this->float($row["hemato{$key}"] ?? null);

            if ($value !== null) {
                $params[$key] = $value;
            }
        }

        return [
            'species' => $this->species($row['especeHemato'] ?? null),
            'sample_nature' => $this->str($row['natureEchantillonHemato'] ?? null) ?? '',
            'identification' => $this->str($row['identificationEchantillonHemato'] ?? null) ?? '',
            'commemoratifs' => $this->str($row['nomCommemoratifHemato'] ?? null) ?? '',
            'params' => $params,
            'commentaires' => $this->str($row['commentairesHemato'] ?? null) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function autopsyPayload(array $row): array
    {
        return [
            'identification' => $this->str($row['identificationAutopsie'] ?? null) ?? '',
            'species' => $this->species($row['especeAutopsie'] ?? null),
            'sexe' => $this->str($row['sexeAutopsie'] ?? null) ?? '',
            'conformation' => $this->str($row['conformationAutopsie'] ?? null) ?? '',
            'conservation' => $this->str($row['conservationAutopsie'] ?? null) ?? '',
            'engraissement' => $this->str($row['engraissementAutopsie'] ?? null) ?? '',
            'poids' => $this->float($row['poidsAutopsie'] ?? null),
            'commemoratifs' => $this->str($row['commemoratifsAutopsie'] ?? null) ?? '',
            'lesions' => $this->str($row['commentairesAutopsie'] ?? null) ?? '',
            'conclusion' => $this->str($row['resultatsAutopsie'] ?? null) ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(array $row): array
    {
        $pages = [];
        $count = max(1, min(10, $this->int($row['nbDePages'] ?? null) ?? 1));

        for ($i = 1; $i <= $count; $i++) {
            $pages[] = $this->strWithBreaks($row["resultatsCompteRendu{$i}"] ?? null) ?? '';
        }

        return [
            'pages' => $pages,
            'nb_pages' => $count,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function bseLaitierPayload(array $row, array $settings): array
    {
        $payload = VeterinaryModules::payloadTemplate('bse-laitier', $settings);

        $this->copyNumbers($payload, $row, [
            'annee_reference' => 'anneeReference',
            'nb_vaches_productrices' => 'nbVachesProductrices',
            'ivv' => 'IVV',
            'concentration_cellulaire_moyen' => 'concentrationCellulaireMoyen',
            'production_annuelle_lait' => 'productionAnnuelleLait',
            'prix_lait_tonne' => 'prixLaitTonne',
            'tx_butyreux_moyen' => 'txButyreuxMoyen',
            'tx_proteique_moyen' => 'txProteiqueMoyen',
            'nb_veaux_nes_vivants' => 'nbVeauxNesVivants',
            'nb_avortons' => 'nbAvortons',
            'nb_jumeaux' => 'nbJumeaux',
            'prix_veaux_male' => 'prixVeauxMale',
            'prix_veaux_femelle' => 'prixVeauxFemelle',
            'ha_foin' => 'haFoin',
            'ha_ensilage_herbe' => 'haEnsilageHerbe',
            'ha_ensilage_mais' => 'haEnsilageMais',
            'production_cereales_tonnes' => 'productionCerealesTonnes',
            'achat_cereales_tonnes' => 'achatCerealesTonnes',
            'achat_cereales_euros' => 'achatCerealesEuros',
            'achat_complementaire_tonnes' => 'achatComplementaireTonnes',
            'achat_complementaire_euros' => 'achatComplementaireEuros',
            'achat_amv_euros' => 'achatAMVEuros',
            'nb_mammites_locales' => 'nbMammitesLocales',
            'nb_mammites_locales_non_gueries' => 'nbMammitesLocalesNonGueries',
            'nb_mammites_aigues' => 'nbMammitesAigues',
            'nb_mammites_aigues_non_gueries' => 'nbMammitesAiguesNonGueries',
            'nb_cci250' => 'nbCCI250',
            'nb_boiteries' => 'nbBoiteries',
            'nb_boiteries_non_gueries' => 'nbBoiteriesNonGueries',
            'nb_fievres_de_lait' => 'nbFievresDeLait',
            'nb_fievres_de_lait_non_gueries' => 'nbFievresDeLaitNonGueries',
            'nb_non_delivrances' => 'nbNonDelivrances',
            'nb_metrites' => 'nbMetrites',
            'nb_caillettes' => 'nbCaillettes',
            'nb_caillettes_non_gueries' => 'nbCaillettesNonGueries',
            'nb_cetoses' => 'nbCetoses',
            'nb_acidoses' => 'nbAcidoses',
            'nb_malades_0a7' => 'nbMalades0a7',
            'nb_morts_0a7' => 'nbMorts0a7',
            'nb_malades_8a_sevr' => 'nbMalades8aSevr',
            'nb_morts_8a_sevr' => 'nbMorts8aSevr',
            'nb_ivia1' => 'nbIVIA1',
            'nb_iviaf' => 'nbIVIAF',
            'tx_reussite_ia1' => 'txReussiteIA1',
            'tx_ia3' => 'txIA3',
        ]);

        $payload['race'] = $this->str($row['race'] ?? null) ?? $payload['race'];
        $payload['boolean_depistage_metrite'] = $this->bool($row['booleanDepistageMetrite'] ?? null);
        $payload['commentaire'] = $this->legacyPlainText($row['commentaireBSE'] ?? null);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function bseAllaitantPayload(array $row, array $settings): array
    {
        $payload = VeterinaryModules::payloadTemplate('bse-allaitant', $settings);

        $this->copyNumbers($payload, $row, [
            'annee_reference' => 'anneeReference',
            'nb_vaches_reproductrices' => 'nbVachesReproductrices',
            'ivv' => 'IVV',
            'nb_veaux_nes_vivants' => 'nbVeauxNesVivants',
            'nb_jumeaux' => 'nbJumeaux',
            'nb_accidents_velage' => 'nbAccidentsVelage',
            'nb_avortons' => 'nbAvortons',
            'nb_morts_post24h' => 'nbMortsPost24h',
            'nb_sevres' => 'nbSevres',
            'nb_malades_diar1' => 'nbMaladesDiar1',
            'nb_morts_diar1' => 'nbMortsDiar1',
            'nb_malades_diar2et3' => 'nbMaladesDiar2Et3',
            'nb_morts_diar2et3' => 'nbMortsDiar2Et3',
            'nb_malades_diar4' => 'nbMaladesDiar4',
            'nb_morts_diar4' => 'nbMortsDiar4',
            'nb_diar_perf' => 'nbDiarPerf',
            'nb_malades_respi' => 'nbMaladesRespi',
            'nb_morts_respi' => 'nbMortsRespi',
            'nb_malades_omphalite' => 'nbMaladesOmphalite',
            'nb_morts_omphalite' => 'nbMortsOmphalite',
            'nb_malades_autres' => 'nbMaladesAutres',
            'nb_morts_autres' => 'nbMortsAutres',
            'nb_morts_subites' => 'nbMortsSubites',
            'nb_morts_avant3_mois' => 'nbMortsAvant3Mois',
            'nb_velages_longs' => 'nbVelagesLongs',
            'nb_cesariennes' => 'nbCesariennes',
            'nb_non_delivrances' => 'nbNonDelivrances',
            'nb_torsions_retournements_matrices' => 'nbTorsionsRetournementsMatrices',
            'nb_metrites' => 'nbMetrites',
            'ha_foin' => 'haFoin',
            'ha_ensilage_herbe' => 'haEnsilageHerbe',
            'ha_ensilage_mais' => 'haEnsilageMais',
            'production_cereales_tonnes' => 'productionCerealesTonnes',
            'achat_cereales_tonnes' => 'achatCerealesTonnes',
            'achat_cereales_euros' => 'achatCerealesEuros',
            'achat_complementaire_tonnes' => 'achatComplementaireTonnes',
            'achat_complementaire_euros' => 'achatComplementaireEuros',
            'achat_amv_euros' => 'achatAMVEuros',
        ]);

        $payload['race'] = $this->str($row['race'] ?? null) ?? $payload['race'];
        $payload['commentaire'] = $this->legacyPlainText($row['commentaireBSE'] ?? null);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $map
     */
    private function copyNumbers(array &$payload, array $row, array $map): void
    {
        foreach ($map as $target => $source) {
            $value = $this->float($row[$source] ?? null);

            if ($value !== null) {
                $payload[$target] = $value;
            }
        }
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private function mappedResults(array $row, array $map): array
    {
        $results = [];

        foreach ($map as $target => $source) {
            $value = $this->str($row[$source] ?? null);

            if ($value !== null && $value !== '0') {
                $results[$target] = $value;
            }
        }

        return $results;
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, float>
     */
    private function mappedNumericResults(array $row, array $map): array
    {
        $results = [];

        foreach ($map as $target => $source) {
            $value = $this->float($row[$source] ?? null);

            if ($value !== null) {
                $results[$target] = $value;
            }
        }

        return $results;
    }

    private function animalName(array $row, string $module): ?string
    {
        if ($module === 'gaz-du-sang') {
            $name = $this->str($row['nomAnimalGaz'] ?? null);

            if ($name !== null) {
                return $name;
            }
        }

        $legacyId = $this->legacyId($row, 'idAnimal');

        return $legacyId !== '' ? ($this->animalNames[$legacyId] ?? null) : null;
    }

    private function insertMapping(string $sourceTable, string $legacyId, string $targetTable, ?int $targetId, array $metadata = []): void
    {
        DB::table(self::MAPPING_TABLE)->insert([
            'import_token' => $this->importToken,
            'source_table' => $sourceTable,
            'legacy_id' => $legacyId,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function applyArchiveTimestamps(Model $model, Carbon $timestamp): void
    {
        $model->timestamps = false;
        $model->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();
        $model->timestamps = true;
    }

    private function legacyId(array $row, string $key): string
    {
        $value = $this->str($row[$key] ?? null);

        return $value === '0' ? '' : ($value ?? '');
    }

    private function firstString(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->str($row[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = LegacyHtmlCleaner::plainText((string) $value);

        return $this->isBlankLegacyString($string) ? null : $string;
    }

    private function strWithBreaks(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = LegacyHtmlCleaner::plainTextWithBreaks((string) $value);

        return $this->isBlankLegacyString($string) ? null : $string;
    }

    private function isBlankLegacyString(string $value): bool
    {
        $string = trim($value);

        return $string === '' || strtolower($string) === 'null';
    }

    private function int(mixed $value): ?int
    {
        $float = $this->float($value);

        return $float === null ? null : (int) $float;
    }

    private function float(mixed $value): ?float
    {
        $string = $this->str($value);

        if ($string === null) {
            return null;
        }

        $normalized = str_replace(',', '.', $string);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function bool(mixed $value): bool
    {
        $string = strtolower((string) ($this->str($value) ?? ''));

        return in_array($string, ['1', 'true', 'oui', 'yes', 'on'], true);
    }

    private function result(mixed $value): string
    {
        return $this->str($value) ?? '0';
    }

    private function date(mixed $value): ?string
    {
        return $this->dateCarbon($value)?->toDateString();
    }

    private function dateCarbon(mixed $value): ?Carbon
    {
        $string = $this->str($value);

        if ($string === null || $string === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($string)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function archiveDate(array $row): ?Carbon
    {
        return $this->dateCarbon($row['dateAnalyse'] ?? null)
            ?? $this->dateCarbon($row['datePrelevement'] ?? null);
    }

    private function earliestDate(?Carbon $current, Carbon $candidate): Carbon
    {
        return $current === null || $candidate->lt($current) ? $candidate->copy() : $current;
    }

    private function timestampForBreeder(array $row): Carbon
    {
        $legacyId = $this->legacyId($row, 'idEleveur');

        if ($legacyId !== '' && isset($this->breederArchiveDates[$legacyId])) {
            return $this->breederArchiveDates[$legacyId]->copy();
        }

        return $this->fallbackArchiveTimestamp();
    }

    private function timestampForAliment(array $row): Carbon
    {
        $legacyBreederId = $this->legacyId($row, 'idEleveur');

        if ($legacyBreederId !== '' && isset($this->breederArchiveDates[$legacyBreederId])) {
            return $this->breederArchiveDates[$legacyBreederId]->copy();
        }

        return $this->fallbackArchiveTimestamp();
    }

    private function timestampForAnalysis(array $row): Carbon
    {
        return $this->archiveDate($row) ?? $this->fallbackArchiveTimestamp();
    }

    private function fallbackArchiveTimestamp(): Carbon
    {
        return $this->globalArchiveDate?->copy() ?? Carbon::create(1970, 1, 1)->startOfDay();
    }

    private function species(mixed $value): string
    {
        $species = mb_strtolower($this->str($value) ?? '');

        return match (true) {
            str_contains($species, 'bovin') => 'Bovin',
            str_contains($species, 'ovin') => 'Ovin',
            str_contains($species, 'caprin') => 'Caprin',
            str_contains($species, 'equin'), str_contains($species, 'cheval') => 'Equin',
            str_contains($species, 'chien'), str_contains($species, 'canin') => 'Chien',
            str_contains($species, 'chat'), str_contains($species, 'felin') => 'Chat',
            default => 'Bovin',
        };
    }

    private function firstNumericColumn(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $this->float($row[$key] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array{breeders: int, aliments: int, analyses: array<string, int>}  $stats
     */
    private function printStats(array $stats, bool $dryRun): void
    {
        $this->line($dryRun ? 'Simulation terminee.' : 'Import termine.');
        $this->line("Eleveurs: {$stats['breeders']}");
        $this->line("Aliments: {$stats['aliments']}");

        foreach ($stats['analyses'] as $module => $count) {
            $this->line("Analyses {$module}: {$count}");
        }

        $this->line('Rations: 0 (ignorees volontairement)');
        $this->line('Logs et comptes legacy: 0 (ignores volontairement)');
    }
}

#!/bin/sh
set -eu

if [ "${1:-}" = "--help" ] || [ "${1:-}" = "-h" ]; then
    echo "Usage : ./comparer-rations.sh [dossier-de-sortie]"
    echo "Compare les 10 rations Larelev en direct et en mélange (20 cas)."
    echo "Rapports HTML et Markdown dans storage/app/comparaisons-rations/<date> par défaut."
    echo "Codes de sortie : 0 = conforme ; 1 = écarts/tests en échec ; 2 = rapport incomplet/erreur."
    exit 0
fi
if [ "$#" -gt 1 ]; then
    echo "Usage : ./comparer-rations.sh [dossier-de-sortie]" >&2
    exit 2
fi

project_directory=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
if ! command -v php >/dev/null 2>&1 || [ ! -f "$project_directory/vendor/autoload.php" ]; then
    echo "PHP et les dépendances Composer du projet sont nécessaires." >&2
    exit 2
fi

report_directory=${1:-"$project_directory/storage/app/comparaisons-rations/$(TZ=Europe/Paris date +%Y-%m-%d_%H-%M-%S)-$$"}
case "$report_directory" in
    /*) ;;
    *) report_directory="$PWD/$report_directory" ;;
esac
mkdir -p "$report_directory"
report_directory=$(CDPATH= cd -- "$report_directory" && pwd)
if [ -e "$report_directory/cas" ] || [ -e "$report_directory/tests.log" ]; then
    echo "Ce dossier contient déjà une exécution. Choisis un nouveau dossier : $report_directory" >&2
    exit 2
fi
mkdir "$report_directory/cas"
cd "$project_directory"

echo "Comparaison des 10 rations, en direct et en mélange (environ 2 minutes)…"
echo "Progression détaillée : $report_directory/tests.log"
test_status=0
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
    APP_CONFIG_CACHE="$report_directory/config-non-cachee.php" \
    RATION_COMPARISON_REPORT_DIR="$report_directory/cas" \
    php artisan test --compact --colors=never \
        tests/Unit/ReferenceRationFixtureTest.php \
        tests/Unit/InraFeedComplementsTest.php \
        tests/Feature/InraCompletedRationParityTest.php \
        --log-junit="$report_directory/tests.xml" \
        > "$report_directory/tests.log" 2>&1 || test_status=$?

report_status=0
php tests/Support/generate-ration-report.php "$report_directory" "$test_status" || report_status=$?
echo "Rapport à ouvrir : $report_directory/rapport.html"
echo "Rapport à partager : $report_directory/rapport.md"
if [ "$report_status" -ne 0 ]; then
    exit 2
fi
if [ "$test_status" -ne 0 ]; then
    echo "Des tests signalent des écarts ou une erreur (code $test_status). Détail dans tests.log."
fi
exit "$test_status"

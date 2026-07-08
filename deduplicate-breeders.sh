#!/bin/sh
set -eu

if ! command -v kamal >/dev/null 2>&1; then
    echo "Erreur: la commande 'kamal' est introuvable." >&2
    exit 1
fi

if [ ! -f "config/deploy.yml" ]; then
    echo "Erreur: config/deploy.yml introuvable. Lance ce script depuis la racine du projet (app/)." >&2
    exit 1
fi

MODE="${1:-}"

if [ "$MODE" = "--force" ]; then
    echo "ATTENTION : fusion REELLE des eleveurs en double sur la PRODUCTION (nexa.vethorizons.fr)."
    echo "Les doublons seront supprimes apres repointage de leurs analyses et plans de rationnement."
    printf "Tape 'oui' pour confirmer : "
    read -r ANSWER
    if [ "$ANSWER" != "oui" ]; then
        echo "Annule."
        exit 1
    fi
    echo "Application de la fusion via Kamal..."
    kamal app exec --primary --reuse 'php artisan breeders:deduplicate --force'
elif [ -z "$MODE" ]; then
    echo "Simulation (dry-run) via Kamal - aucune donnee ne sera modifiee."
    kamal app exec --primary --reuse 'php artisan breeders:deduplicate'
else
    echo "Usage: $0 [--force]" >&2
    echo "  (sans argument) : simulation (dry-run), affiche les fusions prevues" >&2
    echo "  --force         : applique reellement la fusion (avec confirmation)" >&2
    exit 1
fi

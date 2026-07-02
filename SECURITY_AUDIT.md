# Audit de sécurité — Nexa (Ration)

**Date :** 2 juillet 2026
**Périmètre :** application Laravel 13 + Vue 3 / Inertia v2 (répertoire `app/` du projet), configuration de déploiement Kamal, dépendances Composer et npm.
**Méthode :** revue manuelle du code (routes, contrôleurs, form requests, policies, modèles, middlewares, templates Blade PDF, composants Vue), revue de la configuration (Fortify, session, Docker, Caddy, Kamal), `composer audit` et `npm audit`.

---

## Synthèse

L'application est globalement **saine** : les fondations sécurité sont bien posées (autorisations systématiques, validation par Form Requests, requêtes SQL paramétrées, mots de passe robustes, 2FA, debug désactivé en production). Aucune vulnérabilité critique exploitable à distance par un anonyme n'a été identifiée.

Les actions prioritaires concernent : **la mise à jour des dépendances** (CVE connues, dont une injection CRLF dans Laravel ≤ 13.9 et une injection SMTP dans symfony/mime), **l'usage de `v-html` sur des données saisies par l'utilisateur** (XSS stocké latent) et **le durcissement des cookies/en-têtes HTTP en production**.

| Domaine | État |
|---|---|
| Autorisations / IDOR | ✅ Bon |
| Injection SQL | ✅ Bon |
| Mass assignment | ✅ Bon |
| Authentification (Fortify) | ✅ Bon |
| XSS | ✅ Corrigé (2 juil. 2026) |
| Dépendances | ✅ Mises à jour (2 juil. 2026) |
| Config production (cookies, en-têtes) | ✅ Corrigé (2 juil. 2026) |
| Gestion des secrets | ⚠️ Points d'attention |

---

## Suivi des correctifs — 2 juillet 2026

| Constat | Statut | Correctif |
|---|---|---|
| 1.1 / 1.2 Dépendances vulnérables | ✅ Corrigé | `composer update` + `npm audit fix` (par l'équipe) |
| 2.1 XSS `v-html` | ✅ Corrigé | Interpolation `{{ }}` + `whitespace-pre-line` dans les 5 pages d'analyses |
| 2.2 Cookie sans flag Secure | ✅ Corrigé | `SESSION_SECURE_COOKIE: true` dans `config/deploy.yml` (effectif au prochain `kamal deploy`) |
| 2.3 En-têtes de sécurité | ✅ Corrigé | HSTS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` dans le `Caddyfile` |
| 2.4 Chromium no-sandbox / conteneur root | ⏳ Reporté | Nécessite un test de déploiement dédié (port < 1024, sandbox Chromium sous Docker) — voir § 2.4 |
| 2.5 Rate limiting endpoints coûteux | ✅ Corrigé | `throttle:10,1` (PDF), `throttle:5,1` (imports), `throttle:30,1` (AgriNIR) dans `routes/web.php` |
| 3.1 Secrets en clair | ⚠️ Point d'attention | Recommandation inchangée : gestionnaire de secrets pour `.kamal/secrets` |
| 3.2 Registration ouverte | ⚠️ Décision produit | À trancher (ouverte vs invitation) |
| 3.3 `User` complet partagé via Inertia | ✅ Corrigé | Liste blanche de 6 champs dans `HandleInertiaRequests` |
| 3.4 `trustProxies('*')` | ✅ Corrigé | Restreint aux plages privées (RFC 1918) dans `bootstrap/app.php` |
| 3.5 CSV d'import conservés | ✅ Corrigé | Lecture directe du fichier uploadé, plus aucun stockage (corrige aussi un chemin `storage/app/` erroné) |
| 3.6 Wildcards LIKE | ✅ Corrigé | Helper `App\Support\SearchTerm::likeContains()` + clause `ESCAPE` sur tous les LIKE bruts |
| 3.6 Incohérence 403/404 | ✅ Corrigé | `failedAuthorization()` → 404 dans `UpdateAnalysisRequest` et `UpdateBreederRequest` |

Correctifs couverts par `tests/Feature/SecurityHardeningTest.php` (6 tests). Suite complète : 380 tests, 0 échec.

---

## 1. Constats — sévérité ÉLEVÉE

### 1.1 Dépendances Composer avec vulnérabilités connues

`composer audit` remonte **19 advisories sur 10 paquets**, dont :

| Paquet | Version installée | Vulnérabilité | Sévérité |
|---|---|---|---|
| `laravel/framework` | v13.4.0 | CVE-2026-48019 — injection CRLF dans la règle de validation `email` par défaut (affecte ≤ 13.9.0) | Haute |
| `symfony/mime` | v8.0.8 | CVE-2026-45067 — injection d'en-têtes e-mail / commandes SMTP via CRLF | Haute |
| `guzzlehttp/guzzle` / `guzzlehttp/psr7` | 7.10.0 / 2.9.0 | 5 advisories | Moyenne |
| `symfony/http-foundation`, `http-kernel`, `mailer`, `routing`, `yaml`, `polyfill-intl-idn` | — | 8 advisories | Faible→Moyenne |

**Risque concret :** l'application accepte des adresses e-mail en entrée (inscription, profil, éleveurs) et envoie des e-mails (vérification, réinitialisation de mot de passe). La combinaison CRLF dans la règle `email` + injection SMTP dans symfony/mime est exactement le scénario visé par ces CVE.

**Action :**
```bash
composer update
composer audit   # vérifier qu'il ne reste rien
```

### 1.2 Dépendances npm avec vulnérabilités connues

`npm audit` : **13 vulnérabilités (2 critiques, 4 hautes)**. Les critiques (`shell-quote` via `concurrently`) et `vite`/`launch-editor` sont **dev-only** (pas embarquées dans le bundle de production), mais `axios`, `form-data` et `linkify-it` sont des dépendances transitives non-dev.

**Action :**
```bash
npm audit fix
npm audit        # contrôler le reste
```

---

## 2. Constats — sévérité MOYENNE

### 2.1 XSS stocké latent via `v-html` sur des champs libres utilisateur

Plusieurs pages d'analyses rendent des champs saisis librement par l'utilisateur (`payload.commentaires`, `payload.advice_preventive`, `payload.advice_curative`) avec `v-html`, sans aucune sanitisation :

- `resources/js/pages/analyses/tests-biochimie/Show.vue:208`
- `resources/js/pages/analyses/tests-rapides/Show.vue:219`
- `resources/js/pages/analyses/hemogramme/Show.vue:219`
- `resources/js/pages/analyses/diarrhee-neonatale/Show.vue:212,216`
- `resources/js/pages/analyses/coproscopie-parasitaire/Show.vue:202,206`

Le champ `payload` est validé comme simple `array` (`app/Http/Requests/StoreAnalysisRequest.php`) et stocké tel quel : tout HTML/JS injecté est persisté puis exécuté au rendu.

**Aujourd'hui**, seule la personne propriétaire voit ses propres analyses (self-XSS, impact limité). **Mais** le jour où une analyse devient visible par un tiers (partage, vue admin, support à distance), cela devient un XSS stocké classique permettant le vol de session/actions à l'insu de la victime.

**Action :** remplacer `v-html` par une interpolation texte (`{{ }}`) avec `white-space: pre-line` pour conserver les retours à la ligne, ou sanitiser avec DOMPurify si du HTML riche est réellement nécessaire. À noter : le template PDF fait déjà la bonne chose (`{{ $plainTextWithBreaks(...) }}` + `LegacyHtmlCleaner`).

Cas à part, correct : `TwoFactorSetupModal.vue:173` (`v-html="qrCodeSvg"` — SVG généré par Fortify, source de confiance) et la pagination `aliments/Index.vue` (labels générés par Laravel).

### 2.2 Cookie de session sans flag `Secure` en production

`config/session.php:172` : `'secure' => env('SESSION_SECURE_COOKIE')` — et `SESSION_SECURE_COOKIE` n'est défini nulle part dans `config/deploy.yml`. Le cookie de session (et le cookie XSRF) peut donc être transmis sur une requête HTTP en clair (première requête avant redirection HTTPS, contenu mixte, etc.).

**Action :** ajouter dans `config/deploy.yml` (env clear) :
```yaml
SESSION_SECURE_COOKIE: true
```

### 2.3 Absence d'en-têtes de sécurité HTTP

Ni le `Caddyfile`, ni un middleware n'ajoutent `Strict-Transport-Security`, `X-Frame-Options` / `frame-ancestors`, `X-Content-Type-Options` ou de `Content-Security-Policy`.

**Action :** ajouter au `Caddyfile` :
```caddy
header {
    Strict-Transport-Security "max-age=31536000; includeSubDomains"
    X-Content-Type-Options "nosniff"
    X-Frame-Options "DENY"
    Referrer-Policy "strict-origin-when-cross-origin"
}
```
Une CSP est plus délicate avec Vite/Inertia mais vaut l'investissement à terme.

### 2.4 Chromium sans sandbox, conteneur exécuté en root

`Dockerfile` : `LARAVEL_PDF_NO_SANDBOX=true` et l'image FrankenPHP tourne en root (nécessaire justement parce que Chromium refuse le sandbox en root). Les PDF sont générés par un Chrome headless **sans sandbox** à partir de HTML contenant des données utilisateur.

Les templates Blade PDF échappent correctement les entrées (`{{ }}` partout où la donnée est utilisateur ; les `{!! $bar(...) !!}` de `resources/views/pdf/analysis.blade.php` ne reçoivent que des valeurs numériques formatées). La barrière est donc l'échappement Blade : si un jour un `{!! !!}` rend une donnée utilisateur, un attaquant pourra exécuter du JS dans un Chrome sans sandbox côté serveur (lecture de fichiers locaux type `.env` via `file://`, SSRF interne, voire pire sans sandbox).

**Action (défense en profondeur) :** exécuter le conteneur applicatif avec un utilisateur non-root et réactiver le sandbox Chromium (`LARAVEL_PDF_NO_SANDBOX=false`) ; maintenir la règle « jamais de `{!! !!}` sur une donnée utilisateur » dans les templates PDF.

### 2.5 Pas de rate limiting sur les endpoints coûteux

La génération PDF (`aliments/{id}/pdf`, `plans/.../pdf`, `analyses/{id}/pdf`) lance un Chrome headless à chaque requête ; les imports CSV et `agrinir/calculer` sont également coûteux. Aucun `throttle` n'est appliqué (seul `settings/password` en a un). Un compte authentifié (l'inscription est ouverte) peut saturer le serveur.

**Action :** appliquer `->middleware('throttle:...')` sur ces routes, p. ex. `throttle:10,1` pour les PDF et `throttle:5,1` pour les imports.

---

## 3. Constats — sévérité FAIBLE

### 3.1 Secrets en clair dans le répertoire du projet

- `.env.local` (à la racine de l'app) contient le **mot de passe Docker Hub** (`KAMAL_REGISTRY_PASSWORD`) et le **mot de passe de la base de production** en clair.
- `.kamal/secrets` contient l'ensemble des secrets de production.

Les deux sont bien **gitignorés et n'ont jamais été commités** (vérifié sur l'historique). C'est un fonctionnement Kamal standard, mais : ces fichiers sont lisibles par tout processus/outil s'exécutant sur la machine (y compris les assistants IA). Préférer l'intégration Kamal avec un gestionnaire de secrets (1Password, Bitwarden, LastPass — supporté nativement par `.kamal/secrets`), et faire tourner les mots de passe au moindre doute de fuite.

### 3.2 Registration ouverte à tous

`config/fortify.php` active `Features::registration()` : n'importe qui peut créer un compte sur `https://nexa.vethorizons.fr`. Pour un outil professionnel vétérinaire, envisager une inscription sur invitation ou une validation admin (le flag `is_admin` et l'écran admin existent déjà). Point de décision produit plus que vulnérabilité — la vérification d'e-mail et le rate limiting sont en place.

### 3.3 Objet `User` complet partagé avec le front

`app/Http/Middleware/HandleInertiaRequests.php:43` partage `$request->user()` entier sur chaque page. `$hidden` protège les champs sensibles (password, secrets 2FA), mais toute nouvelle colonne ajoutée à `users` sera exposée par défaut au client. Préférer une liste explicite (`only('id', 'name', 'email', 'is_admin', ...)`).

### 3.4 `trustProxies('*')`

`bootstrap/app.php:17` fait confiance à tous les proxys. Derrière kamal-proxy sur le même hôte c'est fonctionnel, mais si le port du conteneur applicatif devient joignable directement (règle firewall manquante), un client peut usurper son IP via `X-Forwarded-For` — ce qui affaiblit le rate limiting de login (clé basée sur e-mail + IP). Vérifier que le port de l'app n'est accessible que via le proxy (firewall/réseau Docker) ou restreindre aux IP du proxy.

### 3.5 Fichiers d'import conservés indéfiniment

`app/Http/Controllers/Admin/ImportController.php:26` stocke chaque CSV dans `storage/app/imports/` sans jamais les supprimer. Les CSV d'éleveurs contiennent des données personnelles (noms, adresses, téléphones, e-mails). Supprimer le fichier après import (`Storage::delete($path)`) — pertinence RGPD.

### 3.6 Divers (très faible)

- **Wildcards LIKE non échappés** dans les recherches (`%`, `_` dans `DashboardController`, `VeterinaryAnalysisController::applySearch`) : requêtes potentiellement lentes, pas d'injection (bindings corrects). Échapper avec `addcslashes($term, '%_\\')` si besoin.
- **Incohérence 403/404** : `UpdateAnalysisRequest::authorize()` renvoie 403 pour une analyse d'autrui alors que `ensureOwned()` renvoie 404 ailleurs — micro-oracle d'existence d'IDs. Harmoniser sur 404.
- **`favicon.ico` de 363 Ko** dans `public/` : pas un problème de sécurité, mais servie à chaque visiteur anonyme.

---

## 4. Points forts relevés

- **Autorisations solides et systématiques** : policies (`AlimentPolicy`, `PlanRationnementPolicy`, `MelangePolicy`) + `authorize()` dans les Form Requests (`UpdateAnalysisRequest`, `UpdateBreederRequest`) + scoping `where('user_id', ...)` sur toutes les listes + `Route::scopeBindings()` sur les routes imbriquées plans/rations/mélanges. Aucune IDOR trouvée sur les contrôleurs examinés ; la propriété du `breeder_id` et du `comparison_analysis_id` est même re-vérifiée à la validation.
- **Pas d'injection SQL** : tous les `whereRaw`/`selectRaw` utilisent des bindings ; les identifiants de colonnes passent par `$grammar->wrap()`.
- **Mass assignment maîtrisé** : `user_id`, `is_admin`, `code_inra` ne sont jamais assignés depuis la requête ; les `validated()` des Form Requests servent de liste blanche ; l'écran admin de modification d'utilisateur ne permet que `is_admin` et `email_verified_at`, derrière le middleware `EnsureUserIsAdmin`.
- **Authentification robuste** : mots de passe production `min(12) + mixedCase + numbers + symbols + uncompromised` (vérification HaveIBeenPwned), rate limiting login et 2FA (5/min), 2FA TOTP avec confirmation + mot de passe requis, vérification d'e-mail obligatoire (`verified`), throttle sur le changement de mot de passe, `current_password` exigé.
- **Production bien configurée** : `APP_DEBUG=false`, `APP_ENV=production`, logs en `stderr` niveau `error`, `DB::prohibitDestructiveCommands()` en production, HTTPS via kamal-proxy, healthcheck `/up` neutre.
- **PDF sûrs en l'état** : tout contenu utilisateur passe par `{{ }}` et `LegacyHtmlCleaner::plainText[WithBreaks]()`.
- **Uploads restreints** : `mimes:csv,txt` + taille max, imports admin derrière `EnsureUserIsAdmin`, parsing CSV sans exécution.
- **Aucun secret dans l'historique git** ; `.env*`, `.kamal/secrets` et `config/deploy.yml` correctement gitignorés ; Debugbar en `require-dev` uniquement.

---

## 5. Plan d'action recommandé (par priorité)

1. ~~**Immédiat** — `composer update` puis `composer audit` (CVE e-mail CRLF/SMTP), `npm audit fix`.~~ ✅ Fait (2 juil. 2026)
2. ~~**Cette semaine** — Supprimer les `v-html` sur `payload.*` (§ 2.1) ; ajouter `SESSION_SECURE_COOKIE: true` au déploiement (§ 2.2) ; ajouter les en-têtes de sécurité au Caddyfile (§ 2.3).~~ ✅ Fait (2 juil. 2026)
3. ~~**Court terme** — Rate limiting sur PDF/imports/calculs (§ 2.5) ; suppression des CSV après import (§ 3.5) ; liste explicite des champs `user` partagés via Inertia (§ 3.3).~~ ✅ Fait (2 juil. 2026)
4. **Moyen terme (restant)** — Conteneur non-root + sandbox Chromium réactivé (§ 2.4, à tester via un déploiement dédié) ; gestion des secrets Kamal via un gestionnaire dédié (§ 3.1) ; décider du modèle d'inscription (ouverte vs invitation, § 3.2) ; mettre en place `composer audit` + `npm audit` en CI.

---

*Audit réalisé par revue statique du code ; aucun test d'intrusion dynamique n'a été effectué sur l'environnement de production.*

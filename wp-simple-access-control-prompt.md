# Prompt per Claude Code — WP Simple Access Control

> **Come usare questo file**
> Questo è un prompt di sviluppo strutturato (vibe coding "fatto bene") da fornire a Claude Code come briefing iniziale del progetto. Claude Code dovrà leggerlo per intero, fare eventuali domande di chiarimento che emergono dall'analisi, e poi procedere a scaffolding incrementale del repository. Le sezioni sono ordinate per priorità: contesto → vincoli → architettura → specifica funzionale → criteri di accettazione → roadmap di implementazione.

---

## 1. Identità del progetto

**Nome plugin:** `WP Simple Access Control`
**Slug / textdomain:** `wp-simple-access-control`
**Namespace PHP:** `WPSimpleAccessControl`
**Prefisso DB / opzioni / hook:** `wpsac_`
**Costante principale:** `WPSAC_VERSION`, `WPSAC_PLUGIN_DIR`, `WPSAC_PLUGIN_URL`, `WPSAC_PLUGIN_FILE`

**Obiettivo in una frase:** un plugin WordPress che consente di proteggere singoli contenuti (page, post e qualsiasi CPT pubblico) limitando l'accesso a ruoli utente selezionabili, con form di login inline stilizzato, registro accessi consultabile in admin, configurazione per-contenuto via pannello Gutenberg, e supporto multisite.

---

## 2. Vincoli tecnici (NON negoziabili)

| Area | Vincolo |
|---|---|
| PHP | 8.1 minimo, dichiarazioni di tipo strette (`declare(strict_types=1)`) in tutti i file PHP del namespace |
| WordPress | 6.4 minimo (per Block Editor API stabili) |
| Autoload | PSR-4 via Composer, namespace radice `WPSimpleAccessControl\` mappato su `src/` |
| Coding standards PHP | PHPCS con ruleset combinato `WordPress` + `WordPress-Extra` + `WordPress-Docs` (WPCS 3.x) |
| Static analysis | PHPStan livello 6 con `szepeviktor/phpstan-wordpress` |
| Frontend assets | `@wordpress/scripts` (build/start/lint:js/lint:css), niente webpack custom |
| CSS | SCSS con metodologia BEM rigorosa, un blocco = un file `.scss` in `src/scss/blocks/` |
| Ambiente locale | `wp-env` con configurazione in `.wp-env.json` |
| Repo | Inizializzato con git, `.gitignore` per WP/Node/Composer/IDE |
| Changelog | `CHANGELOG.md` formato [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versionamento [SemVer](https://semver.org/) |
| Skills | Cartella `.claude/skills/` con skill specifica per questo plugin secondo convenzione [Automattic agent-skills](https://github.com/Automattic/agent-skills) |
| Multisite | Tutto deve funzionare anche in network: tabelle per-site, attivazione anche network-wide |
| i18n | Completa, sia PHP (`__`, `esc_html__`) che JS (`wp.i18n`); textdomain caricato correttamente |
| Sicurezza | Nonce su ogni form/REST endpoint, capability check, escaping output, sanitizzazione input, `prepare()` su ogni query |

**Esclusi esplicitamente da questa fase:** test automatici (PHPUnit/Jest) e CI GitHub Actions. Il codice deve comunque essere strutturato in modo testabile (DI leggera, repository pattern) per poterli aggiungere dopo senza refactor.

---

## 3. Struttura del repository

```
wp-simple-access-control/
├── .claude/
│   └── skills/
│       └── wp-simple-access-control/
│           └── SKILL.md          # convenzioni del plugin per Claude Code
├── .wp-env.json
├── .gitignore
├── .editorconfig
├── .nvmrc
├── composer.json
├── package.json
├── phpcs.xml.dist
├── phpstan.neon.dist
├── README.md
├── CHANGELOG.md
├── LICENSE                       # GPL-2.0-or-later
├── wp-simple-access-control.php  # entry file plugin (header + bootstrap)
├── uninstall.php
├── src/
│   ├── Plugin.php                # container/bootstrap principale
│   ├── Domain/
│   │   ├── AccessRule.php        # entità: post_id, roles[], enabled, login_message
│   │   ├── AccessLogEntry.php    # entità: id, user_id, post_id, ip, ua, outcome, ts
│   │   └── AccessOutcome.php     # enum: GRANTED, DENIED_NOT_LOGGED, DENIED_ROLE, DENIED_DISABLED
│   ├── Infrastructure/
│   │   ├── Database/
│   │   │   ├── Schema.php        # CREATE/ALTER tabelle, dbDelta
│   │   │   └── Migrations.php    # gestione versioni schema
│   │   ├── Repository/
│   │   │   ├── AccessRuleRepository.php
│   │   │   └── AccessLogRepository.php
│   │   └── Container.php         # service container minimale
│   ├── Frontend/
│   │   ├── Gate.php              # hook su template_redirect: decide accesso
│   │   ├── LoginRenderer.php     # rendering form inline + template completo
│   │   ├── RestGate.php          # protezione REST API per contenuti riservati
│   │   ├── FeedGate.php          # filtra contenuto in feed → solo excerpt
│   │   ├── CacheHeaders.php      # DONOTCACHEPAGE + Cache-Control
│   │   └── LoginHandler.php      # processa POST del form login
│   ├── Admin/
│   │   ├── BlockEditor/
│   │   │   ├── PanelRegistrar.php       # enqueue script + register_post_meta
│   │   │   └── RestController.php       # REST custom per il pannello
│   │   ├── Settings/
│   │   │   └── SettingsPage.php         # pagina opzioni globali
│   │   └── Logs/
│   │       ├── LogsListTable.php        # WP_List_Table per i log
│   │       └── LogsPage.php             # admin page consultazione
│   ├── Logging/
│   │   └── AccessLogger.php      # service che scrive su AccessLogRepository
│   ├── Security/
│   │   ├── LoginRateLimiter.php  # transient-based rate limiting
│   │   └── IpResolver.php        # gestione X-Forwarded-For, anonimizzazione opzionale
│   └── Support/
│       ├── Hooks.php             # registry centralizzato hook → handler
│       └── Template.php          # caricamento template con override da tema
├── src/scss/
│   ├── frontend/
│   │   ├── _variables.scss
│   │   ├── login-form.scss       # blocco BEM: .wpsac-login
│   │   └── login-template.scss   # blocco BEM: .wpsac-login-page
│   └── editor/
│       └── panel.scss            # blocco BEM: .wpsac-panel
├── src/js/
│   └── editor/
│       ├── index.js              # entry point Gutenberg panel
│       ├── components/
│       │   ├── AccessPanel.jsx
│       │   ├── RolesSelector.jsx
│       │   └── LoginMessageField.jsx
│       └── store/
│           └── index.js          # piccolo store o uso di @wordpress/data core/editor
├── templates/
│   ├── login-form.php            # form inline (override-abile da tema in /wp-simple-access-control/)
│   └── login-page.php            # template completo standalone
├── languages/
│   └── wp-simple-access-control.pot
└── build/                        # output di wp-scripts (gitignored)
```

**Override template da tema:** la classe `Support\Template` cerca prima in `get_stylesheet_directory() . '/wp-simple-access-control/<template>.php'`, poi nella cartella `templates/` del plugin. Documentare nel README.

---

## 4. Modello dati

### 4.1 Tabelle custom (per-site in multisite, prefisso `$wpdb->prefix`)

**`{prefix}wpsac_access_rules`** — una riga per post protetto

| Colonna | Tipo | Note |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `post_id` | BIGINT UNSIGNED, UNIQUE, FK logica su `wp_posts.ID` | |
| `enabled` | TINYINT(1) NOT NULL DEFAULT 0 | |
| `roles` | TEXT NOT NULL | JSON array di slug ruoli, es: `["editor","author"]` |
| `login_message` | TEXT NULL | messaggio personalizzato sopra il form |
| `created_at` | DATETIME NOT NULL | |
| `updated_at` | DATETIME NOT NULL | |

> Scelta: tabella separata invece di solo postmeta per poter fare query efficienti (es: "elenca tutti i post protetti"), pur mantenendo la sincronizzazione con un postmeta "shadow" `_wpsac_protected` (`'1'`/`'0'`) usato come bandiera leggera per filtri loop.

**`{prefix}wpsac_access_logs`** — registro accessi

| Colonna | Tipo | Note |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `post_id` | BIGINT UNSIGNED NOT NULL, INDEX | |
| `user_id` | BIGINT UNSIGNED NULL, INDEX | NULL = utente non autenticato |
| `ip_address` | VARBINARY(16) NULL | salvato come binary via `inet_pton` per IPv4/IPv6 |
| `user_agent` | VARCHAR(500) NULL | troncato |
| `outcome` | VARCHAR(32) NOT NULL, INDEX | enum string: `granted`, `denied_not_logged`, `denied_role`, `denied_disabled`, `login_success`, `login_failed`, `login_rate_limited` |
| `referer` | VARCHAR(500) NULL | |
| `created_at` | DATETIME NOT NULL, INDEX | |

**Indici:** `(post_id, created_at)`, `(user_id, created_at)`, `(outcome)`.

**Migrations:** `Schema.php` espone `install(int $blog_id = null)`, `uninstall()`, gestiti via option `wpsac_db_version`. In multisite, hook su `wpmu_new_blog`/`wp_initialize_site` per creare tabelle nei nuovi siti.

### 4.2 Opzioni globali (per-site)

Salvate in `get_option('wpsac_settings')` come array, con default sensati:

```php
[
    'login_form_mode'         => 'inline',     // 'inline' | 'full_template'
    'enable_remember_me'      => true,
    'enable_lost_password'    => true,
    'enable_registration'     => false,         // rispetta comunque get_option('users_can_register')
    'rate_limit_attempts'     => 5,
    'rate_limit_window'       => 300,           // secondi
    'rate_limit_lockout'      => 900,           // secondi
    'anonymize_ip'            => false,
    'feed_show_excerpt'       => true,
    'log_retention_days'      => 0,             // 0 = nessuna retention (come da requisito)
    'twofa_integration'       => 'auto',        // 'auto' rileva plugin compatibili, 'off'
]
```

---

## 5. Specifica funzionale dettagliata

### 5.1 Pannello Gutenberg

- Implementato come `PluginDocumentSettingPanel` registrato via `registerPlugin()` di `@wordpress/plugins`.
- Visibile solo per post type pubblici e per utenti con capability `edit_post` sul post corrente.
- Componenti:
  - **ToggleControl** "Abilita protezione accesso"
  - **FormTokenField** o lista di **CheckboxControl** (preferire la seconda per chiarezza) con tutti i ruoli del sito ottenuti via REST `wp/v2/users` o endpoint custom `wpsac/v1/roles`. **Nessun ruolo selezionato + abilitato = solo utenti loggati, qualsiasi ruolo.** Documentare questo comportamento.
  - **TextareaControl** "Messaggio sul form di login" (opzionale, sovrascrive default globale)
  - Link "Visualizza log accessi per questo contenuto" → admin page dei log filtrata per `post_id`.
- Persistenza: tramite `register_post_meta` con `show_in_rest: true` per i campi semplici, ma la sincronizzazione effettiva con la tabella `wpsac_access_rules` avviene server-side su hook `save_post` (single source of truth = tabella custom; postmeta serve solo per il binding con il Block Editor).
- Build: `npm run build` produce `build/editor.js` e `build/editor.asset.php`; enqueue con dependencies da asset file.

### 5.2 Frontend gate (Gate.php)

Hook su `template_redirect` con priorità 1 (prima dei redirect canonici).

Pseudocodice algoritmico:

```
on template_redirect:
  if (!is_singular()) return
  $post = get_queried_object()
  $rule = $repository->findByPostId($post->ID)
  if (!$rule || !$rule->enabled) return                    // nessuna protezione
  $user = wp_get_current_user()
  if ($user->ID === 0):
    log(DENIED_NOT_LOGGED)
    render_login(reason='not_logged')
    exit
  if (current_user_can('manage_options')):
    log(GRANTED)
    return                                                  // admin sempre passa
  if (empty($rule->roles)):
    log(GRANTED)
    return                                                  // basta essere loggati
  if (array_intersect($user->roles, $rule->roles)):
    log(GRANTED)
    return
  log(DENIED_ROLE)
  render_login(reason='wrong_role')
  exit
```

**Cache awareness:** se viene mostrato login → definire `DONOTCACHEPAGE` se non già definita, inviare `Cache-Control: private, no-store, max-age=0` e `Vary: Cookie`. Anche su accesso concesso a contenuto protetto: `Cache-Control: private`. Hookare anche `nocache_headers`.

### 5.3 Login form / template

- **Modalità inline:** il gate fa `get_header()`, include il template `login-form.php` (avvolto in `<main class="wpsac-login__wrapper">`), `get_footer()`, `exit`.
- **Modalità full template:** stessa logica ma il template `login-page.php` è autonomo (no `get_header`/`get_footer`), con HTML minimale e SCSS dedicato.
- Il form POST a una action `wpsac_do_login` gestita via `admin-post.php` (loggato e non), processata da `LoginHandler`.
- `LoginHandler`:
  1. verifica nonce `wpsac_login`
  2. verifica rate limit (`LoginRateLimiter`); se superato → log `login_rate_limited`, render con errore
  3. tenta `wp_signon()` con `remember` se l'opzione globale è abilitata
  4. log `login_success` / `login_failed`
  5. su successo → `wp_safe_redirect()` all'URL originale (passato come hidden field, validato con `wp_validate_redirect`)
- Il form mostra condizionalmente:
  - Checkbox "Ricordami" se `enable_remember_me`
  - Link "Password dimenticata" → `wp_lostpassword_url($redirect)` se `enable_lost_password`
  - Link "Registrati" → `wp_registration_url()` se `enable_registration` **E** `get_option('users_can_register')`
- Messaggio sopra il form: ordine di priorità → `$rule->login_message` → opzione globale `default_login_message` → testo i18n di default.

### 5.4 Integrazione 2FA

- `twofa_integration='auto'`: rilevamento via `is_plugin_active()` di plugin noti (`two-factor/two-factor.php`, `wp-2fa/wp-2fa.php`).
- Se rilevato: il `LoginHandler` non bypassa il flusso, semplicemente non interferisce con `wp_signon` (che già delega ai filter `authenticate`/`wp_login`). Documentare nel README che il 2FA si attiva normalmente al primo login.
- Hookare `wp_login` per loggare `login_success` solo dopo che 2FA ha completato.

### 5.5 Protezione REST API

`RestGate` su `rest_pre_dispatch` (o filter `rest_prepare_{post_type}`):
- Per richieste a singolo post protetto, verificare ruolo dell'utente corrente; se non autorizzato → `WP_Error` 403 con messaggio i18n.
- Anche per la collection: filtrare via `rest_{post_type}_query` aggiungendo `post__not_in` con gli ID protetti che l'utente non può vedere.

### 5.6 Feed RSS

`FeedGate` su `the_content_feed` e `the_excerpt_rss`:
- Per post protetti: forzare l'output a `the_excerpt` (anche se il feed è full-text).
- Aggiungere a fondo excerpt link "Leggi tutto" → URL del post (che a sua volta mostrerà login).

### 5.7 Loop / archivi

Hook su `the_content` (priority bassa, es. 9): se `is_singular()` è false e siamo in main loop e post è protetto → sostituire con excerpt + nota "Contenuto riservato". **NON** rimuovere dal `WP_Query` per default (rispetta SEO/conteggi); fornire però un filtro `wpsac_hide_protected_in_loops` per chi lo vuole.

### 5.8 Logging

`AccessLogger::log(int $post_id, string $outcome, ?int $user_id = null)`:
- Risolve IP via `IpResolver` (legge `REMOTE_ADDR`, opzionalmente `X-Forwarded-For` se header autorizzato — configurabile, off di default per sicurezza)
- Anonimizza IP se opzione attiva (`/24` per IPv4, `/64` per IPv6)
- Tronca user agent a 500 char
- INSERT con `$wpdb->insert` (gestisce escaping)
- Hook `wpsac_after_log` per estensibilità

### 5.9 Pagine Admin

**Pagina log accessi** (`tools.php` come parent o menu top-level dedicato):
- `WP_List_Table` con colonne: data/ora, post (link edit), utente (link profilo), IP, esito (badge colorato), user agent (tooltip)
- Filtri: post, utente, esito, range date
- Bulk action: elimina selezionati
- Pagination, sortable columns
- Capability richiesta: `manage_options`
- Action GET `?wpsac_export_csv=1` per export CSV (rispetta filtri attivi)

**Pagina impostazioni** (`options-general.php` come parent):
- Settings API (`register_setting`, `add_settings_section`, `add_settings_field`)
- Sezioni: Login form, Sicurezza (rate limit, IP), Feed/REST, Avanzate
- Salva tutto in option `wpsac_settings` con sanitize callback unica

### 5.10 Multisite

- Network admin page per impostazioni di default ereditate dai siti (opzionale, fase 2 — per ora ogni sito ha le sue)
- Su `activate_{plugin}`: se network-active, iterare blogs e creare schema su ognuno
- Su nuovo sito: hook `wp_initialize_site`
- Capability `manage_network_options` per settings network-wide

### 5.11 Sicurezza trasversale

- Ogni form: `wp_nonce_field` + verifica
- Ogni REST endpoint custom: `permission_callback` che valida capability e nonce (`X-WP-Nonce`)
- Ogni query: `$wpdb->prepare()` — mai concatenazione
- Ogni output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` secondo contesto
- Ogni input: sanitize specifico (`sanitize_text_field`, `absint`, `sanitize_key` per ruoli, ecc.)
- `uninstall.php` rispetta `WP_UNINSTALL_PLUGIN`, droppa tabelle e options solo se opzione `wpsac_drop_data_on_uninstall` è true (default false → conserva dati)

---

## 6. Skill Anthropic (`.claude/skills/wp-simple-access-control/SKILL.md`)

Deve contenere, in formato Automattic agent-skills:
- **Frontmatter YAML:** `name`, `description` (quando applicare la skill, in formato trigger), `version`.
- **Sezione "Convenzioni":** namespace, prefissi, naming hook, struttura cartelle, BEM patterns specifici.
- **Sezione "Pattern ricorrenti":** come aggiungere un nuovo hook, come aggiungere un campo al pannello Gutenberg, come aggiungere una colonna al log.
- **Sezione "Anti-pattern":** cosa NON fare (es: usare `$wpdb->query` con concatenazione, mettere logica nei template, scrivere CSS senza BEM).
- **Sezione "Comandi utili":** `composer`, `npm`, `wp-env`, `phpcs`, `phpstan`.

Questa skill verrà letta da Claude Code in sessioni future per mantenere coerenza.

---

## 7. Configurazione strumenti

### 7.1 `composer.json`

- `require`: `php: >=8.1`
- `require-dev`: `wp-coding-standards/wpcs ^3.0`, `dealerdirect/phpcodesniffer-composer-installer`, `phpstan/phpstan ^1.10`, `szepeviktor/phpstan-wordpress`
- `autoload`: PSR-4 `WPSimpleAccessControl\\` → `src/`
- Scripts: `phpcs`, `phpcbf`, `phpstan`, `lint` (esegue entrambi)

### 7.2 `package.json`

- `devDependencies`: `@wordpress/scripts` (versione corrente stabile)
- Scripts standard `@wordpress/scripts`: `build`, `start`, `lint:js`, `lint:css`, `format`
- Aggiungere script `i18n:pot` con `wp i18n make-pot` (richiede WP-CLI in wp-env)

### 7.3 `.wp-env.json`

```json
{
  "core": "WordPress/WordPress",
  "phpVersion": "8.1",
  "plugins": ["."],
  "config": { "WP_DEBUG": true, "WP_DEBUG_LOG": true, "SCRIPT_DEBUG": true },
  "mappings": {}
}
```

Documentare comandi tipici nel README: `wp-env start`, `wp-env run cli wp ...`.

### 7.4 `phpcs.xml.dist`

Ruleset che estende `WordPress`, `WordPress-Extra`, `WordPress-Docs`, con:
- `text_domain` configurato su `wp-simple-access-control`
- `prefixes` configurato su `wpsac`, `WPSimpleAccessControl`, `WPSAC`
- `minimum_supported_wp_version` 6.4
- esclusioni: `vendor/`, `node_modules/`, `build/`

### 7.5 `phpstan.neon.dist`

```yaml
parameters:
    level: 6
    paths:
        - src/
        - wp-simple-access-control.php
        - uninstall.php
    bootstrapFiles:
        - vendor/szepeviktor/phpstan-wordpress/bootstrap.php
```

---

## 8. CHANGELOG.md iniziale

Formato Keep a Changelog 1.1.0, sezione `[Unreleased]` con sotto-sezioni standard. Prima entry datata: `[0.1.0] - YYYY-MM-DD` con `### Added` che elenca le funzionalità della MVP.

---

## 9. README.md (almeno)

- Badge versione, licenza, PHP, WP
- Descrizione, funzionalità principali
- Requisiti
- Installazione (zip + composer + wp-env)
- Configurazione rapida
- Override template da tema
- Hook/filter pubblici documentati
- Sviluppo locale (wp-env, build, lint)
- Roadmap
- Licenza GPL-2.0-or-later

---

## 10. Criteri di accettazione (definition of done della MVP)

Considera il progetto completo quando:

1. Si può installare via `wp-env start` e attivare il plugin senza errori PHP/JS in console.
2. Su una pagina nuova, il pannello Gutenberg mostra correttamente i controlli e li salva.
3. Una pagina protetta per ruolo `editor`:
   - mostra il form di login a un visitatore non loggato
   - mostra il form di login a un `subscriber` loggato
   - mostra il contenuto a un `editor`
   - mostra il contenuto a un `administrator` (override implicito)
4. Il form di login autentica correttamente e redirige all'URL originale.
5. Rate limiting blocca al N+1° tentativo e logga `login_rate_limited`.
6. Il feed RSS mostra solo excerpt per i post protetti.
7. La REST API restituisce 403 su `/wp-json/wp/v2/pages/{id}` per il post protetto, a utente non autorizzato.
8. La pagina admin "Log accessi" elenca le entry con filtri funzionanti e export CSV.
9. La pagina impostazioni salva e applica tutte le opzioni dichiarate.
10. `composer lint` (phpcs + phpstan) passa senza errori.
11. `npm run lint:js && npm run lint:css` passano senza errori.
12. `npm run build` produce asset funzionanti.
13. Il plugin attivato in network multisite crea le tabelle in tutti i siti esistenti e nei nuovi.
14. Disinstallando con `wpsac_drop_data_on_uninstall=true` rimuove tabelle e options; con false le conserva.
15. La skill in `.claude/skills/` è presente e completa secondo specifica sezione 6.
16. `CHANGELOG.md` riflette lo stato.

---

## 11. Roadmap di implementazione consigliata (ordine di esecuzione)

Procedi a step incrementali, ognuno deve essere committabile e funzionante:

1. **Bootstrap repo:** struttura cartelle, `composer.json`, `package.json`, `.wp-env.json`, `.gitignore`, `phpcs.xml.dist`, `phpstan.neon.dist`, file entry plugin con header, `Plugin.php` placeholder, `CHANGELOG.md` iniziale, README scheletro, LICENSE, skill scheletro.
2. **Schema DB + Repository:** `Schema.php`, `Migrations.php`, entità Domain, repository con metodi base, attivazione/disattivazione hook.
3. **Pannello Gutenberg minimale:** registrazione post meta, entry JS, componente con toggle e checkbox ruoli, build funzionante, sync con tabella `wpsac_access_rules` su `save_post`.
4. **Frontend Gate:** `Gate.php` con logica di accesso, `LoginRenderer` modalità inline, template `login-form.php`, SCSS BEM, `LoginHandler` con nonce. Test manuale completo.
5. **Logging + IpResolver + RateLimiter:** integrazione in Gate e LoginHandler.
6. **REST API + Feed gates.**
7. **Admin page log:** `WP_List_Table` con filtri, paginazione, CSV export.
8. **Settings page:** Settings API completa.
9. **Modalità full template + integrazione 2FA + opzioni avanzate.**
10. **Multisite hardening:** test su rete, hook nuovi siti.
11. **i18n:** `.pot`, traduzione italiana di esempio.
12. **Polish:** documentazione hook, README finale, CHANGELOG aggiornato, skill aggiornata.

---

## 12. Domande residue / punti aperti

Se durante l'implementazione emergono ambiguità, ferma e chiedi prima di prendere decisioni architetturali. Esempi di domande legittime:
- Quale plugin 2FA testiamo per primo?
- Per la pagina log preferiamo menu top-level o sotto "Strumenti"?
- Vogliamo già da MVP un widget dashboard "ultimi accessi negati"?

In assenza di risposta, applica la soluzione più conservativa (= meno superficie, più sicura, più aderente alle convenzioni WordPress core).

---

## 13. Stile di codice e commenti

- PHP: PHPDoc completo su ogni metodo pubblico (`@param`, `@return`, `@throws`); commenti inline solo dove la logica non è ovvia.
- JS: JSDoc su funzioni esportate; nei componenti React commento iniziale che descrive props e responsabilità.
- SCSS: commento di intestazione per ogni file blocco con nome BEM e dipendenze.
- Commit message in inglese, formato [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`).

---

**Fine prompt.** Procedi con domande o, se tutto è chiaro, inizia dallo step 1 della roadmap.

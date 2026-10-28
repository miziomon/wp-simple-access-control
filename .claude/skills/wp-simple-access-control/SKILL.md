---
name: wp-simple-access-control
description: Usa questa skill quando lavori sul plugin WP Simple Access Control (namespace WPSimpleAccessControl, prefisso wpsac_): aggiungere o modificare hook, servizi, impostazioni, campi del pannello Gutenberg, controlli per singolo blocco, colonne del registro accessi, template, traduzioni, oppure testare gate, REST, feed, 2FA e multisite in wp-env.
version: 0.1.0
---

# WP Simple Access Control

Plugin che limita l'accesso a singoli contenuti e a singoli blocchi in base al ruolo, con form di login inline e registro accessi. Documentazione e commenti sono in italiano, le stringhe traducibili e il README in inglese.

## Convenzioni

| Cosa | Regola |
|---|---|
| Namespace PHP | `WPSimpleAccessControl\`, PSR-4 su `src/`, `declare(strict_types=1)` in ogni file |
| Prefissi | `wpsac_` (hook, opzioni, tabelle, meta), `WPSAC_` (costanti), `wpsac-` (handle e text domain `wp-simple-access-control`) |
| Costanti | `WPSAC_VERSION`, `WPSAC_PLUGIN_FILE`, `WPSAC_PLUGIN_DIR`, `WPSAC_PLUGIN_URL` (definite in `wp-simple-access-control.php`, dichiarate per PHPStan in `phpstan-bootstrap.php`) |
| Tabelle | `{prefix}wpsac_access_rules`, `{prefix}wpsac_access_logs` (per sito; date in UTC) |
| Opzioni | `wpsac_settings` (array, default in `Support\Settings::defaults()`), `wpsac_db_version`, `wpsac_drop_data_on_uninstall` |
| Meta | `_wpsac_enabled`, `_wpsac_roles`, `_wpsac_login_message` (ponte con l'editor), `_wpsac_protected` (bandiera "shadow") |
| Attributi blocco | `wpsacVisibility`, `wpsacRoles`, `wpsacShowTeaser`, `wpsacTeaserMessage` (stessi nomi in PHP e JS) |
| Hook pubblici | filtri e azioni `wpsac_*`, elencati nel README |
| Stringhe | Inglese, con `__()`/`esc_html__()` e text domain; mai testo italiano nel sorgente |
| Commit | In inglese, Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`) |

### Architettura

- `Plugin::boot()` registra i servizi nel `Infrastructure\Container` (factory lazy) e chiama `->register()` su ciascuno. Ogni servizio aggancia i propri hook in `register()`: non c'è un registro centrale di hook.
- **La decisione di accesso sta in un solo posto**: `Domain\AccessPolicy` (`check()` per i contenuti, `can_view_block()` per i blocchi). Gate, REST, feed, archivi e blocchi la riusano. Non duplicare la logica.
- `Frontend\ContentAccess` risponde "questo utente può vedere questo post?" con una sola query per richiesta (`AccessRuleRepository::find_enabled()`, in cache e invalidata da `save()`/`delete`).
- La tabella `wpsac_access_rules` è la fonte di verità. I meta `_wpsac_*` servono solo all'editor e vengono copiati nella tabella da `Admin\BlockEditor\RuleSynchronizer` su `wp_after_insert_post` (non `save_post`: con il block editor i meta arrivano dopo).

### Struttura

```
src/Domain          AccessPolicy, AccessRule, AccessLogEntry, AccessOutcome (enum)
src/Infrastructure  Container, Database/{Schema,Migrations}, Repository/*
src/Frontend        Gate, LoginRenderer, LoginHandler, RestGate, FeedGate, LoopGate, ContentAccess, CacheHeaders, Assets
src/Blocks          BlockVisibility, TeaserRenderer, ExcerptGuard
src/Admin           BlockEditor/{PanelRegistrar,RuleSynchronizer,RestController}, Logs/*, Settings/*
src/Security        IpResolver, LoginRateLimiter, TwoFactorIntegration
src/Logging         AccessLogger, LogRetention
src/Support         Settings, Template, PostTypes
src/js/editor       index.js, store/, components/, blocks/
src/scss            frontend/, editor/, admin/  (un blocco BEM = un file)
templates           login-form.php, login-page.php (override dal tema)
```

### BEM

Un blocco per file SCSS, con commento iniziale (nome del blocco, elementi, modificatori, dipendenze). Blocchi esistenti: `.wpsac-login`, `.wpsac-login-page`, `.wpsac-teaser`, `.wpsac-panel`, `.wpsac-block-indicator`, `.wpsac-logs-filters`, `.wpsac-logs-table`, `.wpsac-badge`. Elementi `__x`, modificatori `--y`; niente selettori annidati che escano dal blocco. I valori personalizzabili sono custom property `--wpsac-*`.

## Pattern ricorrenti

### Aggiungere un servizio o un hook

1. Crea la classe in `src/<Area>/` con un metodo `register(): void` che fa `add_action`/`add_filter`.
2. Registrala in `Plugin::boot()` con `$this->container->set( X::class, static fn( Container $c ) => new X( $c->get( Dep::class ) ) )` e chiama `->register()`.
3. Se è solo admin, registrala dentro `if ( is_admin() )`.
4. Nuovo hook pubblico: nome `wpsac_*`, docblock con `@param`, e riga nelle tabelle del README.

### Aggiungere un'impostazione

1. Aggiungi la chiave e il default in `Support\Settings::defaults()`.
2. Aggiungi il campo in `Admin\Settings\SettingsPage::fields()` (section, label, type, description, e min/max o options). La stessa definizione serve a stampare e a sanitizzare.
3. Leggila con `$settings->get( 'chiave' )`. Aggiorna la tabella delle impostazioni del README.

### Aggiungere un campo al pannello Gutenberg del documento

1. Dichiara il meta in `PanelRegistrar::register_meta()` (con `show_in_rest`, `sanitize_callback` e l'`auth_callback` comune: i meta con `_` iniziale lo richiedono).
2. Leggilo e scrivilo in `src/js/editor/components/AccessPanel.js` (`useEntityProp( 'postType', postType, 'meta' )`).
3. Se deve finire nella regola, estendi `AccessRule`, `RuleSynchronizer::sync()` e il repository (vedi sotto per le colonne).

### Aggiungere un attributo ai blocchi

Nomi e default identici in `Blocks\BlockVisibility::add_attributes()` e in `src/js/editor/blocks/constants.js` (`ATTRIBUTES`). Se l'attributo contiene testo scritto dall'utente tienilo **senza HTML**: vedi "Trappole".

### Aggiungere una colonna al registro accessi

1. `Schema::install()`: aggiungi la colonna in `CREATE TABLE` e incrementa `Schema::DB_VERSION` (`Migrations::maybe_upgrade()` rieseguirà `dbDelta`).
2. `Domain\AccessLogEntry` (nuovo parametro), `AccessLogRepository::add()` e `hydrate_all()`.
3. `AccessLogger::log()` per valorizzarla.
4. `Admin\Logs\LogsListTable`: voce in `get_columns()` e metodo `column_<nome>()`; per ordinare, aggiungi la chiave in `AccessLogRepository::SORTABLE`.
5. `LogsPage::csv_row()` e l'intestazione dell'export.

### Aggiungere un esito del log

Nuovo `case` in `Domain\AccessOutcome` (valore stringa ≤ 32 caratteri), la sua `label()`, il colore del badge in `LogsListTable::column_outcome()`.

### Aggiungere o modificare stringhe tradotte

Aggiorna il sorgente in inglese, poi `npm run i18n:pot && npm run i18n:po`, traduci le voci nuove in `languages/wp-simple-access-control-it_IT.po`, poi `npm run i18n:mo && npm run i18n:json`. Un nuovo file JS con stringhe va aggiunto a `tools/i18n-map.json`.

## Anti-pattern

- **Mai** concatenare input in una query: sempre `$wpdb->prepare()`. Nomi di tabella e colonne vengono solo da elenchi interni (vedi `AccessLogRepository::SORTABLE`).
- **Mai** logica nei template (`templates/*.php`): ricevono `$args` già pronti e fanno solo escaping.
- **Mai** scrivere CSS fuori da un blocco BEM o con selettori che dipendono dal tema.
- **Mai** duplicare la decisione di accesso: usa `AccessPolicy`.
- **Mai** fidarsi di `$_GET`/`$_POST` senza nonce e sanitizzazione; la password del login non si sanitizza.
- **Mai** mettere HTML negli attributi di un blocco ristretto (vedi sotto).
- **Mai** seguire un redirect ricevuto da input senza `wp_validate_redirect()` (vedi `LoginHandler::requested_redirect()`).
- Non loggare ogni blocco nascosto (una riga per blocco a ogni visita): esiste `wpsac_block_hidden` per chi lo vuole.
- Non rifattorizzare codice adiacente a ciò che stai cambiando.

## Trappole incontrate (leggere prima di toccare queste aree)

- **kses e commento del blocco.** Per gli utenti senza `unfiltered_html`, WordPress ripulisce l'HTML dentro il JSON del commento di blocco: gli attributi spariscono e il blocco ristretto diventa pubblico (fail open). Per questo il teaser è testo semplice con `[login]testo[/login]`.
- **Estratti.** Per Group/Columns/Column/List il core fa `render_block()` solo sui figli: il filtro `render_block` sul contenitore non scatta. `ExcerptGuard` toglie i blocchi non visibili da `post_content` (aggiustando `innerContent`) e ricalcola con `wp_trim_excerpt( '', $clone )`; la copia deve avere `filter = 'raw'`. Guardia anti-ricorsione obbligatoria (`LoopGate`, `ExcerptGuard`).
- **Il canvas dell'editor è un iframe.** Gli stili dei blocchi vanno caricati con `enqueue_block_assets` (entry `editor-canvas`), non con `enqueue_block_editor_assets`: altrimenti l'indicatore ha la classe ma non si vede.
- **Block theme.** `get_header()` non funziona: il form sostituisce il contenuto via `the_content` (priorità massima). Sui temi classici si usa `get_header()` + `exit`, così il template del tema non tocca il contenuto.
- **REST.** `rest_request_before_callbacks` blocca `get_item`; il contesto `edit` resta aperto a chi può modificare il post, altrimenti l'autore non apre la propria pagina nell'editor.
- **`post__in` vince su `post__not_in`.** In `WP_Query` sono un `if/elseif`: la REST mappa `?include=` su `post__in`, quindi escludere solo con `post__not_in` lascia passare `?include=<id>` e restituisce il contenuto intero. Si usa sempre `ContentAccess::exclude_from_query()`, che toglie gli ID anche da `post__in` (e mette `[0]` se non resta nulla: un array vuoto significa "nessun filtro").
- **Callback degli hook tolleranti.** Molti temi e plugin chiamano `get_the_excerpt` con un solo argomento e `the_content` con `null`: i callback su questi filtri non devono avere tipi scalari o `\WP_Post` obbligatori (TypeError/ArgumentCountError = fatal su una pagina protetta). Parametri `mixed`, cast a `string`, `get_post()` come ripiego.
- **2FA.** Two Factor interrompe `wp_signon()` (hook `wp_login`, priorità `PHP_INT_MAX`) e riprende dopo il secondo fattore: `login_success` si registra su `two_factor_user_authenticated`.
- **JS.** I file con JSX si chiamano `.js` (non `.jsx`): `wp i18n make-json` ignora i `.jsx`.
- **PHPCS/PHPStan.** Le costanti `WPSAC_*` per PHPStan stanno in `phpstan-bootstrap.php`; in `Template::render()` la variabile `$args` è usata dal file incluso (ignore motivato).

## Comandi utili

```bash
composer install && npm install
npm run build | npm run start         # build / watch (entry: editor, frontend, admin, editor-canvas)
composer lint                         # phpcs + phpstan (livello 6)
composer phpcbf                       # correzione automatica di phpcs
npm run lint:js && npm run lint:css
npx wp-env start                      # http://localhost:8888  (admin / password)
npx wp-env run cli wp <comando>       # WP-CLI nell'ambiente
npm run env:multisite -- start        # multisite su :8892, da lanciare dalla radice del repo
npm run i18n:pot | i18n:po | i18n:mo | i18n:json
```

### Verifiche in wp-env (script usa e getta)

- Per eseguire PHP: scrivi un file temporaneo nella radice del plugin ed esegui `npx wp-env run cli wp eval-file wp-content/plugins/wp-simple-access-control/<file>.php`; **cancellalo dopo**.
- In Git Bash `wp-env run` richiede `MSYS_NO_PATHCONV=1`, ma `curl` per Windows no: non usarli nello stesso comando (i percorsi `/tmp/...` non si risolvono).
- **Non eseguire `wp plugin uninstall wp-simple-access-control` in wp-env**: cancella la cartella montata, cioè il repository. Per provare `uninstall.php` definisci `WP_UNINSTALL_PLUGIN` e fai `include` del file.
- Contenuti di test con blocchi: usa `wp_insert_post( wp_slash( ... ) )` (senza `wp_slash` si perdono i backslash del JSON) e crea i dati come amministratore, altrimenti kses altera il contenuto.
- Ogni login dallo stesso IP conta per il rate limiter: azzera con `wp transient delete --all`.
- Screenshot ed E2E: `playwright-core` è già in `node_modules`; si usa il Chrome di sistema con `executablePath` e `headless: true`.

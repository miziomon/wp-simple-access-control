# Changelog

Tutte le modifiche rilevanti a questo progetto sono documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) e il progetto aderisce al [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-10-28

Prima versione (MVP).

### Added

- **Protezione dei contenuti interi** per page, post e CPT pubblici: pannello "Access control" nella sidebar del documento (attivazione, ruoli, messaggio di login) con salvataggio nella tabella `wpsac_access_rules` su `wp_after_insert_post`.
- **Controllo per singolo blocco**: attributi di visibilità (`wpsacVisibility`, `wpsacRoles`, `wpsacShowTeaser`, `wpsacTeaserMessage`) aggiunti a tutti i blocchi, registrati anche lato server; modalità tutti / solo loggati (con ruoli) / solo non loggati; messaggio sostitutivo in testo semplice con `[login]testo[/login]`; indicatore nell'editor senza elemento wrapper.
- **Protezione degli estratti automatici** dei blocchi contenitore (Group, Columns, Column, List), che il core genera senza passare dal contenitore.
- **Form di login inline** per temi classici e block theme, e modalità **pagina autonoma**; override dei template dal tema; stato HTTP 403 e header anti-cache (`DONOTCACHEPAGE`, `Cache-Control: private`, `Vary: Cookie`).
- **Protezione di REST API, feed e archivi**: 403 sul singolo contenuto, esclusione dalle collezioni, solo estratto nei feed, estratto con nota negli archivi; filtro `wpsac_hide_protected_in_loops`.
- **Registro accessi** (`wpsac_access_logs`) con IP in binario (IPv4/IPv6), anonimizzazione opzionale, retention con WP-Cron, pagina "Strumenti > Access log" con filtri, ordinamento, cancellazione multipla ed export CSV con neutralizzazione delle formule.
- **Rate limiting** dei login falliti per IP e integrazione con i plugin 2FA (provato con Two Factor).
- **Pagina impostazioni** con la Settings API.
- **Multisite**: attivazione di rete, hook sui nuovi siti, eliminazione delle tabelle insieme al sito, pulizia del cron, disinstallazione per sito.
- **Disinstallazione** che conserva i dati salvo l'opzione `wpsac_drop_data_on_uninstall`.
- **Traduzione italiana** completa (stringhe sorgente in inglese) e workflow `npm run i18n:*`.
- Ambienti di sviluppo `wp-env` (singolo sito e multisite), PHPCS (WordPress), PHPStan livello 6, ESLint e stylelint.

# AI Pages

Plugin WordPress per pubblicare landing page generate dall'AI come HTML self-contained, servite fedeli all'output senza passare da un page builder.

Una landing è un documento HTML autonomo (CSS e JS inline). Il plugin la serve così com'è, con un editor comodo, un'anteprima live e un deploy automatizzabile da un agent AI.

## Caratteristiche

- **Custom post type `ai_page`** con indirizzo personalizzabile (default `/lp/...`).
- **Tre tipi di pagina** (chrome):
  - `full` (default): il tuo documento HTML completo, servito identico.
  - `none`: solo il tuo contenuto, il sito ci mette attorno una struttura minima più gli snippet head/footer.
  - `site`: la landing avvolta da header e footer del tema.
- **Editor** con evidenziazione della sintassi, **anteprima live** con breakpoint (mobile/tablet/desktop) e schermo intero, e **click-to-highlight**: clicchi un elemento nell'anteprima e lo ritrovi selezionato nel codice.
- **Deploy da agent cloud** via REST con token personale, oppure da **WP-CLI**.
- **Token per-utente**: i deploy sono attribuiti all'utente del token.
- **Snippet globali** per head e fine pagina, e toggle per eseguire gli hook del sito (così funzionano i plugin SEO come Slim SEO).
- **Shortcode** opzionali per pagina, senza `wpautop`.
- **Versioning** tramite le revisioni native di WordPress.

## Requisiti

- WordPress 6.4+
- PHP 8.0+

## Installazione

Copia la cartella in `wp-content/plugins/ai-pages` e attiva il plugin. Le impostazioni sono in **AI Pages → Impostazioni**.

## Deploy

### Da agent cloud (REST)

```
POST /wp-json/ai-pages/v1/deploy
Authorization: Bearer <token>
Content-Type: application/json

{ "key": "black-friday", "title": "Black Friday", "chrome": "full",
  "status": "publish", "html": "<!doctype html>...</html>" }
```

Il token si trova in **AI Pages → Impostazioni** (uno per utente, anche nel profilo). Campi: `key` e `html` obbligatori; `title`, `slug`, `chrome`, `status` opzionali. Risposta: `{ ok, id, url, action }`. Ripubblicare con la stessa `key` aggiorna la pagina invece di duplicarla.

Nelle Impostazioni c'è anche un prompt pronto da incollare nell'agent, con endpoint e token già dentro.

### Da riga di comando (WP-CLI)

```
wp ai-page upsert --key=black-friday --file=./bf.html --chrome=full
cat bf.html | wp ai-page upsert --key=black-friday
```

Tieni i file `.html` in un repo per avere diff, review e rollback.

## Licenza

GPL-2.0-or-later

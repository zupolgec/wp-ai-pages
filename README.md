# AI Pages

Plugin WordPress per pubblicare AI page generate dall'AI come HTML self-contained, servite fedeli all'output senza passare da un page builder.

Una AI page è un documento HTML autonomo (CSS e JS inline). Il plugin la serve così com'è, con un editor comodo, un'anteprima live e una pubblicazione automatizzabile da un agent AI.

## Caratteristiche

- **Custom post type `ai_page`** con indirizzo personalizzabile (default `/pages/...`).
- **Percorso personalizzato per pagina**: puoi pubblicare una singola AI page fuori dal prefisso, es. `/promo/black-friday`.
- **Percorsi root intenzionali**: il prefisso globale non può essere `/`; per pubblicare alla radice usa il percorso personalizzato della singola AI page.
- **Asset nella Libreria media**: il deploy può includere immagini base64 e placeholder `asset://nome-file`. Gli asset identici vengono deduplicati per contenuto e l'editor mostra quelli collegati con anteprima, nome e dimensione. Gli asset con lo stesso contenuto non vengono duplicati e sono elencati nell'editor con anteprima, nome e dimensione.
- **Tre tipi di pagina** (chrome):
  - `full` (default): il tuo documento HTML completo, servito identico.
  - `none`: solo il tuo contenuto, il sito ci mette attorno una struttura minima più gli snippet head/footer.
  - `site`: la AI page avvolta da header e footer del tema.
- **Editor** con evidenziazione della sintassi, **anteprima live** con breakpoint (mobile/tablet/desktop) e schermo intero, e **click-to-highlight**: clicchi un elemento nell'anteprima e lo ritrovi selezionato nel codice.
- **Pubblicazione da agent cloud** via REST con token personale, oppure da **WP-CLI**.
- **Accesso controllato**: la pubblicazione via token è disattivata di default e può essere aperta solo agli amministratori o anche agli editor.
- **Token per-utente salvati come hash**: il valore viene mostrato solo quando lo generi.
- **Snippet globali** per head e fine pagina, e toggle per eseguire gli hook del sito (così funzionano i plugin SEO come Slim SEO).
- **Shortcode** opzionali per pagina, senza `wpautop`.
- **Versioning** tramite le revisioni native di WordPress.

## Requisiti

- WordPress 6.4+
- PHP 8.0+

## Installazione

Copia la cartella in `wp-content/plugins/ai-pages` e attiva il plugin. Le impostazioni sono in **AI Pages → Impostazioni**.

## Release

Gli zip pubblicati sono disponibili nelle [GitHub Releases](https://github.com/zupolgec/wp-ai-pages/releases).

Per creare una release, aggiorna `Version` in `ai-pages.php` e `Stable tag` in `readme.txt`, poi crea un tag `vX.Y.Z` coerente:

```bash
git tag v0.5.0
git push origin v0.5.0
```

La GitHub Action valida i metadati e allega alla release lo zip installabile, ad esempio `ai-pages-0.5.0.zip`.

## Test

La CI esegue controlli leggeri ma utili: lint PHP, syntax check JavaScript, coerenza metadati, packaging smoke e WordPress Plugin Check non bloccante.

Per un test di integrazione locale, usa un sito WordPress o WP Studio:

```bash
rsync -a --exclude='.git' --exclude='.github' --exclude='.playwright-cli' ./ /path/to/wordpress/wp-content/plugins/ai-pages/
wp plugin activate ai-pages
wp option update aip_prefix pages
wp rewrite flush
```

Checklist manuale consigliata: apri la dashboard, apri `AI Pages → Impostazioni`, verifica che prefisso vuoto e `/` mostrino errore, crea una AI page via editor, crea una AI page via WP-CLI, prova un percorso personalizzato alla radice, abilita temporaneamente REST per amministratori, genera un token, pubblica via REST con asset, revoca il token e rimetti la pubblicazione automatica su disattivata.

## Deploy

### Da agent cloud (REST)

La pubblicazione via REST è disattivata di default. Attivala in **AI Pages → Impostazioni**, poi genera un token e copialo subito: non sarà più mostrato.

```
POST /wp-json/ai-pages/v1/deploy
Authorization: Bearer <token>
Content-Type: application/json

{ "key": "black-friday", "title": "Black Friday", "path": "/promo/black-friday",
  "chrome": "full", "status": "publish",
  "html": "<!doctype html><img src=\"asset://hero.webp\"></html>",
  "assets": [{ "name": "hero.webp", "data": "<base64>", "alt": "Descrizione immagine" }] }
```

Campi: `key` e `html` obbligatori; `title`, `slug`, `path`, `chrome`, `status`, `assets` opzionali. La `key` viene salvata come AI page key univoca. Gli asset vengono caricati nella Libreria media e sostituiscono i placeholder `asset://nome-file`. Piccoli elementi grafici ottimizzati dovrebbero restare inline in HTML/CSS/SVG; usa `assets` solo per veri media. Risposta: `{ ok, id, url, action, asset_ids }`. Ripubblicare con la stessa `key` aggiorna la pagina invece di duplicarla.

Nelle Impostazioni c'è anche un prompt pronto da incollare nell'agent. Se non hai appena generato il token, il prompt contiene un segnaposto da sostituire.

### Da riga di comando (WP-CLI)

```
wp ai-page upsert --key=black-friday --file=./bf.html --chrome=full
wp ai-page upsert --key=black-friday --file=./bf.html --url-path=/promo/black-friday --assets=./assets.json
cat bf.html | wp ai-page upsert --key=black-friday
```

Tieni i file `.html` in un repo per avere diff, review e rollback.

## Licenza

MIT

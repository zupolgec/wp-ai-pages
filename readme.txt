=== AI Pages ===
Contributors: 16bit
Tags: ai, landing pages, html, rest api, wp-cli
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.5.0
License: MIT
License URI: https://opensource.org/license/mit

Pubblica AI page self-contained come HTML raw, con editor, anteprima e pubblicazione automatica controllata.

== Description ==

AI Pages crea un custom post type per pagine HTML self-contained generate dall'AI. Ogni AI page può essere servita come documento completo, come contenuto autonomo in una shell minima, oppure dentro header e footer del tema.

Le AI page usano di default indirizzi sotto `/pages/`. Il prefisso è configurabile, ma non può essere vuoto o `/`. Per pubblicare alla radice, usa il percorso personalizzato della singola AI page.

I deploy REST e WP-CLI possono includere immagini: usa placeholder `asset://nome-file` nell'HTML e passa gli asset come base64. Il plugin li carica nella Libreria media e sostituisce i placeholder con gli URL finali. Piccoli elementi grafici ottimizzati dovrebbero restare inline; gli asset sono pensati per veri media.

La pubblicazione via REST è disattivata di default. Puoi abilitarla solo per amministratori o anche per editor. I token sono personali, vengono salvati come hash e il valore viene mostrato solo al momento della generazione.

== Installation ==

1. Carica la cartella `ai-pages` in `wp-content/plugins/`.
2. Attiva il plugin da WordPress.
3. Vai in `AI Pages > Impostazioni`.
4. Se ti serve la pubblicazione automatica, attivala e genera un token.

== Frequently Asked Questions ==

= Dove scarico lo zip? =

Gli zip delle release sono su https://github.com/zupolgec/wp-ai-pages/releases.

= Quando viene creata una release? =

Quando viene pubblicato un tag Git `vX.Y.Z` coerente con `Version` e `Stable tag`.

= La pubblicazione via token è attiva subito? =

No. È disattivata di default.

= Posso recuperare un token già generato? =

No. Il token viene mostrato solo una volta. Se lo perdi, generane uno nuovo.

== Changelog ==

= 0.5.0 =
* Asset immagine importati nella Libreria media tramite placeholder `asset://`.
* Deduplica degli asset per contenuto: lo stesso file non viene caricato due volte.
* Asset collegati mostrati nell'editor con anteprima, nome e dimensione.
* Percorso personalizzato per singola AI page, anche alla radice del sito.

= 0.4.0 =
* Pubblicazione REST configurabile.
* Token salvati come hash e mostrati solo alla generazione.
* Anteprima admin sandboxed.
* AI page key univoca.

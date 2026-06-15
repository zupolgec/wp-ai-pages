( function ( $ ) {
	$( function () {
		var ta = document.getElementById( 'aip-html' );
		var iframe = document.getElementById( 'aip-preview' );
		var root = document.getElementById( 'aip-edit' );
		if ( ! ta || ! iframe || ! window.wp || ! wp.codeEditor ) {
			return;
		}

		var editor = wp.codeEditor.initialize( 'aip-html', window.AIP_CM || {} );
		var cm = editor.codemirror;
		var pickMode = false;

		/* -----------------------------------------------------------------
		 * Annotatore: scansiona il sorgente e inserisce in ogni start-tag
		 * un attributo data-aip-loc="<offset nel sorgente originale>".
		 * Gli offset restano validi su cm.getValue() perché calcolati
		 * sull'originale e usati prima di eventuale wrapping.
		 * --------------------------------------------------------------- */
		function findTagEnd( src, i ) {
			var q = null;
			for ( var j = i; j < src.length; j++ ) {
				var c = src[ j ];
				if ( q ) {
					if ( c === q ) { q = null; }
				} else if ( c === '"' || c === "'" ) {
					q = c;
				} else if ( c === '>' ) {
					return j + 1;
				}
			}
			return src.length;
		}

		function annotate( src ) {
			var out = '';
			var i = 0;
			var n = src.length;
			while ( i < n ) {
				if ( src[ i ] !== '<' ) {
					out += src[ i ];
					i++;
					continue;
				}
				// Commenti.
				if ( src.substr( i, 4 ) === '<!--' ) {
					var ce = src.indexOf( '-->', i + 4 );
					ce = ce < 0 ? n : ce + 3;
					out += src.slice( i, ce );
					i = ce;
					continue;
				}
				// Closing tag, doctype, processing instruction.
				if ( src[ i + 1 ] === '/' || src[ i + 1 ] === '!' || src[ i + 1 ] === '?' ) {
					var ge = findTagEnd( src, i );
					out += src.slice( i, ge );
					i = ge;
					continue;
				}
				var m = /^<([a-zA-Z][a-zA-Z0-9:-]*)/.exec( src.slice( i, i + 60 ) );
				if ( ! m ) {
					out += src[ i ];
					i++;
					continue;
				}
				var tag = m[ 1 ].toLowerCase();
				var nameEnd = i + 1 + m[ 1 ].length;
				var tagEnd = findTagEnd( src, i );
				out += src.slice( i, nameEnd ) + ' data-aip-loc="' + i + '"' + src.slice( nameEnd, tagEnd );
				// Raw-text elements: copia il contenuto senza annotare.
				if ( tag === 'script' || tag === 'style' || tag === 'textarea' ) {
					var close = src.toLowerCase().indexOf( '</' + tag, tagEnd );
					close = close < 0 ? n : close;
					out += src.slice( tagEnd, close );
					i = close;
				} else {
					i = tagEnd;
				}
			}
			return out;
		}

		/* --- Anteprima live --- */
		function bridgeScript() {
			return '<script>(function(){' +
				'var pick=false,last=null;' +
				'function closest(el){while(el&&el.nodeType===1){if(el.hasAttribute("data-aip-loc")){return el;}el=el.parentElement;}return null;}' +
				'function clear(){if(last){last.style.outline="";last.style.outlineOffset="";last=null;}}' +
				'window.addEventListener("message",function(e){var d=e.data||{};if(d.type!=="aip-set-pick"){return;}pick=!!d.on;if(document.body){document.body.style.cursor=pick?"crosshair":"";}if(!pick){clear();}});' +
				'document.addEventListener("click",function(e){if(!pick){return;}e.preventDefault();e.stopPropagation();var el=closest(e.target);if(el){parent.postMessage({type:"aip-pick",offset:parseInt(el.getAttribute("data-aip-loc"),10)},"*");}},true);' +
				'document.addEventListener("mouseover",function(e){if(!pick){return;}var el=closest(e.target);if(el===last){return;}clear();if(el){el.style.outline="2px solid #2271b1";el.style.outlineOffset="-2px";last=el;}},true);' +
				'}());</scr' + 'ipt>';
		}

		function injectBridge( doc ) {
			var script = bridgeScript();
			if ( /<\/body\s*>/i.test( doc ) ) {
				return doc.replace( /<\/body\s*>/i, script + '</body>' );
			}
			if ( /<\/html\s*>/i.test( doc ) ) {
				return doc.replace( /<\/html\s*>/i, script + '</html>' );
			}
			return doc + script;
		}

		function buildDoc( html ) {
			var annotated = annotate( html );
			var head = html.trim().toLowerCase();
			var isDoc = head.indexOf( '<!doctype' ) === 0 || head.indexOf( '<html' ) === 0;
			if ( isDoc ) {
				return injectBridge( annotated );
			}
			return injectBridge( '<!doctype html><html><head><meta charset="utf-8">' +
				'<meta name="viewport" content="width=device-width,initial-scale=1"></head><body>' +
				annotated + '</body></html>' );
		}
		function render() {
			iframe.srcdoc = buildDoc( cm.getValue() );
		}
		var t;
		cm.on( 'change', function () {
			clearTimeout( t );
			t = setTimeout( render, 500 );
		} );
		$( '#aip-refresh' ).on( 'click', render );

		/* -----------------------------------------------------------------
		 * Click-to-highlight: click nell'anteprima -> evidenzia nel codice.
		 * L'iframe è sandboxed: comunica con la pagina admin via postMessage.
		 * --------------------------------------------------------------- */
		function highlightAt( offset ) {
			var src = cm.getValue();
			if ( offset < 0 || offset > src.length ) {
				return;
			}
			var end = src.indexOf( '>', offset );
			end = end < 0 ? offset : end + 1;
			var from = cm.posFromIndex( offset );
			var to = cm.posFromIndex( end );
			cm.focus();
			cm.setSelection( from, to );
			cm.scrollIntoView( { from: from, to: to }, 100 );
		}

		function sendPickState() {
			if ( iframe.contentWindow ) {
				iframe.contentWindow.postMessage( { type: 'aip-set-pick', on: pickMode }, '*' );
			}
		}

		iframe.addEventListener( 'load', sendPickState );
		window.addEventListener( 'message', function ( e ) {
			var data = e.data || {};
			if ( e.source !== iframe.contentWindow || data.type !== 'aip-pick' ) {
				return;
			}
			if ( root.classList.contains( 'aip-fs' ) ) {
				root.classList.remove( 'aip-fs' );
			}
			highlightAt( parseInt( data.offset, 10 ) );
		} );

		function setPick( on ) {
			pickMode = on;
			$( '#aip-pick' ).toggleClass( 'active', on );
			sendPickState();
		}
		$( '#aip-pick' ).on( 'click', function () {
			setPick( ! pickMode );
		} );

		/* --- Breakpoint --- */
		$( '.aip-bp button' ).on( 'click', function () {
			var w = $( this ).data( 'w' );
			$( '.aip-bp button' ).removeClass( 'active' );
			$( this ).addClass( 'active' );
			iframe.style.width = ( w === 'full' ) ? '100%' : ( w + 'px' );
		} );

		/* --- Schermo intero --- */
		function toggleFs() {
			root.classList.toggle( 'aip-fs' );
		}
		$( '#aip-fs' ).on( 'click', toggleFs );
		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && root.classList.contains( 'aip-fs' ) ) {
				toggleFs();
			}
		} );

		/* --- Sync verso textarea prima del submit --- */
		$( 'form#post' ).on( 'submit', function () {
			cm.save();
		} );

		render();
	} );
}( jQuery ) );

/**
 * QA Kit front-end toolbar.
 *
 * Every check returns { items: [{ level, text, detail, el, selector, indent }] }.
 * Levels: error, warn, info, pass. The panel and the AI report both render from that shape.
 */
(function () {
	'use strict';

	var D = window.qaKitData;
	if (!D) return;

	var IGNORE = '#qa-kit-host, #qa-kit-highlight, #wpadminbar, #qa-kit-capture';
	var results = {};
	var state = { items: [] };
	var root, panel, body, tabsEl, activeTab = 'summary';

	var CHECKS = {
		a11y: { label: 'Accessibility', run: checkA11y },
		structure: { label: 'Structure', run: checkStructure },
		seo: { label: 'SEO', run: checkSeo },
		acf: { label: 'ACF', run: checkAcf },
		images: { label: 'Images', run: checkImages },
		performance: { label: 'Performance', run: checkPerformance },
		errors: { label: 'Errors', run: checkErrors },
		breakpoints: { label: 'Breakpoints', run: checkBreakpoints }
	};

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function ignored(el) {
		return !!(el && el.closest && el.closest(IGNORE));
	}

	function qsa(sel, ctx) {
		return Array.prototype.filter.call((ctx || document).querySelectorAll(sel), function (el) {
			return !ignored(el);
		});
	}

	function selectorFor(el) {
		if (!el || !el.tagName) return '';
		var parts = [];
		var node = el;
		for (var depth = 0; node && node.nodeType === 1 && depth < 4; depth++) {
			var part = node.tagName.toLowerCase();
			if (node.id) {
				parts.unshift(part + '#' + node.id);
				break;
			}
			var cls = (node.getAttribute('class') || '').trim().split(/\s+/).filter(Boolean).slice(0, 2);
			if (cls.length) part += '.' + cls.join('.');
			parts.unshift(part);
			node = node.parentElement;
		}
		return parts.join(' > ');
	}

	function visible(el) {
		var r = el.getBoundingClientRect();
		if (r.width === 0 && r.height === 0) return false;
		var cs = getComputedStyle(el);
		return cs.visibility !== 'hidden' && cs.display !== 'none';
	}

	// Screen-reader-only patterns: a 1x1 box, or clipped by the element or an ancestor.
	function visuallyHidden(el, rect, win) {
		if (rect.width <= 1 && rect.height <= 1) return true;
		for (var n = el; n && n.nodeType === 1; n = n.parentElement) {
			var cs = win.getComputedStyle(n);
			if (cs.clip && cs.clip !== 'auto') return true;
			if (/inset\(\s*50%/.test(cs.clipPath || '')) return true;
		}
		return false;
	}

	function navLabel(n) {
		var ids = (n.getAttribute('aria-labelledby') || '').trim();
		if (ids) {
			return ids.split(/\s+/).map(function (id) {
				var ref = document.getElementById(id);
				return ref ? ref.textContent : '';
			}).join(' ').trim();
		}
		return (n.getAttribute('aria-label') || '').trim();
	}

	function item(level, text, detail, el, extra) {
		var it = { level: level, text: text, detail: detail || '', el: el || null, selector: el ? selectorFor(el) : '' };
		if (extra) for (var k in extra) it[k] = extra[k];
		return it;
	}

	function kb(bytes) {
		return Math.round(bytes / 1024) + ' KB';
	}

	function counts(items) {
		var c = { error: 0, warn: 0, info: 0, pass: 0 };
		items.forEach(function (i) { c[i.level]++; });
		return c;
	}

	function loadScript(src) {
		return new Promise(function (resolve, reject) {
			var s = document.createElement('script');
			s.src = src;
			s.async = true;
			s.setAttribute('data-qa-kit', '');
			s.onload = resolve;
			s.onerror = function () { reject(new Error('Could not load ' + src)); };
			document.head.appendChild(s);
		});
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		// Fallback for http:// local sites.
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.style.cssText = 'position:fixed;left:-9999px;top:0';
		document.body.appendChild(ta);
		ta.select();
		var ok = document.execCommand('copy');
		ta.remove();
		return ok ? Promise.resolve() : Promise.reject(new Error('Copy failed'));
	}

	function frameUrl() {
		var u = new URL(location.href);
		u.hash = '';
		u.searchParams.set('qa-kit-frame', '1');
		return u.toString();
	}

	/* ------------------------------------------------------------------ */
	/* Checks                                                              */
	/* ------------------------------------------------------------------ */

	function checkA11y() {
		var ready = window.axe ? Promise.resolve() : loadScript(D.axeUrl);
		return ready.then(function () {
			return window.axe.run(
				{ exclude: [['#qa-kit-host'], ['#qa-kit-highlight'], ['#wpadminbar']] },
				{ resultTypes: ['violations', 'incomplete'] }
			);
		}).then(function (res) {
			var items = [];
			res.violations.forEach(function (v) {
				var level = v.impact === 'critical' || v.impact === 'serious' ? 'error' : 'warn';
				v.nodes.slice(0, 5).forEach(function (n, idx) {
					var target = typeof n.target[0] === 'string' ? n.target[0] : '';
					var el = null;
					try { el = target ? document.querySelector(target) : null; } catch (e) { /* bad selector */ }
					items.push(item(
						level,
						v.help + ' (' + v.id + ', ' + v.impact + ')',
						idx === 0 ? n.failureSummary.replace(/\n/g, ' ') + ' ' + v.helpUrl : n.failureSummary.replace(/\n/g, ' '),
						el,
						{ selector: target }
					));
				});
				if (v.nodes.length > 5) {
					items.push(item(level, v.help + ': ' + (v.nodes.length - 5) + ' more elements'));
				}
			});
			res.incomplete.slice(0, 15).forEach(function (v) {
				items.push(item('info', 'Needs manual review: ' + v.help + ' (' + v.id + ')', v.nodes.length + ' element(s). ' + v.helpUrl));
			});
			if (!res.violations.length) items.unshift(item('pass', 'No automated accessibility violations found'));
			items.push(item('info', 'Automated checks catch roughly a third of issues. Also test keyboard-only navigation, focus order and a screen reader.'));
			return { items: items };
		});
	}

	function checkStructure() {
		var items = [];

		// Heading outline.
		var headings = qsa('h1, h2, h3, h4, h5, h6, [role="heading"]').filter(visible);
		var h1s = headings.filter(function (h) { return levelOf(h) === 1; });
		if (h1s.length === 0) items.push(item('error', 'No H1 on the page'));
		if (h1s.length > 1) items.push(item('warn', h1s.length + ' H1 elements (expected 1)', '', h1s[1]));

		var prev = 0;
		var outline = [];
		headings.forEach(function (h) {
			var lvl = levelOf(h);
			var text = textOf(h);
			if (!text) items.push(item('error', 'Empty H' + lvl, '', h));
			if (prev && lvl > prev + 1) items.push(item('warn', 'Skipped heading level: H' + prev + ' to H' + lvl, text, h));
			outline.push(item('info', 'H' + lvl + ': ' + (text || '(empty)').slice(0, 90), '', h, { indent: lvl - 1, outline: true }));
			prev = lvl;
		});

		// Landmarks.
		var mains = qsa('main, [role="main"]').filter(visible);
		if (!mains.length) items.push(item('error', 'No <main> landmark'));
		if (mains.length > 1) items.push(item('warn', mains.length + ' visible <main> landmarks (expected 1)', '', mains[1]));
		if (!qsa('header, [role="banner"]').length) items.push(item('warn', 'No <header> / banner landmark'));
		if (!qsa('footer, [role="contentinfo"]').length) items.push(item('warn', 'No <footer> / contentinfo landmark'));
		var navs = qsa('nav, [role="navigation"]');
		if (navs.length > 1) {
			navs.forEach(function (n) {
				var label = navLabel(n);
				if (!label) {
					items.push(item('warn', 'Multiple <nav> elements: this one needs an aria-label', '', n));
				} else if (/^[\d\s]+$/.test(label)) {
					items.push(item('warn', 'Multiple <nav> elements: label "' + label + '" does not describe this one', 'Use a name such as "Footer" or "Social links".', n));
				}
			});
		}
		if (!qsa('a[href^="#"]').some(function (a) { return /skip/i.test(a.textContent); })) {
			items.push(item('warn', 'No skip link found (e.g. "Skip to content")'));
		}

		// Semantics.
		qsa('div[onclick], span[onclick], div[role="button"], span[role="button"]').forEach(function (el) {
			items.push(item('error', 'Clickable ' + el.tagName.toLowerCase() + ': use a <button> or <a>', '', el));
		});
		qsa('a:not([href])').filter(visible).forEach(function (el) {
			items.push(item('warn', '<a> without href (not focusable)', el.textContent.trim().slice(0, 60), el));
		});
		qsa('a[href="#"], a[href^="javascript:"]').filter(visible).forEach(function (el) {
			items.push(item('warn', 'Link used as a button (href="' + el.getAttribute('href') + '")', el.textContent.trim().slice(0, 60), el));
		});
		var stacked = qsa('br + br').filter(function (br) {
			// Only flag when nothing but whitespace sits between the two breaks.
			var n = br.previousSibling;
			while (n && n.nodeType === 3) {
				if (n.textContent.trim()) return false;
				n = n.previousSibling;
			}
			return n && n.nodeName === 'BR';
		});
		if (stacked.length) items.push(item('warn', stacked.length + ' stacked <br> tags used for spacing', '', stacked[0].parentElement));
		var emptyP = qsa('p').filter(function (p) { return !p.textContent.trim() && !p.querySelector('img, iframe, video, svg, input'); });
		if (emptyP.length) items.push(item('warn', emptyP.length + ' empty <p> elements (often stray wpautop or editor output)', '', emptyP[0]));
		qsa('table').forEach(function (t) {
			if (!t.querySelector('th')) items.push(item('warn', 'Table without <th> headers (layout table or missing headers?)', '', t));
		});

		var divs = qsa('div').length;
		var semantic = qsa('main, header, footer, nav, section, article, aside, figure, ul, ol').length;
		items.push(item('info', divs + ' <div> vs ' + semantic + ' semantic elements', 'A high ratio can mean div soup. Check sections, articles and lists are marked up as such.'));

		if (!items.some(function (i) { return i.level === 'error' || i.level === 'warn'; })) {
			items.unshift(item('pass', 'Heading outline and landmarks look good'));
		}
		return { items: items.concat(outline) };
	}

	function textOf(h) {
		var c = h.cloneNode(true);
		Array.prototype.forEach.call(c.querySelectorAll('.qa-kit-hlabel'), function (l) { l.remove(); });
		return c.textContent.trim().replace(/\s+/g, ' ');
	}

	function levelOf(h) {
		if (h.getAttribute('role') === 'heading') return parseInt(h.getAttribute('aria-level') || '2', 10);
		return parseInt(h.tagName.charAt(1), 10);
	}

	function meta(name) {
		var el = document.querySelector('meta[name="' + name + '"], meta[property="' + name + '"]');
		return el ? (el.getAttribute('content') || '').trim() : null;
	}

	function checkSeo() {
		var items = [];

		var title = document.title.trim();
		if (!title) items.push(item('error', 'Missing <title>'));
		else items.push(item(title.length < 30 || title.length > 60 ? 'warn' : 'pass', 'Title: ' + title.length + ' characters (aim for 30 to 60)', title));

		var desc = meta('description');
		if (!desc) items.push(item('error', 'Missing meta description'));
		else items.push(item(desc.length < 70 || desc.length > 160 ? 'warn' : 'pass', 'Meta description: ' + desc.length + ' characters (aim for 70 to 160)', desc));

		var canonical = document.querySelector('link[rel="canonical"]');
		if (!canonical) items.push(item('warn', 'No canonical link'));
		else {
			var here = location.origin + location.pathname;
			var href = canonical.href.split('#')[0].split('?')[0];
			items.push(item(href === here ? 'pass' : 'info', 'Canonical: ' + canonical.href, href === here ? '' : 'Differs from the current URL. Fine if intentional.'));
		}

		var robots = meta('robots') || '';
		if (/noindex/i.test(robots)) {
			items.push(item(D.env === 'production' ? 'error' : 'info', 'Robots meta: ' + robots, 'Expected on staging. Must be removed on production.'));
		}
		if (!D.blogPublic) items.push(item('info', 'Search engine visibility is set to discourage indexing', 'Turn this off at launch (Settings > Reading).'));

		var h1 = qsa('h1').filter(visible).length;
		if (h1 !== 1) items.push(item(h1 ? 'warn' : 'error', h1 + ' visible H1 elements (expected 1)'));

		if (!document.documentElement.getAttribute('lang')) items.push(item('error', 'Missing lang attribute on <html>'));
		if (!document.querySelector('meta[name="viewport"]')) items.push(item('error', 'Missing viewport meta tag'));
		if (!document.querySelector('link[rel~="icon"]')) items.push(item('warn', 'No favicon link'));

		['og:title', 'og:description', 'og:image', 'og:url', 'og:type'].forEach(function (p) {
			var v = meta(p);
			items.push(item(v ? 'pass' : 'warn', p + (v ? ': ' + v.slice(0, 90) : ' missing')));
		});
		if (!meta('twitter:card')) items.push(item('info', 'No twitter:card meta (falls back to Open Graph)'));

		var ld = qsa('script[type="application/ld+json"]');
		if (!ld.length) items.push(item('warn', 'No JSON-LD structured data'));
		ld.forEach(function (s) {
			try {
				var data = JSON.parse(s.textContent);
				var types = [];
				(function walk(o) {
					if (Array.isArray(o)) return o.forEach(walk);
					if (o && typeof o === 'object') {
						if (o['@type']) types.push([].concat(o['@type']).join('/'));
						if (o['@graph']) walk(o['@graph']);
					}
				})(data);
				items.push(item('pass', 'JSON-LD: ' + (types.join(', ') || 'no @type')));
			} catch (e) {
				items.push(item('error', 'Invalid JSON-LD: ' + e.message));
			}
		});

		var imgs = qsa('img');
		var noAlt = imgs.filter(function (i) { return !i.hasAttribute('alt'); });
		var emptyAlt = imgs.filter(function (i) { return i.getAttribute('alt') === ''; });
		if (noAlt.length) items.push(item('error', noAlt.length + ' of ' + imgs.length + ' image(s) missing an alt attribute', '', noAlt[0]));
		if (emptyAlt.length) items.push(item('info', emptyAlt.length + ' image(s) with alt="" (fine only if decorative)', '', emptyAlt[0]));

		var generic = /^(click here|here|read more|learn more|more|link|this)$/i;
		qsa('a[href]').filter(function (a) {
			return generic.test(a.textContent.trim()) && !a.getAttribute('aria-label');
		}).slice(0, 10).forEach(function (a) {
			items.push(item('warn', 'Generic link text: "' + a.textContent.trim() + '"', a.href, a));
		});

		var links = qsa('a[href]');
		var external = links.filter(function (a) { return a.hostname && a.hostname !== location.hostname; });
		items.push(item('info', links.length + ' links: ' + (links.length - external.length) + ' internal, ' + external.length + ' external'));

		return { items: items };
	}

	function checkAcf() {
		var items = [];
		if (D.acf === null) return { items: [item('info', 'ACF is not active')] };
		if (!D.acf.length) return { items: [item('info', 'No ACF field groups are assigned to this page')] };
		D.acf.forEach(function (group) {
			items.push(item('info', 'Group: ' + group.title + ' (' + group.key + ')', group.fields.length + ' fields', null, { group: true }));
			group.fields.forEach(function (f) {
				var label = f.label + ' (' + f.name + ', ' + f.type + ')';
				if (f.empty && f.required) items.push(item('error', 'Required but empty: ' + label, '', null, { indent: 1 }));
				else if (f.empty) items.push(item('warn', 'Empty: ' + label, 'Check the template handles this gracefully.', null, { indent: 1 }));
				else items.push(item('pass', label, f.preview, null, { indent: 1 }));
			});
		});
		items.push(item('info', 'Run the ACF audit in Tools > QA Kit to find fields that are defined but never used in the theme, and vice versa.'));
		return { items: items };
	}

	function checkImages() {
		var items = [];
		var dpr = window.devicePixelRatio || 1;
		var imgs = qsa('img');
		imgs.forEach(function (img) {
			var src = img.currentSrc || img.src;
			var name = src.split('/').pop().split('?')[0];
			if (img.complete && img.naturalWidth === 0) {
				items.push(item('error', 'Broken image: ' + name, src, img));
				return;
			}
			if (!img.complete) return;
			var rect = img.getBoundingClientRect();
			var top = rect.top + window.scrollY;

			if (rect.width > 0) {
				var needed = rect.width * dpr;
				if (img.naturalWidth > needed * 1.5 && img.naturalWidth - needed > 300) {
					items.push(item('warn', 'Oversized: ' + name, img.naturalWidth + 'px wide, shown at ' + Math.round(rect.width) + 'px (' + Math.round(needed) + 'px at ' + dpr + 'x)', img));
				}
			}
			if (!img.getAttribute('width') || !img.getAttribute('height')) {
				items.push(item('warn', 'No width/height attributes: ' + name, 'Causes layout shift while loading.', img));
			}
			if (!img.srcset && img.naturalWidth > 800) {
				items.push(item('info', 'No srcset: ' + name, 'Use wp_get_attachment_image() so WordPress outputs responsive sizes.', img));
			}
			if (img.loading === 'lazy' && top < window.innerHeight && rect.width > 0) {
				items.push(item('warn', 'Lazy-loaded above the fold: ' + name, 'Delays LCP. Remove loading="lazy" or set fetchpriority="high" on the hero image.', img));
			}
			if (img.loading !== 'lazy' && top > window.innerHeight * 2) {
				items.push(item('info', 'Not lazy-loaded below the fold: ' + name, '', img));
			}
			var entry = performance.getEntriesByName(src)[0];
			var size = entry ? (entry.encodedBodySize || entry.transferSize || 0) : 0;
			if (size > 300 * 1024) {
				items.push(item('warn', 'Heavy file: ' + name + ' (' + kb(size) + ')', '', img));
			} else if (size > 100 * 1024 && /\.(jpe?g|png)$/i.test(name)) {
				items.push(item('info', name + ' is ' + kb(size) + ': WebP or AVIF would be smaller', '', img));
			}
		});
		if (!items.length) items.push(item('pass', imgs.length + ' images checked, no issues'));
		else items.unshift(item('info', imgs.length + ' images checked'));
		return { items: items };
	}

	function observe(type) {
		return new Promise(function (resolve) {
			var entries = [];
			try {
				var po = new PerformanceObserver(function (list) { entries = entries.concat(list.getEntries()); });
				po.observe({ type: type, buffered: true });
				setTimeout(function () { po.disconnect(); resolve(entries); }, 150);
			} catch (e) {
				resolve(null);
			}
		});
	}

	function checkPerformance() {
		return Promise.all([observe('largest-contentful-paint'), observe('layout-shift')]).then(function (res) {
			var items = [];
			var nav = performance.getEntriesByType('navigation')[0];
			if (nav) {
				var ttfb = Math.round(nav.responseStart);
				items.push(item(ttfb > 800 ? 'warn' : 'pass', 'Server response (TTFB): ' + ttfb + ' ms', ttfb > 800 ? 'Slow PHP or no page cache. Check queries with Query Monitor.' : ''));
				items.push(item('info', 'DOMContentLoaded ' + Math.round(nav.domContentLoadedEventEnd) + ' ms, load ' + Math.round(nav.loadEventEnd) + ' ms'));
			}

			var lcp = res[0];
			if (lcp && lcp.length) {
				var last = lcp[lcp.length - 1];
				var ms = Math.round(last.startTime);
				items.push(item(ms > 4000 ? 'error' : ms > 2500 ? 'warn' : 'pass', 'Largest Contentful Paint: ' + ms + ' ms (good under 2500)', last.element ? 'LCP element: ' + selectorFor(last.element) : '', last.element && !ignored(last.element) ? last.element : null));
			}
			var shifts = res[1];
			if (shifts) {
				var cls = shifts.filter(function (s) { return !s.hadRecentInput; }).reduce(function (a, s) { return a + s.value; }, 0);
				items.push(item(cls > 0.25 ? 'error' : cls > 0.1 ? 'warn' : 'pass', 'Cumulative Layout Shift: ' + cls.toFixed(3) + ' (good under 0.1)'));
			}

			var resources = performance.getEntriesByType('resource').filter(function (r) {
				return r.name.indexOf('qa-kit') === -1;
			});
			var byType = {};
			var total = 0;
			var hosts = {};
			resources.forEach(function (r) {
				var size = r.transferSize || r.encodedBodySize || 0;
				var t = r.initiatorType === 'link' && /\.css/.test(r.name) ? 'css' : r.initiatorType;
				byType[t] = (byType[t] || 0) + size;
				total += size;
				try {
					var h = new URL(r.name).hostname;
					if (h !== location.hostname) hosts[h] = true;
				} catch (e) { /* ignore */ }
			});
			items.push(item('info', resources.length + ' requests, ' + kb(total) + ' total', Object.keys(byType).map(function (t) { return t + ' ' + kb(byType[t]); }).join(', ')));
			if ((byType.script || 0) > 500 * 1024) items.push(item('warn', 'JavaScript weighs ' + kb(byType.script), 'Check for plugins loading scripts on every page.'));
			var third = Object.keys(hosts);
			if (third.length) items.push(item('info', third.length + ' third-party hosts', third.join(', ')));

			qsa('head script[src]:not([async]):not([defer]):not([type="module"]):not([data-qa-kit])').forEach(function (s) {
				items.push(item('warn', 'Render-blocking script in <head>: ' + s.src.split('/').pop().split('?')[0], 'Load with defer, or enqueue in the footer with wp_enqueue_script( ..., true ).'));
			});

			var nodes = document.getElementsByTagName('*').length;
			items.push(item(nodes > 1500 ? 'warn' : 'pass', 'DOM size: ' + nodes + ' elements (aim for under 1500)'));
			items.push(item('info', 'Local timings are not representative. Run PageSpeed Insights against staging for real numbers.'));
			return { items: items };
		});
	}

	function checkErrors() {
		var items = [];
		(D.phpErrors || []).forEach(function (e) {
			items.push(item(e.type === 'Warning' ? 'error' : 'warn', 'PHP ' + e.type + ': ' + e.message, e.file + ':' + e.line));
		});
		(window.__qaKitErrors || []).forEach(function (e) {
			var label = { js: 'JS error', promise: 'Unhandled promise rejection', console: 'console.error', resource: 'Failed request' }[e.type] || e.type;
			items.push(item('error', label + ': ' + e.message, e.source || ''));
		});
		if (!items.length) items.push(item('pass', 'No PHP notices, JS errors or failed requests captured'));
		return { items: items };
	}

	function loadFrame(width, container) {
		return new Promise(function (resolve) {
			var f = document.createElement('iframe');
			f.style.cssText = 'position:absolute;left:-20000px;top:0;width:' + width + 'px;height:900px;border:0;';
			f.setAttribute('aria-hidden', 'true');
			var done = false;
			var timer = setTimeout(function () { finish(); }, 20000);
			function finish() {
				if (done) return;
				done = true;
				clearTimeout(timer);
				resolve(f);
			}
			f.onload = function () { setTimeout(finish, 600); };
			f.src = frameUrl();
			container.appendChild(f);
		});
	}

	function inspectFrame(f, width) {
		var items = [];
		var doc;
		try { doc = f.contentDocument; } catch (e) { doc = null; }
		if (!doc || !doc.body) {
			return [item('info', width + 'px: could not load the page in a frame (X-Frame-Options?)')];
		}
		var sw = doc.documentElement.scrollWidth;
		if (sw > width + 1) {
			items.push(item('error', width + 'px: horizontal scroll, page is ' + sw + 'px wide'));
			var offenders = Array.prototype.filter.call(doc.body.querySelectorAll('*'), function (el) {
				var r = el.getBoundingClientRect();
				if (r.width === 0 || r.right <= width + 1) return false;
				var p = el.parentElement;
				return !p || p.getBoundingClientRect().right <= width + 1;
			}).slice(0, 5);
			offenders.forEach(function (el) {
				items.push(item('error', width + 'px: element overflows the viewport', selectorFor(el) + ' (right edge ' + Math.round(el.getBoundingClientRect().right) + 'px)', null, { selector: selectorFor(el), indent: 1 }));
			});
		} else {
			items.push(item('pass', width + 'px: no horizontal scroll'));
		}
		if (width < 600) {
			var small = Array.prototype.filter.call(doc.body.querySelectorAll('p, li, a, span, td, label, small, button'), function (el) {
				return el.childNodes.length && el.textContent.trim() && parseFloat(f.contentWindow.getComputedStyle(el).fontSize) < 12 && el.getBoundingClientRect().width > 0;
			});
			if (small.length) items.push(item('warn', width + 'px: ' + small.length + ' text elements under 12px', selectorFor(small[0])));
			var tiny = Array.prototype.filter.call(doc.body.querySelectorAll('a, button, input, select'), function (el) {
				var r = el.getBoundingClientRect();
				if (!(r.width > 0 && r.height > 0 && (r.width < 24 || r.height < 24))) return false;
				// Links in running text are exempt from the target size rule (WCAG 2.5.8).
				if (el.tagName === 'A' && el.closest('p')) return false;
				return !visuallyHidden(el, r, f.contentWindow);
			});
			if (tiny.length) {
				items.push(item('warn', width + 'px: ' + tiny.length + ' tap targets smaller than 24x24px'));
				tiny.slice(0, 10).forEach(function (el) {
					var r = el.getBoundingClientRect();
					items.push(item('info', width + 'px: small tap target', Math.round(r.width) + 'x' + Math.round(r.height) + 'px', null, { selector: selectorFor(el), indent: 1 }));
				});
				if (tiny.length > 10) items.push(item('info', width + 'px: ' + (tiny.length - 10) + ' more small tap targets', '', null, { indent: 1 }));
			}
		}
		return items;
	}

	function checkBreakpoints() {
		var holder = document.createElement('div');
		holder.id = 'qa-kit-frames';
		holder.style.cssText = 'position:absolute;left:0;top:0;width:0;height:0;overflow:hidden;';
		root.appendChild(holder);
		return Promise.all(D.breakpoints.map(function (w) {
			return loadFrame(w, holder).then(function (f) { return inspectFrame(f, w); });
		})).then(function (groups) {
			holder.remove();
			var items = [].concat.apply([], groups);
			items.push(item('info', 'Automated checks only catch overflow and sizing. Open the preview to eyeball each breakpoint.'));
			return { items: items };
		});
	}

	/* ------------------------------------------------------------------ */
	/* Panel UI                                                            */
	/* ------------------------------------------------------------------ */

	function build() {
		if (root) return;
		var host = document.createElement('div');
		host.id = 'qa-kit-host';
		document.body.appendChild(host);
		root = host.attachShadow({ mode: 'open' });
		root.innerHTML =
			'<style>.panel:not(.open){display:none}</style>' +
			'<link rel="stylesheet" href="' + esc(D.cssUrl) + '">' +
			'<aside class="panel" role="dialog" aria-label="QA Kit">' +
			'<div class="hd"><strong>QA Kit</strong><span class="ver">' + esc(D.version) + '</span>' +
			'<button class="run-all" type="button">Run all</button>' +
			'<button class="close" type="button" aria-label="Close QA Kit">&times;</button></div>' +
			'<nav class="tabs"></nav>' +
			'<div class="body" tabindex="-1"></div>' +
			'<div class="ft"><button class="report" type="button">Copy report for AI</button>' +
			'<a href="' + esc(D.promptsUrl) + '">Prompt library</a></div>' +
			'</aside>';
		panel = root.querySelector('.panel');
		body = root.querySelector('.body');
		tabsEl = root.querySelector('.tabs');
		root.querySelector('.close').addEventListener('click', close);
		root.querySelector('.run-all').addEventListener('click', function () { runAll(); });
		root.querySelector('.report').addEventListener('click', copyReport);
		renderTabs();
		body.addEventListener('click', onBodyClick);
	}

	function open(tab) {
		build();
		panel.classList.add('open');
		if (tab) show(tab);
		else render();
	}

	function close() {
		if (panel) panel.classList.remove('open');
		closePreview();
	}

	function renderTabs() {
		var html = tab('summary', 'Summary', null);
		Object.keys(CHECKS).forEach(function (id) {
			html += tab(id, CHECKS[id].label, results[id]);
		});
		tabsEl.innerHTML = html;
		Array.prototype.forEach.call(tabsEl.querySelectorAll('button'), function (b) {
			b.addEventListener('click', function () { show(b.getAttribute('data-tab')); });
		});
	}

	function tab(id, label, res) {
		var badge = '';
		if (res && res.items) {
			var c = counts(res.items);
			if (c.error) badge = '<span class="b error">' + c.error + '</span>';
			else if (c.warn) badge = '<span class="b warn">' + c.warn + '</span>';
			else badge = '<span class="b pass">&#10003;</span>';
		} else if (res && res.running) {
			badge = '<span class="b run">&hellip;</span>';
		}
		return '<button type="button" data-tab="' + id + '" class="' + (activeTab === id ? 'active' : '') + '">' + esc(label) + badge + '</button>';
	}

	function show(id) {
		activeTab = id;
		if (id !== 'summary' && !results[id]) {
			runCheck(id);
		}
		renderTabs();
		render();
	}

	function runCheck(id) {
		results[id] = { running: true };
		renderTabs();
		if (activeTab === id) render();
		return Promise.resolve()
			.then(function () { return CHECKS[id].run(); })
			.catch(function (e) { return { items: [item('error', 'Check failed: ' + e.message)] }; })
			.then(function (res) {
				results[id] = res;
				renderTabs();
				if (activeTab === id || activeTab === 'summary') render();
				return res;
			});
	}

	function runAll() {
		open('summary');
		return Promise.all(Object.keys(CHECKS).map(runCheck)).then(function () { render(); });
	}

	function render() {
		if (!body) return;
		if (activeTab === 'summary') return renderSummary();
		var res = results[activeTab];
		var html = '<div class="tab-head"><h2>' + esc(CHECKS[activeTab].label) + '</h2>' +
			'<button type="button" class="rerun" data-rerun="' + activeTab + '">Re-run</button>';
		if (activeTab === 'structure') html += '<button type="button" class="outline-toggle">Label headings on page</button>';
		if (activeTab === 'breakpoints') html += '<button type="button" class="preview-open">Open preview</button>';
		html += '</div>';
		if (!res || res.running) {
			html += '<p class="muted">Running&hellip;</p>';
		} else {
			html += renderItems(res.items);
		}
		body.innerHTML = html;
	}

	function renderItems(items) {
		state.items = items;
		return '<ul class="items">' + items.map(function (it, i) {
			var clickable = it.el ? ' clickable' : '';
			var style = it.indent ? ' style="padding-left:' + (10 + it.indent * 14) + 'px"' : '';
			return '<li class="item ' + it.level + clickable + (it.group ? ' group' : '') + '" data-i="' + i + '"' + style + '>' +
				'<span class="lvl">' + (it.level === 'warn' ? 'warning' : it.level) + '</span>' +
				'<div><div class="t">' + esc(it.text) + '</div>' +
				(it.detail ? '<div class="d">' + esc(it.detail) + '</div>' : '') +
				(it.selector ? '<code>' + esc(it.selector) + '</code>' : '') +
				'</div></li>';
		}).join('') + '</ul>';
	}

	function renderSummary() {
		var html = '<div class="tab-head"><h2>Summary</h2></div>' +
			'<dl class="ctx"><dt>Page</dt><dd>' + esc(D.page.object) + '</dd>' +
			'<dt>Template</dt><dd><code>' + esc(D.page.template || 'unknown') + '</code></dd>' +
			'<dt>Environment</dt><dd>' + esc(D.env) + '</dd></dl>';
		var ran = Object.keys(CHECKS).filter(function (id) { return results[id]; });
		if (!ran.length) {
			html += '<p class="muted">Pick a tab to run one check, or run them all.</p><button type="button" class="primary run-all-inline">Run all checks</button>';
		} else {
			html += '<table class="sum"><thead><tr><th>Check</th><th>Errors</th><th>Warnings</th></tr></thead><tbody>';
			Object.keys(CHECKS).forEach(function (id) {
				var r = results[id];
				var c = r && r.items ? counts(r.items) : null;
				html += '<tr data-tab="' + id + '"><td>' + esc(CHECKS[id].label) + '</td>' +
					'<td class="' + (c && c.error ? 'error' : '') + '">' + (r ? (r.running ? '&hellip;' : c.error) : '-') + '</td>' +
					'<td class="' + (c && c.warn ? 'warn' : '') + '">' + (r ? (r.running ? '&hellip;' : c.warn) : '-') + '</td></tr>';
			});
			html += '</tbody></table>';
		}
		html += '<h3>Theme files rendered</h3><ul class="files">' + D.page.files.map(function (f) { return '<li><code>' + esc(f) + '</code></li>'; }).join('') + '</ul>';
		body.innerHTML = html;
	}

	function onBodyClick(e) {
		var t = e.target;
		if (t.closest('.run-all-inline')) return runAll();
		var rerun = t.closest('[data-rerun]');
		if (rerun) return runCheck(rerun.getAttribute('data-rerun'));
		if (t.closest('.outline-toggle')) return toggleOutline();
		if (t.closest('.preview-open')) return openPreview();
		var row = t.closest('tr[data-tab]');
		if (row) return show(row.getAttribute('data-tab'));
		var li = t.closest('.item.clickable');
		if (li) {
			var it = state.items[parseInt(li.getAttribute('data-i'), 10)];
			if (it && it.el) highlight(it.el);
		}
	}

	function highlight(el) {
		el.scrollIntoView({ behavior: 'smooth', block: 'center' });
		setTimeout(function () {
			var old = document.getElementById('qa-kit-highlight');
			if (old) old.remove();
			var r = el.getBoundingClientRect();
			var box = document.createElement('div');
			box.id = 'qa-kit-highlight';
			box.style.cssText = 'position:absolute;z-index:2147483646;pointer-events:none;outline:3px solid #e11d48;outline-offset:2px;background:rgba(225,29,72,.12);transition:opacity .4s;' +
				'left:' + (r.left + scrollX) + 'px;top:' + (r.top + scrollY) + 'px;width:' + Math.max(r.width, 4) + 'px;height:' + Math.max(r.height, 4) + 'px';
			document.body.appendChild(box);
			setTimeout(function () { box.style.opacity = '0'; }, 2200);
			setTimeout(function () { box.remove(); }, 2700);
		}, 350);
	}

	var outlineOn = false;
	function toggleOutline() {
		outlineOn = !outlineOn;
		qsa('h1, h2, h3, h4, h5, h6').forEach(function (h) {
			var existing = h.querySelector(':scope > .qa-kit-hlabel');
			if (existing) existing.remove();
			if (outlineOn) {
				var tag = document.createElement('span');
				tag.className = 'qa-kit-hlabel';
				tag.textContent = h.tagName;
				tag.style.cssText = 'display:inline-block;margin-right:6px;padding:1px 5px;font:600 11px/1.4 monospace;color:#fff;background:#7c3aed;border-radius:3px;vertical-align:middle;';
				h.insertBefore(tag, h.firstChild);
			}
		});
		var btn = root.querySelector('.outline-toggle');
		if (btn) btn.textContent = outlineOn ? 'Hide heading labels' : 'Label headings on page';
	}

	function openPreview() {
		closePreview();
		var wrap = document.createElement('div');
		wrap.className = 'preview';
		var scale = 0.5;
		wrap.innerHTML = '<div class="preview-bar"><strong>Breakpoints</strong>' +
			'<label>Zoom <select class="zoom"><option value="0.35">35%</option><option value="0.5" selected>50%</option><option value="0.75">75%</option><option value="1">100%</option></select></label>' +
			'<button type="button" class="preview-close">Close</button></div><div class="frames"></div>';
		var frames = wrap.querySelector('.frames');
		D.breakpoints.forEach(function (w) {
			var fig = document.createElement('figure');
			fig.innerHTML = '<figcaption>' + w + 'px</figcaption><div class="fwrap"><iframe title="' + w + 'px preview" src="' + esc(frameUrl()) + '" width="' + w + '" height="1000"></iframe></div>';
			frames.appendChild(fig);
		});
		function applyScale() {
			Array.prototype.forEach.call(frames.querySelectorAll('.fwrap'), function (fw, i) {
				var w = D.breakpoints[i];
				fw.style.width = (w * scale) + 'px';
				fw.style.height = (1000 * scale) + 'px';
				fw.firstChild.style.transform = 'scale(' + scale + ')';
			});
		}
		applyScale();
		wrap.querySelector('.zoom').addEventListener('change', function (e) { scale = parseFloat(e.target.value); applyScale(); });
		wrap.querySelector('.preview-close').addEventListener('click', closePreview);
		root.appendChild(wrap);
	}

	function closePreview() {
		var p = root && root.querySelector('.preview');
		if (p) p.remove();
	}

	/* ------------------------------------------------------------------ */
	/* AI report                                                           */
	/* ------------------------------------------------------------------ */

	function buildReport() {
		var lines = [];
		lines.push('# QA Kit report');
		lines.push('');
		lines.push('- URL: ' + location.href);
		lines.push('- Page: ' + D.page.object);
		lines.push('- Template: `' + (D.page.template || 'unknown') + '`');
		lines.push('- Theme: ' + D.page.theme);
		lines.push('- Environment: ' + D.env);
		lines.push('- Viewport when tested: ' + innerWidth + 'x' + innerHeight + ' @' + (devicePixelRatio || 1) + 'x');
		lines.push('');
		lines.push('## Theme files that rendered this page');
		D.page.files.forEach(function (f) { lines.push('- `' + f + '`'); });

		Object.keys(CHECKS).forEach(function (id) {
			var r = results[id];
			if (!r || !r.items) return;
			var c = counts(r.items);
			lines.push('');
			lines.push('## ' + CHECKS[id].label + ' (' + c.error + ' errors, ' + c.warn + ' warnings)');
			var passes = 0;
			r.items.forEach(function (it) {
				if (it.outline) return;
				if (it.level === 'pass' && id !== 'seo') { passes++; return; }
				var line = '- [' + it.level + '] ' + it.text;
				if (it.detail) line += ' | ' + it.detail;
				if (it.selector) line += ' | `' + it.selector + '`';
				lines.push(line);
			});
			if (passes) lines.push('- ' + passes + ' checks passed');
			if (id === 'structure') {
				lines.push('');
				lines.push('Heading outline:');
				r.items.filter(function (i) { return i.outline; }).forEach(function (i) {
					lines.push(new Array(i.indent + 1).join('  ') + '- ' + i.text);
				});
			}
		});

		lines.push('');
		lines.push('---');
		lines.push('');
		lines.push('You are reviewing automated QA findings for the WordPress page above. The theme files that rendered it are listed.');
		lines.push('');
		lines.push('1. For each error and warning, find the likely cause in those files (or name the plugin responsible) and give a specific fix with the file and code change.');
		lines.push('2. Mark anything that is expected on a ' + D.env + ' site, or is a false positive, and say why.');
		lines.push('3. List the fixes in priority order: errors first, then warnings that affect users most.');
		lines.push('4. Note anything these automated checks cannot catch that is worth testing by hand on this page.');
		return lines.join('\n');
	}

	function copyReport() {
		var missing = Object.keys(CHECKS).filter(function (id) { return !results[id] || results[id].running; });
		var btn = root.querySelector('.report');
		btn.textContent = 'Running checks…';
		var ready = missing.length ? runAll() : Promise.resolve();
		return ready.then(function () {
			var report = buildReport();
			return copyText(report).then(function () {
				btn.textContent = 'Copied!';
			}, function () {
				btn.textContent = 'Copy failed: select the text below';
				activeTab = 'summary';
				renderSummary();
				body.insertAdjacentHTML('beforeend', '<textarea class="report-text" readonly>' + esc(report) + '</textarea>');
			});
		}).then(function () {
			setTimeout(function () { btn.textContent = 'Copy report for AI'; }, 2500);
		});
	}

	/* ------------------------------------------------------------------ */
	/* Admin bar wiring                                                    */
	/* ------------------------------------------------------------------ */

	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('#wp-admin-bar-qa-kit > a, #wp-admin-bar-qa-kit .ab-submenu a');
		if (!a) return;
		var href = a.getAttribute('href') || '';
		if (href.indexOf('#qa-kit') !== 0) return;
		e.preventDefault();
		var id = href.replace('#qa-kit', '').replace(/^-/, '');
		if (!id) return panel && panel.classList.contains('open') ? close() : open();
		if (id === 'all') return runAll();
		if (id === 'report') { open('summary'); return copyReport(); }
		if (id === 'breakpoints') { open('breakpoints'); return; }
		open(id);
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && panel && panel.classList.contains('open')) close();
	});
})();

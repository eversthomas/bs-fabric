# Design-System-Richtlinien für Premium-WordPress-Plugins (Flexible CSS-Modell)
> **Branding & Urheber:** Tom Evers · [bezugssysteme.de](https://bezugssysteme.de)
> **Zweck:** Leitfaden für KI-Codegeneratoren zur Replikation des Premium-UX-Designs von Tom Evers in beliebigen WordPress-Plugins.

---

## 1. Das Namensraum- & Präfix-Prinzip (Dynamische Klassen)

Um Kollisionen im WordPress-Backend zu vermeiden und den Code auf das jeweilige Plugin anzupassen, **dürfen keine statischen Klassen wie `bsf-` hartcodiert werden**. 

Stattdessen gilt das **Präfix-Prinzip**:
* Jedes Plugin definiert ein eigenes, kurzes Präfix (z. B. `tea-` für *Tom-Evers-Analytics* oder `tef-` für *Te-Fabric*).
* In den nachfolgenden Beispielen wird das Platzhalter-Präfix `[prefix]-` verwendet. Die KI muss dieses Präfix beim Generieren von Code durch den Namensraum des jeweiligen Plugins ersetzen.
* Das gesamte Design wird in einer App-Shell gekapselt, die auf der Klasse `.[prefix]-app` basiert.

---

## 2. Design Tokens (CSS)

Binde diese CSS-Variablen am Anfang Deines Stylesheets ein, gekapselt im App-Container:

```css
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Hanken+Grotesk:ital,wght@0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500;600&display=swap');

.[prefix]-app {
	/* Typografie */
	--font-brand: 'Space Grotesk', system-ui, sans-serif; /* Titel, Stats & Markenelemente */
	--font-ui: 'Hanken Grotesk', system-ui, sans-serif;    /* Fließtext, UI & Formulare */
	--font-mono: 'JetBrains Mono', ui-monospace, monospace; /* Keys, IDs & Code */

	/* Perzeptive Farbpalette (OKLCH) */
	--bg: oklch(0.975 0.004 255);
	--surface: oklch(1 0 0);
	--surface-2: oklch(0.985 0.004 255);
	--surface-sunken: oklch(0.96 0.005 255);
	--border: oklch(0.92 0.006 255);
	--border-strong: oklch(0.86 0.008 255);

	/* Textkontrast (Tinten-Skala) */
	--ink: oklch(0.24 0.012 260);
	--ink-soft: oklch(0.42 0.012 260);
	--muted: oklch(0.58 0.012 260);
	--faint: oklch(0.72 0.01 260);

	/* Akzentfarben (Vibrantes Tom-Evers-Akzentblau) */
	--accent: oklch(0.55 0.142 252);
	--accent-press: oklch(0.48 0.142 252);
	--accent-soft: oklch(0.95 0.03 252);
	--accent-soft-bd: oklch(0.88 0.06 252);
	--accent-ink: oklch(0.40 0.14 252);

	/* Sekundär-Faden (Teal) */
	--thread: oklch(0.62 0.11 175);
	--thread-soft: oklch(0.95 0.035 175);
	--thread-ink: oklch(0.42 0.10 175);

	/* Statusfarben */
	--ok: oklch(0.60 0.12 155);
	--ok-soft: oklch(0.95 0.04 155);
	--warn: oklch(0.70 0.13 70);
	--warn-soft: oklch(0.96 0.05 75);
	--danger: oklch(0.57 0.17 25);
	--danger-soft: oklch(0.95 0.04 25);

	/* Geometrie & Radien */
	--r-sm: 6px;
	--r: 9px;
	--r-lg: 13px;
	--r-xl: 18px;

	/* Schatten */
	--shadow-sm: 0 1px 2px oklch(0.2 0.02 260 / 0.06), 0 1px 3px oklch(0.2 0.02 260 / 0.05);
	--shadow: 0 2px 6px oklch(0.2 0.02 260 / 0.07), 0 8px 24px oklch(0.2 0.02 260 / 0.06);
	--shadow-lg: 0 12px 40px oklch(0.2 0.02 260 / 0.16);

	/* Layout-Abstände */
	--row-h: 44px;
	--pad: 22px;
	--gap: 16px;

	/* Reset & Basis */
	font-family: var(--font-ui);
	font-size: 14.5px;
	line-height: 1.5;
	color: var(--ink);
	-webkit-font-smoothing: antialiased;
}

/* Dynamische Dichte-Steuerung */
.[prefix]-app[data-density="compact"] {
	--row-h: 38px;
	--pad: 16px;
	--gap: 12px;
}
.[prefix]-app[data-density="comfy"] {
	--row-h: 52px;
	--pad: 28px;
	--gap: 20px;
}
```

---

## 3. Semantische HTML-Komponenten-Blueprints

Die Struktur der Komponenten folgt festen Mustern. Die KI soll Klassen generieren, die das vereinbarte Präfix tragen.

### 3.1 App-Shell mit Branding-Footer
Die Hauptstruktur des Plugins enthält oben eine Breadcrumb-Topbar und unten einen minimalistischen Branding-Verweis im Tom-Evers-Stil:

```html
<div class="[prefix]-app">
	<div class="[prefix]-main">
		<!-- Topbar -->
		<div class="[prefix]-topbar">
			<div class="[prefix]-crumbs">
				<a href="#">Dashboard</a>
				<span class="[prefix]-crumb-sep">/</span>
				<span class="[prefix]-crumb-here">Ansicht</span>
			</div>
			<div class="[prefix]-topbar-spacer"></div>
		</div>

		<!-- Main Content Area -->
		<div class="[prefix]-content">
			<div class="[prefix]-page">
				<!-- Page Head -->
				<div class="[prefix]-page-head">
					<div>
						<h1>Plugin-Name</h1>
						<p class="[prefix]-page-lede">Eine kurze Erläuterung der aktuellen Seite.</p>
					</div>
					<div class="[prefix]-page-actions">
						<button class="[prefix]-btn [prefix]-btn-ghost">Abbrechen</button>
						<button class="[prefix]-btn [prefix]-btn-primary">Aktion ausführen</button>
					</div>
				</div>

				<!-- Hier folgt das Inhalts-Layout (z.B. Cards, Tabellen, Formulare) -->
				
				<!-- Branding-Footer -->
				<footer class="[prefix]-branding-footer" style="margin-top: 48px; border-top: 1px solid var(--border); padding-top: 16px; font-size: 12px; color: var(--muted); display: flex; justify-content: space-between;">
					<span>Tom Evers · <a href="https://bezugssysteme.de" target="_blank" style="color: inherit; text-decoration: none;">bezugssysteme.de</a></span>
					<span>v1.0.0</span>
				</footer>
			</div>
		</div>
	</div>
</div>
```

### 3.2 Interaktions-Elemente & Kontrollen
Egal für welches Plugin, Tasten und Eingabefelder müssen denselben Interaktionsprinzipien folgen:

```html
<!-- Buttons: primary, ghost, subtle, danger -->
<button class="[prefix]-btn [prefix]-btn-primary">Speichern</button>
<button class="[prefix]-btn [prefix]-btn-ghost">Abbrechen</button>
<button class="[prefix]-btn [prefix]-btn-danger [prefix]-btn-sm">Löschen</button>

<!-- Formular-Eingabefelder (Fokus erhält 3px accent-soft Box-Shadow-Ring) -->
<div class="[prefix]-field">
	<label class="[prefix]-flabel">Bezeichnung</label>
	<input type="text" class="[prefix]-input" placeholder="Wert eintragen..." />
</div>
```

### 3.3 Datenpräsentation (Karten & Tabellen)
```html
<!-- Karte -->
<div class="[prefix]-card">
	<div class="[prefix]-card-head">
		<h3>Karten-Überschrift</h3>
	</div>
	<div class="[prefix]-card-body">
		<!-- Karten-Inhalt -->
	</div>
</div>

<!-- Tabelle -->
<table class="[prefix]-tbl">
	<thead>
		<tr>
			<th>Spalte A</th>
			<th>Spalte B</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td class="[prefix]-cell-strong">Datenzeile 1</td>
			<td><span class="[prefix]-badge [prefix]-badge-ok">Aktiv</span></td>
		</tr>
	</tbody>
</table>
```

### 3.4 Slide-Over Sheets
Sheets werden für Detailansichten oder Formulare genutzt, die keinen Seitenwechsel erfordern:

```html
<div class="[prefix]-scrim is-open"></div>
<div class="[prefix]-sheet is-open">
	<div class="[prefix]-sheet-head">
		<h2>Details bearbeiten</h2>
	</div>
	<div class="[prefix]-sheet-body">
		<!-- Dynamisches Formular -->
	</div>
	<div class="[prefix]-sheet-foot">
		<button class="[prefix]-btn [prefix]-btn-ghost">Schließen</button>
		<button class="[prefix]-btn [prefix]-btn-primary" style="margin-left: auto;">Sichern</button>
	</div>
</div>
```

---

## 4. WordPress-Admin-Bridge

Um das Standard-Grau von WordPress im Hintergrund auszublenden, hooke dieses CSS auf Deinen Plugin-Seiten ein:

```css
body.toplevel_page_[plugin-slug] #wpcontent,
body.toplevel_page_[plugin-slug] #wpbody-content {
	background: oklch(0.975 0.004 255) !important;
}

body.toplevel_page_[plugin-slug] .wrap.[prefix]-wrap {
	margin: 0;
	max-width: none;
	padding: 0;
}
```

---

## 5. System-Prompt-Anweisungen für KI-Generatoren

Wenn Du Code für eine Benutzeroberfläche dieses Plugins generierst, beachte Folgendes:
1. **Verwende den Namensraum-Präfix:** Ersetze `[prefix]` im Code konsistent durch das vereinbarte Präfix des aktuellen Plugins.
2. **Keine WP-Klassennamen:** Verwende keine Core-Klassen (z. B. `.button`, `.card`), um Überschneidungen zu vermeiden.
3. **Branding integrieren:** Stelle sicher, dass der Branding-Footer von Tom Evers am unteren Rand der Hauptseite gerendert wird.
4. **Keine harten Hex-Farben:** Styles müssen ausschließlich über die bereitgestellten CSS-Variablen (`var(--bg)`, `var(--accent)` etc.) definiert werden.

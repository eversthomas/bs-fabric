/* bs-fabric — mock data & field-type catalog */
window.DB = (function () {

  // Field type catalog (drives Fields + Builder + Editor rendering)
  const TYPES = {
    text:     { key: 'text',     name: 'Text',          group: 'Basis',     hint: 'Einzeilige Texteingabe' },
    textarea: { key: 'textarea', name: 'Textbereich',   group: 'Basis',     hint: 'Mehrzeiliger Text' },
    richtext: { key: 'richtext', name: 'Rich-Text',     group: 'Basis',     hint: 'Formatierter Editor' },
    number:   { key: 'number',   name: 'Zahl',          group: 'Basis',     hint: 'Numerischer Wert' },
    media:    { key: 'media',    name: 'Bild / Datei',  group: 'Medien',    hint: 'Aus der Mediathek' },
    relation: { key: 'relation', name: 'Beziehung',     group: 'Struktur',  hint: 'Verweis auf Eintrag/Post' },
    repeater: { key: 'repeater', name: 'Wiederholung',  group: 'Struktur',  hint: 'Wiederholbare Feldgruppe' },
  };

  const fields = [
    { id: 'f1',  label: 'Titel',           key: 'titel',          type: 'text',     required: true,  desc: 'Anzeigename des Eintrags', used: 6 },
    { id: 'f2',  label: 'Untertitel',      key: 'untertitel',     type: 'text',     required: false, desc: '', used: 3 },
    { id: 'f3',  label: 'Beschreibung',    key: 'beschreibung',   type: 'richtext', required: false, desc: 'Fließtext mit Formatierung', used: 5 },
    { id: 'f4',  label: 'Kurztext',        key: 'kurztext',       type: 'textarea', required: false, desc: 'Teaser, max. 240 Zeichen', used: 4 },
    { id: 'f5',  label: 'Preis',           key: 'preis',          type: 'number',   required: false, desc: 'in Euro', used: 2 },
    { id: 'f6',  label: 'Lagerbestand',    key: 'lagerbestand',   type: 'number',   required: false, desc: '', used: 1 },
    { id: 'f7',  label: 'Titelbild',       key: 'titelbild',      type: 'media',    required: false, desc: 'Empfohlen 1600×900', used: 5 },
    { id: 'f8',  label: 'Galerie',         key: 'galerie',        type: 'media',    required: false, desc: 'Mehrfachauswahl', used: 2 },
    { id: 'f9',  label: 'Autor',           key: 'autor',          type: 'relation', required: false, desc: 'Verweis auf Person', used: 4 },
    { id: 'f10', label: 'Verwandte Artikel', key: 'verwandte',    type: 'relation', required: false, desc: '', used: 2 },
    { id: 'f11', label: 'Merkmale',        key: 'merkmale',       type: 'repeater', required: false, desc: 'Label + Wert je Zeile', used: 3 },
    { id: 'f12', label: 'Veröffentlicht',  key: 'veroeffentlicht', type: 'text',    required: false, desc: 'ISO-Datum', used: 1 },
  ];

  const templates = [
    {
      id: 't1', name: 'Produkt', key: 'produkt', icon: 'box',
      desc: 'Strukturierte Produktdaten für den Shop-Katalog.',
      color: 'accent', entries: 48, updated: 'vor 2 Std.',
      fields: [
        { ref: 'f1',  required: true  },
        { ref: 'f4',  required: false },
        { ref: 'f3',  required: false },
        { ref: 'f5',  required: true  },
        { ref: 'f6',  required: false },
        { ref: 'f7',  required: false },
        { ref: 'f8',  required: false },
        { ref: 'f11', required: false },
      ],
    },
    {
      id: 't2', name: 'Redaktioneller Artikel', key: 'artikel', icon: 'doc',
      desc: 'Blaupause für Magazin- und Blogbeiträge.',
      color: 'thread', entries: 132, updated: 'gestern',
      fields: [
        { ref: 'f1',  required: true  },
        { ref: 'f2',  required: false },
        { ref: 'f3',  required: true  },
        { ref: 'f7',  required: false },
        { ref: 'f9',  required: true  },
        { ref: 'f10', required: false },
      ],
    },
    {
      id: 't3', name: 'Team-Mitglied', key: 'person', icon: 'user',
      desc: 'Personenprofile für die Über-uns-Seite.',
      color: 'accent', entries: 19, updated: 'vor 4 Tagen',
      fields: [
        { ref: 'f1',  required: true  },
        { ref: 'f2',  required: false },
        { ref: 'f4',  required: false },
        { ref: 'f7',  required: false },
      ],
    },
    {
      id: 't4', name: 'Veranstaltung', key: 'event', icon: 'cal',
      desc: 'Termine mit Ort, Datum und Beschreibung.',
      color: 'thread', entries: 7, updated: 'vor 1 Woche',
      fields: [
        { ref: 'f1',  required: true  },
        { ref: 'f4',  required: false },
        { ref: 'f3',  required: false },
        { ref: 'f7',  required: false },
        { ref: 'f11', required: false },
      ],
    },
  ];

  // Entries for the "Produkt" template (default data list)
  const entries = [
    { id: 'e1', titel: 'Aurora Wolldecke',        status: 'published', preis: 89,  lager: 24,  autor: 'M. Brandt',  updated: '2026-05-30', by: 'Lena V.' },
    { id: 'e2', titel: 'Tundra Leinenkissen',     status: 'published', preis: 42,  lager: 0,   autor: 'M. Brandt',  updated: '2026-05-29', by: 'Lena V.' },
    { id: 'e3', titel: 'Fjord Strickpullover',    status: 'draft',     preis: 129, lager: 11,  autor: 'K. Sommer',  updated: '2026-05-28', by: 'Tom R.' },
    { id: 'e4', titel: 'Birke Holzhocker',        status: 'published', preis: 75,  lager: 6,   autor: 'K. Sommer',  updated: '2026-05-27', by: 'Lena V.' },
    { id: 'e5', titel: 'Moos Keramikvase',        status: 'review',    preis: 34,  lager: 40,  autor: 'M. Brandt',  updated: '2026-05-26', by: 'Tom R.' },
    { id: 'e6', titel: 'Nebel Baumwolltuch',      status: 'published', preis: 28,  lager: 88,  autor: 'A. Falk',    updated: '2026-05-24', by: 'Lena V.' },
    { id: 'e7', titel: 'Granit Wandregal',        status: 'draft',     preis: 159, lager: 3,   autor: 'A. Falk',    updated: '2026-05-22', by: 'Tom R.' },
    { id: 'e8', titel: 'Heide Duftkerze',         status: 'published', preis: 19,  lager: 120, autor: 'K. Sommer',  updated: '2026-05-21', by: 'Lena V.' },
    { id: 'e9', titel: 'Schiefer Untersetzer-Set', status: 'review',   preis: 24,  lager: 52,  autor: 'M. Brandt',  updated: '2026-05-19', by: 'Tom R.' },
    { id: 'e10', titel: 'Lärche Servierbrett',    status: 'published', preis: 48,  lager: 17,  autor: 'A. Falk',    updated: '2026-05-18', by: 'Lena V.' },
  ];

  const STATUS = {
    published: { label: 'Veröffentlicht', cls: 'ok' },
    draft:     { label: 'Entwurf',        cls: '' },
    review:    { label: 'In Prüfung',     cls: 'warn' },
  };

  const fieldById = (id) => fields.find((f) => f.id === id);

  return { TYPES, fields, templates, entries, STATUS, fieldById };
})();

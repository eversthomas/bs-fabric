/* bs-fabric — app root: routing + tweaks */

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "accent": "blue",
  "side": "dark",
  "density": "regular",
  "listView": "table",
  "formCols": "two"
}/*EDITMODE-END*/;

const ACCENTS = [
  { id: 'blue',   color: 'oklch(0.55 0.142 252)' },
  { id: 'indigo', color: 'oklch(0.52 0.18 274)' },
  { id: 'teal',   color: 'oklch(0.55 0.11 192)' },
  { id: 'plum',   color: 'oklch(0.52 0.16 330)' },
];

const CRUMB = {
  dash:      () => [{ label: 'Übersicht' }],
  templates: () => [{ label: 'Templates' }],
  fields:    () => [{ label: 'Fields' }],
  data:      () => [{ label: 'Einträge' }],
  settings:  () => [{ label: 'Einstellungen' }],
};

function App() {
  const [tw, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [route, setRoute] = React.useState({ name: 'dash', params: {} });
  const [toast, toastNode] = useToasts();
  const contentRef = React.useRef();
  const [pendingField, setPendingField] = React.useState(false);

  // expose tweaks to screens that read globally
  window.__tweaks = tw;

  const go = React.useCallback((name, params = {}) => {
    setRoute({ name, params });
    if (contentRef.current) contentRef.current.scrollTop = 0;
  }, []);

  const densityAttr = tw.density === 'regular' ? 'regular' : tw.density;

  // breadcrumbs
  let crumbs;
  if (route.name === 'builder') {
    const t = window.DB.templates.find((x) => x.id === route.params.templateId);
    crumbs = [{ label: 'Templates', go: () => go('templates') }, { label: t ? t.name : 'Neues Template' }];
  } else if (route.name === 'editor') {
    crumbs = [{ label: 'Einträge', go: () => go('data') }, { label: route.params.entryId ? 'Bearbeiten' : 'Neuer Eintrag' }];
  } else if (route.name === 'entry') {
    const e = window.DB.entries.find((x) => x.id === route.params.entryId);
    crumbs = [{ label: 'Einträge', go: () => go('data') }, { label: e ? e.titel : 'Eintrag' }];
  } else {
    crumbs = (CRUMB[route.name] || CRUMB.dash)();
  }

  const dataTw = { listView: tw.listView, set: setTweak };

  let screen;
  switch (route.name) {
    case 'dash':      screen = <DashScreen go={go} />; break;
    case 'templates': screen = <TemplatesScreen go={go} />; break;
    case 'builder':   screen = <BuilderScreen go={go} params={route.params} toast={toast} openFieldEditor={() => setPendingField(true)} />; break;
    case 'fields':    screen = <FieldsScreen go={go} toast={toast} />; break;
    case 'data':      screen = <DataScreen go={go} t={dataTw} toast={toast} />; break;
    case 'editor':    screen = <EditorScreen go={go} params={route.params} toast={toast} />; break;
    case 'entry':     screen = <EntryScreen go={go} params={route.params} toast={toast} />; break;
    case 'settings':  screen = <SettingsScreen toast={toast} />; break;
    default:          screen = <DashScreen go={go} />;
  }

  return (
    <div className="app" data-accent={tw.accent} data-side={tw.side} data-density={densityAttr}>
      <Sidebar route={route} go={go} />
      <div className="main">
        <Topbar crumbs={crumbs} />
        <div className="content" ref={contentRef}>
          <div className="page">{screen}</div>
        </div>
      </div>

      {pendingField && (
        <FieldEditor field={{}} onClose={() => setPendingField(false)} onSave={() => { setPendingField(false); toast('Feld angelegt'); }} />
      )}

      {toastNode}

      <TweaksPanel>
        <TweakSection label="Erscheinungsbild" />
        <TweakRow label="Akzentfarbe">
          <div className="row" style={{ gap: 8 }}>
            {ACCENTS.map((a) => (
              <button key={a.id} onClick={() => setTweak('accent', a.id)}
                title={a.id}
                style={{ width: 26, height: 26, borderRadius: 8, background: a.color, cursor: 'pointer',
                  border: '2px solid', borderColor: tw.accent === a.id ? 'var(--ink)' : 'transparent',
                  boxShadow: tw.accent === a.id ? '0 0 0 2px var(--surface) inset' : 'none' }} />
            ))}
          </div>
        </TweakRow>
        <TweakRadio label="Sidebar" value={tw.side} options={[{ value: 'dark', label: 'Dunkel' }, { value: 'light', label: 'Hell' }]} onChange={(v) => setTweak('side', v)} />
        <TweakRadio label="Dichte" value={tw.density} options={[{ value: 'compact', label: 'Kompakt' }, { value: 'regular', label: 'Standard' }, { value: 'comfy', label: 'Komfort' }]} onChange={(v) => setTweak('density', v)} />

        <TweakSection label="Layout-Varianten" />
        <TweakRadio label="Daten-Liste" value={tw.listView} options={[{ value: 'table', label: 'Tabelle' }, { value: 'cards', label: 'Karten' }]} onChange={(v) => setTweak('listView', v)} />
        <TweakRadio label="Eingabemaske" value={tw.formCols} options={[{ value: 'one', label: 'Einspaltig' }, { value: 'two', label: 'Zweispaltig' }]} onChange={(v) => setTweak('formCols', v)} />
      </TweaksPanel>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);

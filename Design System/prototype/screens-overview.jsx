/* bs-fabric — Dashboard + Settings */

function Stat({ icon, label, value, sub, tone }) {
  return (
    <div className="card" style={{ padding: '16px 18px' }}>
      <div className="row" style={{ justifyContent: 'space-between', marginBottom: 10 }}>
        <span style={{ width: 34, height: 34, borderRadius: 9, display: 'grid', placeItems: 'center', background: tone === 'thread' ? 'var(--thread-soft)' : 'var(--accent-soft)', color: tone === 'thread' ? 'var(--thread-ink)' : 'var(--accent-ink)' }}><Icon name={icon} size={18} /></span>
        {sub && <Badge variant="ok" dot>{sub}</Badge>}
      </div>
      <div style={{ fontFamily: 'var(--font-brand)', fontSize: 28, fontWeight: 600, letterSpacing: '-0.02em', lineHeight: 1 }}>{value}</div>
      <div className="muted" style={{ fontSize: 13, marginTop: 4 }}>{label}</div>
    </div>
  );
}

function DashScreen({ go }) {
  const totalEntries = window.DB.templates.reduce((a, t) => a + t.entries, 0);
  const recent = window.DB.entries.slice(0, 5);
  return (
    <div className="route-fade">
      <PageHead title="Übersicht" lede="Willkommen zurück, Lena. Hier laufen alle Fäden deines strukturierten Contents zusammen.">
        <Btn variant="ghost" icon="threads" onClick={() => go('fields')}>Neues Feld</Btn>
        <Btn variant="primary" icon="plus" onClick={() => go('builder', { templateId: 'new' })}>Neues Template</Btn>
      </PageHead>

      <div className="stat-grid" style={{ marginBottom: 'var(--gap)' }}>
        <Stat icon="layers" label="Templates" value={window.DB.templates.length} />
        <Stat icon="threads" label="Felder in der Bibliothek" value={window.DB.fields.length} tone="thread" />
        <Stat icon="rows" label="Einträge gesamt" value={totalEntries} sub="+12 diese Woche" />
        <Stat icon="edit" label="Offene Entwürfe" value={9} tone="thread" />
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 'var(--gap)', alignItems: 'start' }}>
        <div className="card">
          <div className="card-head"><h3>Templates</h3><a className="grow" /><Btn variant="subtle" sm icon="chevr" onClick={() => go('templates')}>Alle</Btn></div>
          <div style={{ padding: 8 }}>
            {window.DB.templates.map((t) => (
              <button key={t.id} onClick={() => go('builder', { templateId: t.id })} className="addrow"
                style={{ display: 'flex', alignItems: 'center', gap: 12, width: '100%', padding: '11px 12px', border: 'none', background: 'none', borderRadius: 'var(--r)', textAlign: 'left' }}>
                <span style={{ width: 36, height: 36, borderRadius: 9, display: 'grid', placeItems: 'center', flex: 'none', background: t.color === 'thread' ? 'var(--thread-soft)' : 'var(--accent-soft)', color: t.color === 'thread' ? 'var(--thread-ink)' : 'var(--accent-ink)' }}><Icon name={t.icon} size={18} /></span>
                <div className="col grow" style={{ gap: 1 }}>
                  <span style={{ fontWeight: 600, fontSize: 14 }}>{t.name}</span>
                  <span className="muted" style={{ fontSize: 12 }}>{t.fields.length} Felder · {t.entries} Einträge</span>
                </div>
                <span className="muted" style={{ fontSize: 12 }}>{t.updated}</span>
                <Icon name="chevr" size={15} className="muted" />
              </button>
            ))}
          </div>
        </div>

        <div className="card">
          <div className="card-head"><h3>Zuletzt bearbeitet</h3><a className="grow" /><Btn variant="subtle" sm icon="chevr" onClick={() => go('data')}>Einträge</Btn></div>
          <div style={{ padding: 8 }}>
            {recent.map((e) => (
              <button key={e.id} onClick={() => go('entry', { entryId: e.id, templateId: 't1' })} className="addrow"
                style={{ display: 'flex', alignItems: 'center', gap: 11, width: '100%', padding: '9px 12px', border: 'none', background: 'none', borderRadius: 'var(--r)', textAlign: 'left' }}>
                <span style={{ width: 7, height: 7, borderRadius: 50, flex: 'none', background: e.status === 'published' ? 'var(--ok)' : e.status === 'review' ? 'var(--warn)' : 'var(--border-strong)' }} />
                <span className="col grow" style={{ gap: 1, minWidth: 0 }}>
                  <span style={{ fontWeight: 600, fontSize: 13.5, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{e.titel}</span>
                  <span className="muted" style={{ fontSize: 11.5 }}>{e.by} · {e.updated}</span>
                </span>
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------- Settings ---------- */
function SettingsScreen({ toast }) {
  const [tab, setTab] = React.useState('general');
  const tabs = [['general', 'Allgemein'], ['roles', 'Rollen & Rechte'], ['api', 'API & Export'], ['advanced', 'Erweitert']];
  return (
    <div className="route-fade">
      <PageHead title="Einstellungen" lede="Verhalten von bs·fabric für diese WordPress-Installation konfigurieren." />
      <div style={{ display: 'grid', gridTemplateColumns: '200px 1fr', gap: 'var(--gap)', alignItems: 'start' }}>
        <div className="card" style={{ padding: 8 }}>
          {tabs.map(([v, l]) => (
            <button key={v} onClick={() => setTab(v)} className="nav-item"
              style={{ color: tab === v ? 'var(--accent-ink)' : 'var(--ink-soft)', background: tab === v ? 'var(--accent-soft)' : 'transparent', fontWeight: tab === v ? 600 : 500 }}>{l}</button>
          ))}
        </div>

        <div className="col" style={{ gap: 'var(--gap)' }}>
          {tab === 'general' && <>
            <div className="card">
              <div className="card-head"><h3>Allgemein</h3></div>
              <div className="card-body">
                <Field label="Menü-Bezeichnung" hint="So heißt das Plugin im WordPress-Adminmenü.">
                  <input className="input" defaultValue="bs·fabric" style={{ maxWidth: 320 }} />
                </Field>
                <Field label="Standard-Status neuer Einträge">
                  <div className="select-wrap" style={{ maxWidth: 320 }}><select className="selectbox"><option>Entwurf</option><option>In Prüfung</option></select><Icon name="chevd" size={15} /></div>
                </Field>
                <hr className="divider" />
                {[['Autosave aktivieren', 'Eingaben alle 30 Sek. sichern', true], ['Felder-Keys sperren', 'Keys nach Anlage nicht mehr änderbar', true], ['Papierkorb verwenden', 'Gelöschte Einträge 30 Tage aufbewahren', false]].map(([t, s, on], i) => (
                  <div key={i} className="row" style={{ justifyContent: 'space-between', padding: '11px 0', borderTop: i ? '1px solid var(--border)' : 'none' }}>
                    <div className="col" style={{ gap: 1 }}><span style={{ fontWeight: 600, fontSize: 13.5 }}>{t}</span><span className="muted" style={{ fontSize: 12.5 }}>{s}</span></div>
                    <Switch on={on} onClick={() => {}} />
                  </div>
                ))}
              </div>
            </div>
          </>}

          {tab === 'roles' && (
            <div className="card">
              <div className="card-head"><h3>Wer darf was?</h3></div>
              <table className="tbl">
                <thead><tr><th>Rolle</th><th>Felder & Templates</th><th>Einträge anlegen</th><th>Veröffentlichen</th></tr></thead>
                <tbody>
                  {[['Administrator', true, true, true], ['Chefredaktion', true, true, true], ['Redakteur', false, true, true], ['Autor', false, true, false]].map((r, i) => (
                    <tr key={i}>
                      <td className="cell-strong">{r[0]}</td>
                      {[1, 2, 3].map((k) => <td key={k}><Check on={r[k]} onClick={() => {}} /></td>)}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {tab === 'api' && (
            <div className="card">
              <div className="card-head"><h3>REST-API & Export</h3></div>
              <div className="card-body">
                <Field label="API-Basis-Endpoint">
                  <input className="input mono" readOnly defaultValue="/wp-json/bs-fabric/v1/" />
                </Field>
                <Field label="API-Schlüssel" hint="Für externen Lesezugriff auf strukturierte Daten.">
                  <div className="row" style={{ gap: 8 }}>
                    <input className="input mono" readOnly defaultValue="bsf_live_9c2a··········e41" style={{ flex: 1 }} />
                    <Btn variant="ghost" icon="copy" onClick={() => toast('Schlüssel kopiert')}>Kopieren</Btn>
                  </div>
                </Field>
                <hr className="divider" />
                <div className="row" style={{ gap: 10 }}>
                  <Btn variant="ghost" icon="external">JSON exportieren</Btn>
                  <Btn variant="ghost" icon="external">CSV exportieren</Btn>
                </div>
              </div>
            </div>
          )}

          {tab === 'advanced' && (
            <div className="card">
              <div className="card-head"><h3>Erweitert</h3></div>
              <div className="card-body">
                <div className="row" style={{ justifyContent: 'space-between', padding: '11px 0' }}>
                  <div className="col" style={{ gap: 1 }}><span style={{ fontWeight: 600, fontSize: 13.5 }}>Daten beim Deinstallieren löschen</span><span className="muted" style={{ fontSize: 12.5 }}>Entfernt alle Templates, Felder & Einträge unwiderruflich</span></div>
                  <Switch on={false} onClick={() => {}} />
                </div>
                <hr className="divider" />
                <Btn variant="danger" icon="trash">Alle Einträge zurücksetzen</Btn>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { DashScreen, SettingsScreen, Stat });

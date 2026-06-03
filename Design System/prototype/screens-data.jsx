/* bs-fabric — Data list + Editor + Entry detail */

/* ---------- Data list ---------- */
function DataScreen({ go, t, toast }) {
  const tmpl = window.DB.templates;
  const [activeT, setActiveT] = React.useState('t1');
  const [sel, setSel] = React.useState([]);
  const [q, setQ] = React.useState('');
  const [sort, setSort] = React.useState({ key: 'updated', dir: -1 });
  const [status, setStatus] = React.useState('all');
  const view = t.listView; // 'table' | 'cards'

  const cur = tmpl.find((x) => x.id === activeT);
  let rows = window.DB.entries
    .filter((e) => status === 'all' || e.status === status)
    .filter((e) => e.titel.toLowerCase().includes(q.toLowerCase()));
  rows = rows.slice().sort((a, b) => {
    const k = sort.key; let av = a[k], bv = b[k];
    if (typeof av === 'string') return av.localeCompare(bv) * sort.dir;
    return (av - bv) * sort.dir;
  });

  const toggleSort = (k) => setSort((s) => ({ key: k, dir: s.key === k ? -s.dir : 1 }));
  const sar = (k) => sort.key === k ? <span className="sar">{sort.dir > 0 ? '↑' : '↓'}</span> : <span className="sar" />;
  const allSel = sel.length === rows.length && rows.length > 0;

  return (
    <div className="route-fade">
      <PageHead title="Einträge" lede="Alle nach deinen Blaupausen gepflegten Datensätze — sortier-, filter- und bearbeitbar.">
        <Btn variant="primary" icon="plus" onClick={() => go('editor', { templateId: activeT })}>Neuer Eintrag</Btn>
      </PageHead>

      {/* template tabs */}
      <div className="row" style={{ gap: 8, marginBottom: 16, flexWrap: 'wrap' }}>
        {tmpl.map((x) => {
          const on = x.id === activeT;
          return (
            <button key={x.id} onClick={() => { setActiveT(x.id); setSel([]); }}
              className="row" style={{
                gap: 8, padding: '7px 13px 7px 9px', borderRadius: 20, fontSize: 13, fontWeight: 600,
                border: `1px solid ${on ? 'transparent' : 'var(--border)'}`,
                background: on ? 'var(--ink)' : 'var(--surface)', color: on ? '#fff' : 'var(--ink-soft)',
              }}>
              <span style={{ width: 24, height: 24, borderRadius: 6, display: 'grid', placeItems: 'center', background: on ? 'oklch(1 0 0 / 0.16)' : 'var(--surface-sunken)', color: on ? '#fff' : 'var(--accent)' }}><Icon name={x.icon} size={14} /></span>
              {x.name}
              <span style={{ fontFamily: 'var(--font-mono)', fontSize: 11, opacity: 0.7 }}>{x.entries}</span>
            </button>
          );
        })}
      </div>

      <div className="card">
        <div className="card-head" style={{ flexWrap: 'wrap', gap: 10 }}>
          <div className="search" style={{ width: 220, background: 'var(--surface-2)' }}>
            <Icon name="search" size={15} /><input placeholder={`In ${cur.name} suchen…`} value={q} onChange={(e) => setQ(e.target.value)} />
          </div>
          <div className="select-wrap">
            <select className="selectbox" style={{ height: 32, padding: '0 30px 0 12px', fontSize: 13 }} value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="all">Alle Status</option>
              <option value="published">Veröffentlicht</option>
              <option value="draft">Entwurf</option>
              <option value="review">In Prüfung</option>
            </select>
            <Icon name="chevd" size={14} />
          </div>
          <div className="ca" style={{ marginLeft: 'auto' }}>
            <div className="seg">
              <button className={view === 'table' ? 'on' : ''} onClick={() => t.set('listView', 'table')}><Icon name="list" size={15} />Tabelle</button>
              <button className={view === 'cards' ? 'on' : ''} onClick={() => t.set('listView', 'cards')}><Icon name="grid" size={15} />Karten</button>
            </div>
          </div>
        </div>

        {/* bulk bar */}
        {sel.length > 0 && (
          <div className="row" style={{ gap: 12, padding: '10px var(--pad)', background: 'var(--accent-soft)', borderBottom: '1px solid var(--accent-soft-bd)' }}>
            <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--accent-ink)' }}>{sel.length} ausgewählt</span>
            <Btn variant="ghost" sm icon="check" onClick={() => { toast(sel.length + ' veröffentlicht'); setSel([]); }}>Veröffentlichen</Btn>
            <Btn variant="ghost" sm icon="copy" onClick={() => toast('Dupliziert')}>Duplizieren</Btn>
            <Btn variant="ghost" sm icon="trash" onClick={() => { toast(sel.length + ' gelöscht'); setSel([]); }} style={{ color: 'var(--danger)' }}>Löschen</Btn>
            <Btn variant="subtle" sm onClick={() => setSel([])} style={{ marginLeft: 'auto' }}>Aufheben</Btn>
          </div>
        )}

        {view === 'table' ? (
          <table className="tbl">
            <thead>
              <tr>
                <th className="checkbox-cell"><Check on={allSel} onClick={() => setSel(allSel ? [] : rows.map((r) => r.id))} /></th>
                <th className="sortable" onClick={() => toggleSort('titel')}>Titel {sar('titel')}</th>
                <th>Status</th>
                <th className="sortable" onClick={() => toggleSort('preis')}>Preis {sar('preis')}</th>
                <th className="sortable" onClick={() => toggleSort('lager')}>Bestand {sar('lager')}</th>
                <th>Autor</th>
                <th className="sortable" onClick={() => toggleSort('updated')}>Geändert {sar('updated')}</th>
                <th style={{ width: 40 }}></th>
              </tr>
            </thead>
            <tbody>
              {rows.map((e) => (
                <tr key={e.id} className="row-click" onClick={() => go('entry', { entryId: e.id, templateId: activeT })}>
                  <td onClick={(ev) => ev.stopPropagation()}><Check on={sel.includes(e.id)} onClick={() => setSel((s) => s.includes(e.id) ? s.filter((x) => x !== e.id) : [...s, e.id])} /></td>
                  <td className="cell-strong">{e.titel}</td>
                  <td><StatusBadge status={e.status} /></td>
                  <td className="num">{e.preis} €</td>
                  <td className="num">{e.lager === 0 ? <span style={{ color: 'var(--danger)' }}>0</span> : e.lager}</td>
                  <td className="cell-mute">{e.autor}</td>
                  <td className="cell-mute">{e.updated}</td>
                  <td onClick={(ev) => ev.stopPropagation()}><Btn variant="subtle" icon="edit" sm onClick={() => go('editor', { entryId: e.id, templateId: activeT })} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <div className="card-body" style={{ background: 'var(--surface-2)', display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(232px, 1fr))', gap: 'var(--gap)' }}>
            {rows.map((e) => (
              <button key={e.id} className="card" onClick={() => go('entry', { entryId: e.id, templateId: activeT })}
                style={{ textAlign: 'left', padding: 0, cursor: 'pointer', overflow: 'hidden' }}>
                <div className="imgph" style={{ height: 116, borderRadius: 0, border: 'none', borderBottom: '1px solid var(--border)' }}><span>{e.titel}</span></div>
                <div style={{ padding: '12px 14px' }}>
                  <div className="row" style={{ justifyContent: 'space-between', marginBottom: 8 }}>
                    <StatusBadge status={e.status} />
                    <span className="num" style={{ fontWeight: 600, fontSize: 13.5 }}>{e.preis} €</span>
                  </div>
                  <div className="cell-strong" style={{ fontSize: 14.5, marginBottom: 6 }}>{e.titel}</div>
                  <div className="row muted" style={{ fontSize: 12, justifyContent: 'space-between' }}>
                    <span>{e.autor}</span><span>{e.lager} auf Lager</span>
                  </div>
                </div>
              </button>
            ))}
          </div>
        )}

        <div className="row" style={{ padding: '12px var(--pad)', borderTop: '1px solid var(--border)', justifyContent: 'space-between', fontSize: 12.5, color: 'var(--muted)' }}>
          <span>{rows.length} von {cur.entries} Einträgen</span>
          <div className="row" style={{ gap: 6 }}>
            <Btn variant="ghost" sm icon="chevl" disabled />
            <span className="row" style={{ gap: 4 }}>
              <span style={{ width: 28, height: 28, borderRadius: 6, display: 'grid', placeItems: 'center', background: 'var(--accent)', color: '#fff', fontWeight: 600, fontSize: 12.5 }}>1</span>
              <span style={{ width: 28, height: 28, borderRadius: 6, display: 'grid', placeItems: 'center', fontWeight: 600, fontSize: 12.5 }}>2</span>
              <span style={{ width: 28, height: 28, borderRadius: 6, display: 'grid', placeItems: 'center', fontWeight: 600, fontSize: 12.5 }}>3</span>
            </span>
            <Btn variant="ghost" sm icon="chevr" />
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------- Editor (redakteur input mask) ---------- */
const SAMPLE = {
  titel: 'Aurora Wolldecke', untertitel: 'Handgewebt in Tirol',
  kurztext: 'Eine federleichte Decke aus reiner Merinowolle — warm, atmungsaktiv und in gedeckten Naturtönen.',
  beschreibung: '<p>Die <b>Aurora</b> entsteht in traditioneller Handarbeit. Jedes Stück ist ein Unikat mit feiner Webstruktur.</p><ul><li>100 % Merinowolle</li><li>Bei 30°C waschbar</li></ul>',
  preis: 89, lagerbestand: 24,
};

function EditorScreen({ go, params, toast }) {
  const tmpl = window.DB.templates.find((x) => x.id === params.templateId) || window.DB.templates[0];
  const isEdit = !!params.entryId;
  const t = window.__tweaks || { formCols: 'two' };
  const entry = isEdit ? window.DB.entries.find((e) => e.id === params.entryId) : null;
  const [status, setStatus] = React.useState(entry ? entry.status : 'draft');
  const two = t.formCols === 'two';

  // split fields: full-width types stay single column
  const fields = tmpl.fields.map((r) => ({ row: r, def: window.DB.fieldById(r.ref) }));

  return (
    <div className="route-fade">
      <PageHead
        title={isEdit ? (entry ? entry.titel : 'Eintrag') : `Neuer ${tmpl.name}-Eintrag`}
        lede={`Strukturierte Daten nach der Blaupause „${tmpl.name}" pflegen.`}>
        <Btn variant="ghost" onClick={() => go('data', { templateId: tmpl.id })}>Verwerfen</Btn>
        <Btn variant="primary" icon="save" onClick={() => { toast('Eintrag gespeichert'); go('data', { templateId: tmpl.id }); }}>Speichern</Btn>
      </PageHead>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 290px', gap: 'var(--gap)', alignItems: 'start' }}>
        <div className="card">
          <div className="card-head">
            <span style={{ width: 30, height: 30, borderRadius: 8, display: 'grid', placeItems: 'center', background: 'var(--accent-soft)', color: 'var(--accent-ink)' }}><Icon name={tmpl.icon} size={17} /></span>
            <h3>{tmpl.name}</h3>
            <Badge variant="mono">{tmpl.key}</Badge>
          </div>
          <div className="card-body">
            <div style={{ display: 'grid', gridTemplateColumns: two ? '1fr 1fr' : '1fr', gap: '6px 22px' }}>
              {fields.map(({ row, def }, i) => {
                const wide = !two || ['richtext', 'textarea', 'media', 'repeater', 'relation'].includes(def.type);
                return (
                  <div key={i} style={{ gridColumn: wide ? '1 / -1' : 'auto' }}>
                    <Field label={def.label} req={row.required} hint={def.desc}>
                      <FieldInput field={def} value={SAMPLE[def.key]} />
                    </Field>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        {/* publish sidebar */}
        <div className="col" style={{ gap: 'var(--gap)' }}>
          <div className="card">
            <div className="card-head"><h3>Veröffentlichung</h3></div>
            <div className="card-body">
              <Field label="Status">
                <div className="col" style={{ gap: 7 }}>
                  {[['draft', 'Entwurf'], ['review', 'In Prüfung'], ['published', 'Veröffentlicht']].map(([v, l]) => (
                    <button key={v} onClick={() => setStatus(v)} className="row"
                      style={{ gap: 10, padding: '9px 12px', borderRadius: 'var(--r)', border: `1.5px solid ${status === v ? 'var(--accent)' : 'var(--border)'}`, background: status === v ? 'var(--accent-soft)' : 'var(--surface)', fontWeight: 600, fontSize: 13 }}>
                      <span style={{ width: 16, height: 16, borderRadius: 50, border: `2px solid ${status === v ? 'var(--accent)' : 'var(--border-strong)'}`, display: 'grid', placeItems: 'center' }}>{status === v && <span style={{ width: 8, height: 8, borderRadius: 50, background: 'var(--accent)' }} />}</span>
                      {l}
                    </button>
                  ))}
                </div>
              </Field>
              <hr className="divider" />
              <div className="col" style={{ gap: 9, fontSize: 12.5 }}>
                <div className="row" style={{ justifyContent: 'space-between', whiteSpace: 'nowrap', gap: 12 }}><span className="muted">Erstellt</span><span>{entry ? entry.updated : 'jetzt'}</span></div>
                <div className="row" style={{ justifyContent: 'space-between', whiteSpace: 'nowrap', gap: 12 }}><span className="muted">Autor</span><span>Lena Vogt</span></div>
                <div className="row" style={{ justifyContent: 'space-between', whiteSpace: 'nowrap', gap: 12 }}><span className="muted">Felder</span><span>{tmpl.fields.length}</span></div>
              </div>
            </div>
          </div>
          <div className="card" style={{ background: 'var(--surface-2)' }}>
            <div className="card-body" style={{ padding: 16 }}>
              <div className="row" style={{ gap: 9, marginBottom: 6 }}><Icon name="info" size={16} className="muted" /><span style={{ fontWeight: 600, fontSize: 13 }}>Blaupause</span></div>
              <p className="muted" style={{ margin: 0, fontSize: 12.5, lineHeight: 1.5 }}>Diese Maske wird automatisch aus dem Template „{tmpl.name}" erzeugt. Struktur ändern? <a onClick={() => go('builder', { templateId: tmpl.id })} style={{ color: 'var(--accent-ink)', fontWeight: 600, cursor: 'pointer' }}>Im Builder öffnen</a></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ---------- Entry detail ---------- */
function EntryScreen({ go, params, toast }) {
  const tmpl = window.DB.templates.find((x) => x.id === params.templateId) || window.DB.templates[0];
  const e = window.DB.entries.find((x) => x.id === params.entryId) || window.DB.entries[0];
  const fields = tmpl.fields.map((r) => window.DB.fieldById(r.ref));

  const Val = ({ f }) => {
    if (f.type === 'media') return <div className="imgph" style={{ width: 220, height: 124, flexDirection: 'column', gap: 4 }}><Icon name="t_media" size={20} /><span>Titelbild</span></div>;
    if (f.type === 'richtext') return <div style={{ fontSize: 14, lineHeight: 1.6, color: 'var(--ink-soft)' }} dangerouslySetInnerHTML={{ __html: SAMPLE.beschreibung }} />;
    if (f.type === 'relation') return <div className="row" style={{ gap: 7 }}><Badge variant="thread"><Icon name="link" size={12} />{e.autor}</Badge></div>;
    if (f.type === 'repeater') return <div className="col" style={{ gap: 5 }}><div className="row" style={{ gap: 10 }}><span className="key">Material</span><span>Merinowolle</span></div><div className="row" style={{ gap: 10 }}><span className="key">Maße</span><span>130 × 180 cm</span></div></div>;
    if (f.key === 'preis') return <span className="num" style={{ fontSize: 15, fontWeight: 600 }}>{e.preis} €</span>;
    if (f.key === 'lagerbestand') return <span className="num">{e.lager} Stück</span>;
    return <span style={{ color: 'var(--ink-soft)' }}>{SAMPLE[f.key] || '—'}</span>;
  };

  return (
    <div className="route-fade">
      <div className="row" style={{ gap: 12, marginBottom: 18 }}>
        <Btn variant="ghost" icon="back" onClick={() => go('data', { templateId: tmpl.id })}>Zurück</Btn>
        <div className="grow" />
        <Btn variant="ghost" icon="external" onClick={() => toast('Vorschau im Frontend')}>Ansehen</Btn>
        <Btn variant="primary" icon="edit" onClick={() => go('editor', { entryId: e.id, templateId: tmpl.id })}>Bearbeiten</Btn>
      </div>

      <div className="row" style={{ gap: 14, marginBottom: 22, alignItems: 'flex-start' }}>
        <span style={{ width: 46, height: 46, borderRadius: 12, display: 'grid', placeItems: 'center', background: 'var(--accent-soft)', color: 'var(--accent-ink)', flex: 'none' }}><Icon name={tmpl.icon} size={24} /></span>
        <div className="col grow" style={{ gap: 6 }}>
          <h1 style={{ fontFamily: 'var(--font-brand)', fontSize: 26, fontWeight: 600, margin: 0, letterSpacing: '-0.02em' }}>{e.titel}</h1>
          <div className="row" style={{ gap: 10 }}>
            <StatusBadge status={e.status} />
            <Badge variant="mono">{tmpl.key} · {e.id}</Badge>
            <span className="muted" style={{ fontSize: 12.5 }}>Zuletzt {e.updated} von {e.by}</span>
          </div>
        </div>
      </div>

      <div className="card">
        <div className="card-head"><h3>Inhaltsdaten</h3><Badge variant="">{fields.length} Felder</Badge></div>
        <div className="card-body">
          <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr', gap: 0 }}>
            {fields.map((f, i) => (
              <React.Fragment key={i}>
                <div style={{ padding: '14px 0', borderTop: i ? '1px solid var(--border)' : 'none' }}>
                  <div className="flabel" style={{ margin: 0, color: 'var(--ink-soft)' }}>{f.label}</div>
                  <span className="key" style={{ marginTop: 4, display: 'inline-block', fontSize: 11 }}>{f.key}</span>
                </div>
                <div style={{ padding: '14px 0 14px 24px', borderTop: i ? '1px solid var(--border)' : 'none' }}><Val f={f} /></div>
              </React.Fragment>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { DataScreen, EditorScreen, EntryScreen, SAMPLE });

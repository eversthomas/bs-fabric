/* bs-fabric — Templates list + Template Builder */

function TemplateCard({ t, go }) {
  return (
    <button className="card" onClick={() => go('builder', { templateId: t.id })}
      style={{ textAlign: 'left', padding: 0, cursor: 'pointer', transition: 'transform 0.12s, box-shadow 0.12s', display: 'block' }}
      onMouseEnter={(e) => { e.currentTarget.style.boxShadow = 'var(--shadow)'; e.currentTarget.style.transform = 'translateY(-2px)'; }}
      onMouseLeave={(e) => { e.currentTarget.style.boxShadow = 'var(--shadow-sm)'; e.currentTarget.style.transform = 'none'; }}>
      <div style={{ padding: '18px 18px 14px', display: 'flex', gap: 13, alignItems: 'flex-start' }}>
        <span style={{
          width: 42, height: 42, borderRadius: 11, flex: 'none', display: 'grid', placeItems: 'center',
          background: t.color === 'thread' ? 'var(--thread-soft)' : 'var(--accent-soft)',
          color: t.color === 'thread' ? 'var(--thread-ink)' : 'var(--accent-ink)',
        }}>
          <Icon name={t.icon} size={22} />
        </span>
        <div className="col grow" style={{ gap: 3, minWidth: 0 }}>
          <span style={{ fontFamily: 'var(--font-brand)', fontWeight: 600, fontSize: 16, letterSpacing: '-0.01em' }}>{t.name}</span>
          <span className="key" style={{ alignSelf: 'flex-start' }}>{t.key}</span>
        </div>
        <Icon name="chevr" size={16} className="muted" />
      </div>
      <p style={{ margin: 0, padding: '0 18px 14px', color: 'var(--muted)', fontSize: 13, lineHeight: 1.5 }}>{t.desc}</p>
      <div style={{ display: 'flex', gap: 18, padding: '12px 18px', borderTop: '1px solid var(--border)', background: 'var(--surface-2)', fontSize: 12.5 }}>
        <span className="row" style={{ gap: 6 }}><Icon name="threads" size={14} className="muted" /><b style={{ fontWeight: 600 }}>{t.fields.length}</b> <span className="muted">Felder</span></span>
        <span className="row" style={{ gap: 6 }}><Icon name="rows" size={14} className="muted" /><b style={{ fontWeight: 600 }}>{t.entries}</b> <span className="muted">Einträge</span></span>
        <span className="muted" style={{ marginLeft: 'auto' }}>{t.updated}</span>
      </div>
    </button>
  );
}

function TemplatesScreen({ go }) {
  return (
    <div className="route-fade">
      <PageHead title="Templates" lede="Webmuster für strukturierte Inhalte. Jedes Template ist eine Blaupause, nach der Redakteure Einträge anlegen und pflegen.">
        <Btn variant="primary" icon="plus" onClick={() => go('builder', { templateId: 'new' })}>Neues Template</Btn>
      </PageHead>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(290px, 1fr))', gap: 'var(--gap)' }}>
        {window.DB.templates.map((t) => <TemplateCard key={t.id} t={t} go={go} />)}
        <button className="card" onClick={() => go('builder', { templateId: 'new' })}
          style={{ border: '1.5px dashed var(--border-strong)', background: 'var(--surface-2)', boxShadow: 'none', cursor: 'pointer', display: 'grid', placeItems: 'center', minHeight: 150, color: 'var(--muted)' }}>
          <div className="col" style={{ alignItems: 'center', gap: 8 }}>
            <span style={{ width: 38, height: 38, borderRadius: 10, display: 'grid', placeItems: 'center', background: 'var(--surface)', border: '1px solid var(--border)' }}><Icon name="plus" size={20} /></span>
            <span style={{ fontWeight: 600, fontSize: 13.5 }}>Template anlegen</span>
          </div>
        </button>
      </div>
    </div>
  );
}

/* ---------- Builder ---------- */

function AddFieldMenu({ onPick, onClose, exclude }) {
  const [q, setQ] = React.useState('');
  const avail = window.DB.fields.filter((f) =>
    !exclude.includes(f.id) &&
    (f.label.toLowerCase().includes(q.toLowerCase()) || f.key.includes(q.toLowerCase())));
  React.useEffect(() => {
    const h = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [onClose]);
  return (
    <>
      <div style={{ position: 'fixed', inset: 0, zIndex: 30 }} onClick={onClose} />
      <div style={{
        position: 'absolute', top: 'calc(100% + 8px)', right: 0, width: 320, zIndex: 31,
        background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 'var(--r-lg)',
        boxShadow: 'var(--shadow-lg)', overflow: 'hidden',
      }}>
        <div style={{ padding: 10, borderBottom: '1px solid var(--border)' }}>
          <div className="search" style={{ width: '100%', background: 'var(--surface-2)' }}>
            <Icon name="search" size={15} />
            <input placeholder="Feld aus Bibliothek…" value={q} onChange={(e) => setQ(e.target.value)} autoFocus />
          </div>
        </div>
        <div style={{ maxHeight: 280, overflowY: 'auto', padding: 6 }}>
          {avail.length === 0 && <div className="muted" style={{ padding: 16, textAlign: 'center', fontSize: 13 }}>Alle Felder bereits im Template.</div>}
          {avail.map((f) => (
            <button key={f.id} onClick={() => onPick(f.id)} className="addrow"
              style={{ display: 'flex', alignItems: 'center', gap: 11, width: '100%', padding: '8px 10px', border: 'none', background: 'none', borderRadius: 'var(--r)', textAlign: 'left' }}>
              <span style={{ width: 28, height: 28, borderRadius: 7, flex: 'none', display: 'grid', placeItems: 'center', background: 'var(--surface-sunken)', color: 'var(--accent)' }}>
                <Icon name={TYPE_ICON[f.type]} size={15} />
              </span>
              <span className="col grow" style={{ gap: 0, minWidth: 0 }}>
                <span style={{ fontWeight: 600, fontSize: 13 }}>{f.label}</span>
                <span className="key" style={{ alignSelf: 'flex-start', border: 'none', background: 'none', padding: 0, color: 'var(--muted)' }}>{f.key} · {window.DB.TYPES[f.type].name}</span>
              </span>
              <Icon name="plus" size={15} className="muted" />
            </button>
          ))}
        </div>
        <div style={{ borderTop: '1px solid var(--border)', padding: 6 }}>
          <button className="addrow" onClick={() => onPick('__new')}
            style={{ display: 'flex', alignItems: 'center', gap: 11, width: '100%', padding: '9px 10px', border: 'none', background: 'none', borderRadius: 'var(--r)', textAlign: 'left', color: 'var(--accent-ink)', fontWeight: 600, fontSize: 13 }}>
            <span style={{ width: 28, height: 28, borderRadius: 7, flex: 'none', display: 'grid', placeItems: 'center', background: 'var(--accent-soft)', color: 'var(--accent)' }}><Icon name="bolt" size={15} /></span>
            Neues Feld erstellen…
          </button>
        </div>
      </div>
      <style>{`.addrow:hover { background: var(--surface-2) !important; }`}</style>
    </>
  );
}

function BuilderRow({ row, field, idx, total, onMove, onToggleReq, onRemove }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 14px', background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 'var(--r)', boxShadow: 'var(--shadow-sm)' }}>
      <span style={{ color: 'var(--faint)', cursor: 'grab', display: 'flex' }}><Icon name="drag" size={18} /></span>
      <span style={{ width: 34, height: 34, borderRadius: 8, flex: 'none', display: 'grid', placeItems: 'center', background: 'var(--surface-sunken)', color: 'var(--accent)' }}>
        <Icon name={TYPE_ICON[field.type]} size={17} />
      </span>
      <div className="col grow" style={{ gap: 2, minWidth: 0 }}>
        <span style={{ fontWeight: 600, fontSize: 14 }}>{field.label}</span>
        <div className="row" style={{ gap: 8 }}>
          <span className="key" style={{ fontSize: 11 }}>{field.key}</span>
          <span className="muted" style={{ fontSize: 12 }}>{window.DB.TYPES[field.type].name}</span>
        </div>
      </div>
      <button onClick={onToggleReq} className="row" title="Pflichtfeld umschalten"
        style={{ gap: 7, border: '1px solid var(--border)', background: row.required ? 'var(--accent-soft)' : 'var(--surface)', color: row.required ? 'var(--accent-ink)' : 'var(--muted)', borderRadius: 20, padding: '4px 10px', fontSize: 12, fontWeight: 600 }}>
        <span style={{ width: 7, height: 7, borderRadius: 50, background: row.required ? 'var(--accent)' : 'var(--border-strong)' }} />
        Pflicht
      </button>
      <div className="row" style={{ gap: 2 }}>
        <Btn variant="subtle" icon="chevd" sm onClick={() => onMove(1)} disabled={idx === total - 1} style={{ width: 28, height: 28 }} />
        <Btn variant="subtle" icon="chevd" sm onClick={() => onMove(-1)} disabled={idx === 0} style={{ width: 28, height: 28, transform: 'rotate(180deg)' }} />
        <Btn variant="subtle" icon="trash" sm onClick={onRemove} style={{ width: 28, height: 28, color: 'var(--danger)' }} />
      </div>
    </div>
  );
}

function BuilderScreen({ go, params, toast, openFieldEditor }) {
  const existing = window.DB.templates.find((t) => t.id === params.templateId);
  const [name, setName] = React.useState(existing ? existing.name : '');
  const [key, setKey] = React.useState(existing ? existing.key : '');
  const [keyTouched, setKeyTouched] = React.useState(!!existing);
  const [icon, setIcon] = React.useState(existing ? existing.icon : 'box');
  const [desc, setDesc] = React.useState(existing ? existing.desc : '');
  const [rows, setRows] = React.useState(existing ? existing.fields.map((f) => ({ ...f })) : []);
  const [menu, setMenu] = React.useState(false);
  const isNew = !existing;

  const move = (i, dir) => {
    const j = i + dir;
    if (j < 0 || j >= rows.length) return;
    const next = rows.slice(); [next[i], next[j]] = [next[j], next[i]]; setRows(next);
  };
  const pick = (fid) => {
    setMenu(false);
    if (fid === '__new') { openFieldEditor(); return; }
    setRows((r) => [...r, { ref: fid, required: !!window.DB.fieldById(fid)?.required }]);
  };

  const ICON_OPTS = ['box', 'doc', 'user', 'cal', 'layers', 'bolt'];

  return (
    <div className="route-fade">
      <PageHead
        title={
          <input value={name} onChange={(e) => { setName(e.target.value); if (!keyTouched) setKey(slugify(e.target.value)); }}
            placeholder="Template benennen…"
            style={{ font: 'inherit', fontFamily: 'var(--font-brand)', fontSize: 25, fontWeight: 600, letterSpacing: '-0.02em', border: 'none', outline: 'none', background: 'none', color: 'var(--ink)', width: '100%', padding: 0 }} />
        }
        lede={isNew ? 'Stelle die Blaupause zusammen — Felder per Klick hinzufügen, Reihenfolge & Pflichtangaben festlegen.' : 'Felder dieser Blaupause anpassen, ordnen und konfigurieren.'}>
        <Btn variant="ghost" icon="eye" onClick={() => go('editor', { templateId: existing ? existing.id : 't1' })}>Vorschau</Btn>
        <Btn variant="primary" icon="save" onClick={() => { toast(isNew ? 'Template angelegt' : 'Template gespeichert'); go('templates'); }}>Speichern</Btn>
      </PageHead>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 300px', gap: 'var(--gap)', alignItems: 'start' }}>
        {/* canvas */}
        <div className="card">
          <div className="card-head">
            <h3>Felder im Template</h3>
            <Badge variant="accent">{rows.length}</Badge>
            <div className="ca" style={{ position: 'relative' }}>
              <Btn variant="primary" icon="plus" sm onClick={() => setMenu(!menu)}>Feld hinzufügen</Btn>
              {menu && <AddFieldMenu onPick={pick} onClose={() => setMenu(false)} exclude={rows.map((r) => r.ref)} />}
            </div>
          </div>
          <div className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 10, background: 'var(--surface-2)' }}>
            {rows.length === 0 ? (
              <div style={{ border: '1.5px dashed var(--border-strong)', borderRadius: 'var(--r)', padding: '40px 20px', textAlign: 'center' }}>
                <div className="col" style={{ alignItems: 'center', gap: 10 }}>
                  <span style={{ width: 44, height: 44, borderRadius: 11, display: 'grid', placeItems: 'center', background: 'var(--surface)', border: '1px solid var(--border)', color: 'var(--faint)' }}><Icon name="threads" size={24} /></span>
                  <div style={{ fontWeight: 600, fontFamily: 'var(--font-brand)' }}>Noch keine Felder</div>
                  <div className="muted" style={{ fontSize: 13, maxWidth: '34ch' }}>Füge Felder aus deiner Bibliothek hinzu, um die Blaupause aufzubauen.</div>
                </div>
              </div>
            ) : rows.map((r, i) => (
              <BuilderRow key={r.ref + i} row={r} field={window.DB.fieldById(r.ref)} idx={i} total={rows.length}
                onMove={(d) => move(i, d)}
                onToggleReq={() => setRows((rs) => rs.map((x, k) => (k === i ? { ...x, required: !x.required } : x)))}
                onRemove={() => setRows((rs) => rs.filter((_, k) => k !== i))} />
            ))}
            {rows.length > 0 && (
              <div style={{ position: 'relative', alignSelf: 'flex-start' }}>
                <Btn variant="ghost" icon="plus" sm onClick={() => setMenu(!menu)}>Weiteres Feld</Btn>
                {menu && <AddFieldMenu onPick={pick} onClose={() => setMenu(false)} exclude={rows.map((r) => r.ref)} />}
              </div>
            )}
          </div>
        </div>

        {/* settings */}
        <div className="card">
          <div className="card-head"><h3>Template-Einstellungen</h3></div>
          <div className="card-body">
            <Field label="Feld-Key" hint="Slug für API & Datenbank.">
              <input className="input mono" value={key} onChange={(e) => { setKeyTouched(true); setKey(slugify(e.target.value)); }} placeholder="produkt" />
            </Field>
            <Field label="Symbol">
              <div className="row" style={{ gap: 8, flexWrap: 'wrap' }}>
                {ICON_OPTS.map((ic) => (
                  <button key={ic} onClick={() => setIcon(ic)}
                    style={{ width: 40, height: 40, borderRadius: 9, display: 'grid', placeItems: 'center', cursor: 'pointer',
                      border: `1.5px solid ${icon === ic ? 'var(--accent)' : 'var(--border)'}`,
                      background: icon === ic ? 'var(--accent-soft)' : 'var(--surface)', color: icon === ic ? 'var(--accent-ink)' : 'var(--muted)' }}>
                    <Icon name={ic} size={20} />
                  </button>
                ))}
              </div>
            </Field>
            <Field label="Beschreibung" opt>
              <textarea className="textarea" style={{ minHeight: 70 }} value={desc} onChange={(e) => setDesc(e.target.value)} placeholder="Wofür ist dieses Template?" />
            </Field>
            <hr className="divider" />
            <div className="col" style={{ gap: 14 }}>
              <div className="row" style={{ justifyContent: 'space-between', gap: 12, whiteSpace: 'nowrap' }}>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Im Menü anzeigen</span><Switch on={true} onClick={() => {}} />
              </div>
              <div className="row" style={{ justifyContent: 'space-between', gap: 12, whiteSpace: 'nowrap' }}>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Versionierung</span><Switch on={true} onClick={() => {}} />
              </div>
              <div className="row" style={{ justifyContent: 'space-between', gap: 12, whiteSpace: 'nowrap' }}>
                <span style={{ fontSize: 13, fontWeight: 600 }}>Kommentare erlauben</span><Switch on={false} onClick={() => {}} />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { TemplatesScreen, BuilderScreen, TemplateCard });

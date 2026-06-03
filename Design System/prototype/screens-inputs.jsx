/* bs-fabric — Editor field inputs (per type) */

function RichText({ value }) {
  const ref = React.useRef();
  const cmd = (c, v) => { document.execCommand(c, false, v); ref.current && ref.current.focus(); };
  const tools = [
    { c: 'bold', label: 'B', style: { fontWeight: 700 } },
    { c: 'italic', label: 'I', style: { fontStyle: 'italic' } },
    { c: 'insertUnorderedList', icon: 'list' },
    { c: 'createLink', icon: 'link', arg: '#' },
  ];
  return (
    <div style={{ border: '1px solid var(--border-strong)', borderRadius: 'var(--r)', overflow: 'hidden' }}>
      <div className="row" style={{ gap: 2, padding: 6, borderBottom: '1px solid var(--border)', background: 'var(--surface-2)' }}>
        <button className="rt-h2" onMouseDown={(e) => { e.preventDefault(); cmd('formatBlock', 'H3'); }}
          style={{ border: 'none', background: 'none', padding: '5px 9px', borderRadius: 6, fontWeight: 700, fontSize: 12.5, color: 'var(--ink-soft)', fontFamily: 'var(--font-brand)' }}>H</button>
        {tools.map((t) => (
          <button key={t.c} onMouseDown={(e) => { e.preventDefault(); cmd(t.c, t.arg); }}
            style={{ border: 'none', background: 'none', width: 30, height: 28, borderRadius: 6, color: 'var(--ink-soft)', display: 'grid', placeItems: 'center', ...t.style }}>
            {t.icon ? <Icon name={t.icon} size={15} /> : t.label}
          </button>
        ))}
        <span className="grow" />
        <span className="muted mono" style={{ fontSize: 10.5, paddingRight: 6 }}>Rich-Text</span>
      </div>
      <div ref={ref} contentEditable suppressContentEditableWarning
        style={{ minHeight: 96, padding: '11px 13px', fontSize: 14, lineHeight: 1.6, outline: 'none' }}
        dangerouslySetInnerHTML={{ __html: value || '<p>Beschreibung eingeben…</p>' }} />
      <style>{`.rt-h2:hover, [contenteditable]:focus { }`}</style>
    </div>
  );
}

function MediaField({ multi }) {
  const [imgs, setImgs] = React.useState(multi ? 2 : 1);
  return (
    <div className="row" style={{ gap: 10, flexWrap: 'wrap' }}>
      {Array.from({ length: imgs }).map((_, i) => (
        <div key={i} style={{ position: 'relative', width: multi ? 96 : 150, height: multi ? 96 : 110 }}>
          <div className="imgph" style={{ width: '100%', height: '100%', flexDirection: 'column', gap: 4 }}>
            <Icon name="t_media" size={20} />
            <span>{multi ? `Bild ${i + 1}` : 'Titelbild'}</span>
          </div>
          <button onClick={() => setImgs((n) => Math.max(0, n - 1))} style={{ position: 'absolute', top: -7, right: -7, width: 22, height: 22, borderRadius: 50, border: '1px solid var(--border)', background: 'var(--surface)', display: 'grid', placeItems: 'center', boxShadow: 'var(--shadow-sm)', color: 'var(--muted)' }}><Icon name="x" size={12} /></button>
        </div>
      ))}
      <button className="imgph up" onClick={() => setImgs((n) => n + 1)}
        style={{ width: multi ? 96 : 150, height: multi ? 96 : 110, flexDirection: 'column', gap: 5, background: 'var(--surface-2)' }}>
        <Icon name="plus" size={18} />
        <span>{imgs === 0 ? 'Aus Mediathek' : 'Hinzufügen'}</span>
      </button>
    </div>
  );
}

function RelationField({ field }) {
  const opts = window.DB.entries.slice(0, 6);
  const [sel, setSel] = React.useState([opts[0], opts[2]]);
  const [open, setOpen] = React.useState(false);
  const avail = opts.filter((o) => !sel.includes(o));
  return (
    <div>
      <div className="row" style={{ gap: 8, flexWrap: 'wrap', marginBottom: sel.length ? 9 : 0 }}>
        {sel.map((s) => (
          <span key={s.id} className="row" style={{ gap: 8, background: 'var(--thread-soft)', color: 'var(--thread-ink)', borderRadius: 20, padding: '5px 6px 5px 12px', fontSize: 13, fontWeight: 600 }}>
            <Icon name="link" size={13} />{s.titel}
            <button onClick={() => setSel((a) => a.filter((x) => x !== s))} style={{ border: 'none', background: 'oklch(1 0 0 / 0.5)', width: 18, height: 18, borderRadius: 50, display: 'grid', placeItems: 'center', color: 'inherit' }}><Icon name="x" size={11} /></button>
          </span>
        ))}
      </div>
      <div style={{ position: 'relative', display: 'inline-block' }}>
        <Btn variant="ghost" icon="plus" sm onClick={() => setOpen(!open)}>Verknüpfen</Btn>
        {open && (
          <>
            <div style={{ position: 'fixed', inset: 0, zIndex: 20 }} onClick={() => setOpen(false)} />
            <div style={{ position: 'absolute', top: 'calc(100% + 6px)', left: 0, zIndex: 21, width: 250, background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 'var(--r)', boxShadow: 'var(--shadow-lg)', padding: 6 }}>
              {avail.length === 0 && <div className="muted" style={{ padding: 12, fontSize: 13, textAlign: 'center' }}>Keine weiteren Einträge.</div>}
              {avail.map((o) => (
                <button key={o.id} onClick={() => { setSel((a) => [...a, o]); setOpen(false); }} className="addrow"
                  style={{ display: 'flex', gap: 9, alignItems: 'center', width: '100%', padding: '8px 10px', border: 'none', background: 'none', borderRadius: 6, textAlign: 'left', fontSize: 13 }}>
                  <Icon name="rows" size={14} className="muted" />{o.titel}
                </button>
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function RepeaterField() {
  const [rows, setRows] = React.useState([{ k: 'Material', v: 'Merinowolle' }, { k: 'Maße', v: '130 × 180 cm' }]);
  return (
    <div style={{ border: '1px solid var(--border)', borderRadius: 'var(--r)', overflow: 'hidden' }}>
      {rows.map((r, i) => (
        <div key={i} className="row" style={{ gap: 8, padding: 8, borderBottom: '1px solid var(--border)', background: i % 2 ? 'var(--surface-2)' : 'var(--surface)' }}>
          <span style={{ color: 'var(--faint)', display: 'flex' }}><Icon name="drag" size={16} /></span>
          <input className="input" style={{ width: 150, height: 34, fontWeight: 600 }} defaultValue={r.k} placeholder="Bezeichnung" />
          <input className="input grow" style={{ height: 34 }} defaultValue={r.v} placeholder="Wert" />
          <Btn variant="subtle" icon="trash" sm onClick={() => setRows((a) => a.filter((_, k) => k !== i))} style={{ color: 'var(--danger)' }} />
        </div>
      ))}
      <div style={{ padding: 8 }}>
        <Btn variant="subtle" icon="plus" sm onClick={() => setRows((a) => [...a, { k: '', v: '' }])}>Zeile hinzufügen</Btn>
      </div>
    </div>
  );
}

function FieldInput({ field, value }) {
  switch (field.type) {
    case 'text': return <input className="input" defaultValue={value} placeholder={`${field.label} eingeben…`} />;
    case 'textarea': return <textarea className="textarea" defaultValue={value} placeholder="Kurzer Teaser…" />;
    case 'richtext': return <RichText value={value} />;
    case 'number': return <input className="input mono" type="number" defaultValue={value} placeholder="0" style={{ maxWidth: 200 }} />;
    case 'media': return <MediaField multi={field.key === 'galerie'} />;
    case 'relation': return <RelationField field={field} />;
    case 'repeater': return <RepeaterField />;
    default: return <input className="input" />;
  }
}

Object.assign(window, { RichText, MediaField, RelationField, RepeaterField, FieldInput });

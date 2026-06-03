/* bs-fabric — Fields management screen */

function slugify(s) {
  return (s || '').toLowerCase()
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
}

function TypePicker({ value, onChange }) {
  const groups = {};
  Object.values(window.DB.TYPES).forEach((t) => { (groups[t.group] ||= []).push(t); });
  return (
    <div className="col" style={{ gap: 16 }}>
      {Object.entries(groups).map(([g, types]) => (
        <div key={g}>
          <div className="nav-label" style={{ color: 'var(--muted)', padding: '0 0 8px' }}>{g}</div>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 9 }}>
            {types.map((t) => {
              const on = value === t.key;
              return (
                <button key={t.key} onClick={() => onChange(t.key)}
                  style={{
                    display: 'flex', gap: 11, alignItems: 'center', textAlign: 'left',
                    padding: '11px 12px', borderRadius: 'var(--r)', cursor: 'pointer',
                    border: `1.5px solid ${on ? 'var(--accent)' : 'var(--border)'}`,
                    background: on ? 'var(--accent-soft)' : 'var(--surface)',
                    boxShadow: on ? '0 0 0 3px var(--accent-soft)' : 'none', transition: '0.13s',
                  }}>
                  <span style={{
                    width: 32, height: 32, borderRadius: 8, flex: 'none', display: 'grid', placeItems: 'center',
                    background: on ? 'var(--accent)' : 'var(--surface-sunken)',
                    color: on ? '#fff' : 'var(--accent)',
                  }}>
                    <Icon name={TYPE_ICON[t.key]} size={17} />
                  </span>
                  <span className="col" style={{ gap: 1, minWidth: 0 }}>
                    <span style={{ fontWeight: 600, fontSize: 13.5 }}>{t.name}</span>
                    <span style={{ fontSize: 11.5, color: 'var(--muted)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{t.hint}</span>
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      ))}
    </div>
  );
}

function FieldEditor({ field, onClose, onSave }) {
  const isNew = !field.id;
  const [label, setLabel] = React.useState(field.label || '');
  const [key, setKey] = React.useState(field.key || '');
  const [keyTouched, setKeyTouched] = React.useState(!isNew);
  const [type, setType] = React.useState(field.type || 'text');
  const [desc, setDesc] = React.useState(field.desc || '');
  const [required, setRequired] = React.useState(!!field.required);

  const setLabelSmart = (v) => {
    setLabel(v);
    if (!keyTouched) setKey(slugify(v));
  };

  const tdef = window.DB.TYPES[type];

  return (
    <Sheet
      title={isNew ? 'Neues Feld' : 'Feld bearbeiten'}
      sub={isNew ? 'Definiere einen wiederverwendbaren Datenbaustein' : `Feld-ID ${field.id}`}
      onClose={onClose}
      footer={
        <>
          <Btn variant="primary" icon="save" onClick={() => onSave({ ...field, label, key, type, desc, required })}>
            {isNew ? 'Feld anlegen' : 'Speichern'}
          </Btn>
          <Btn variant="subtle" onClick={onClose}>Abbrechen</Btn>
          <div className="grow" />
          {!isNew && <Btn variant="danger" icon="trash" sm onClick={onClose}>Löschen</Btn>}
        </>
      }
    >
      <Field label="Bezeichnung" req hint="So sehen Redakteure das Feld in der Eingabemaske.">
        <input className="input" value={label} onChange={(e) => setLabelSmart(e.target.value)} placeholder="z. B. Titelbild" autoFocus />
      </Field>

      <Field label="Feld-Key" hint="Technischer Bezeichner für Templates & API. Nur Kleinbuchstaben.">
        <input className="input mono" value={key}
          onChange={(e) => { setKeyTouched(true); setKey(slugify(e.target.value)); }}
          placeholder="titelbild" />
      </Field>

      <div className="field">
        <label className="flabel">Feldtyp <span className="req">*</span></label>
        <TypePicker value={type} onChange={setType} />
      </div>

      <hr className="divider" />

      {/* type-specific options */}
      <div style={{ background: 'var(--surface-2)', border: '1px solid var(--border)', borderRadius: 'var(--r)', padding: '14px 16px' }}>
        <div className="row" style={{ marginBottom: 12 }}>
          <Icon name={TYPE_ICON[type]} size={15} className="" />
          <span style={{ fontWeight: 600, fontSize: 13 }}>Optionen für „{tdef.name}"</span>
        </div>
        {(type === 'text') && (
          <Field label="Platzhalter" opt><input className="input" placeholder="z. B. Produktname eingeben…" /></Field>
        )}
        {(type === 'textarea') && (
          <Field label="Max. Zeichen" opt><input className="input" type="number" defaultValue={240} /></Field>
        )}
        {(type === 'number') && (
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
            <Field label="Min"><input className="input" type="number" placeholder="0" /></Field>
            <Field label="Max"><input className="input" type="number" placeholder="—" /></Field>
            <Field label="Einheit"><input className="input" placeholder="€" /></Field>
          </div>
        )}
        {(type === 'media') && (
          <div className="row" style={{ justifyContent: 'space-between' }}>
            <span style={{ fontSize: 13 }}>Mehrfachauswahl erlauben</span><Switch on={false} onClick={() => {}} />
          </div>
        )}
        {(type === 'relation') && (
          <Field label="Verweist auf" hint="Aus welchem Template/Post-Typ darf gewählt werden.">
            <Select value="t1" onChange={() => {}}>
              {window.DB.templates.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
              <option value="post">WordPress-Beiträge</option>
            </Select>
          </Field>
        )}
        {(type === 'repeater') && (
          <div className="fhint" style={{ marginTop: 0 }}>Unterfelder konfigurierst du direkt im Template-Builder, sobald das Feld eingesetzt wird.</div>
        )}
        {(type === 'richtext') && (
          <div className="fhint" style={{ marginTop: 0 }}>Voller Editor mit Fett, Kursiv, Listen, Links & Überschriften.</div>
        )}
      </div>

      <hr className="divider" />

      <Field label="Beschreibung / Hilfetext" opt hint="Wird klein unter dem Feld angezeigt.">
        <textarea className="textarea" style={{ minHeight: 64 }} value={desc} onChange={(e) => setDesc(e.target.value)} placeholder="Hinweis für Redakteure…" />
      </Field>

      <div className="row" style={{ justifyContent: 'space-between', padding: '12px 14px', background: 'var(--surface-2)', border: '1px solid var(--border)', borderRadius: 'var(--r)' }}>
        <div className="col" style={{ gap: 2 }}>
          <span style={{ fontWeight: 600, fontSize: 13 }}>Pflichtfeld</span>
          <span style={{ fontSize: 12, color: 'var(--muted)' }}>Standard, wenn das Feld eingesetzt wird</span>
        </div>
        <Switch on={required} onClick={() => setRequired(!required)} />
      </div>
    </Sheet>
  );
}

function FieldsScreen({ go, toast }) {
  const [fields, setFields] = React.useState(window.DB.fields);
  const [editing, setEditing] = React.useState(null);
  const [q, setQ] = React.useState('');
  const [filter, setFilter] = React.useState('all');

  const shown = fields.filter((f) =>
    (filter === 'all' || f.type === filter) &&
    (f.label.toLowerCase().includes(q.toLowerCase()) || f.key.includes(q.toLowerCase())));

  const save = (f) => {
    if (f.id) setFields((arr) => arr.map((x) => (x.id === f.id ? f : x)));
    else setFields((arr) => [{ ...f, id: 'f' + (arr.length + 1), used: 0 }, ...arr]);
    setEditing(null);
    toast(f.id ? 'Feld gespeichert' : 'Feld angelegt');
  };

  const typeFilters = [['all', 'Alle']].concat(Object.values(window.DB.TYPES).map((t) => [t.key, t.name]));

  return (
    <div className="route-fade">
      <PageHead title="Fields" lede="Die Fäden deines Gewebes — wiederverwendbare Datenbausteine, die du in beliebige Templates einsetzt.">
        <Btn variant="primary" icon="plus" onClick={() => setEditing({})}>Neues Feld</Btn>
      </PageHead>

      <div className="card">
        <div className="card-head">
          <div className="search" style={{ width: 240, background: 'var(--surface-2)' }}>
            <Icon name="search" size={15} />
            <input placeholder="Feld suchen…" value={q} onChange={(e) => setQ(e.target.value)} />
          </div>
          <div className="ca" style={{ flexWrap: 'wrap' }}>
            <div className="select-wrap">
              <select className="selectbox" style={{ height: 32, padding: '0 30px 0 12px', fontSize: 13 }} value={filter} onChange={(e) => setFilter(e.target.value)}>
                {typeFilters.map(([v, l]) => <option key={v} value={v}>{l === 'Alle' ? 'Alle Typen' : l}</option>)}
              </select>
              <Icon name="chevd" size={14} />
            </div>
          </div>
          <span className="muted" style={{ fontSize: 12.5, marginLeft: 4 }}>{shown.length} Felder</span>
        </div>

        {shown.length === 0 ? (
          <Empty icon="threads" title="Keine Felder gefunden">Passe Suche oder Filter an.</Empty>
        ) : (
          <table className="tbl">
            <thead>
              <tr>
                <th>Bezeichnung</th>
                <th>Feld-Key</th>
                <th>Typ</th>
                <th>Verwendung</th>
                <th style={{ width: 60 }}></th>
              </tr>
            </thead>
            <tbody>
              {shown.map((f) => (
                <tr key={f.id} className="row-click" onClick={() => setEditing(f)}>
                  <td>
                    <div className="row" style={{ gap: 10 }}>
                      <span className="cell-strong">{f.label}</span>
                      {f.required && <Badge variant="">Pflicht</Badge>}
                    </div>
                    {f.desc && <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 1 }}>{f.desc}</div>}
                  </td>
                  <td><span className="key">{f.key}</span></td>
                  <td><TypeBadge type={f.type} /></td>
                  <td>
                    {f.used > 0
                      ? <span className="row" style={{ gap: 7, whiteSpace: 'nowrap' }}><Icon name="layers" size={14} className="muted" /><span className="cell-mute">{f.used} Templates</span></span>
                      : <span className="cell-mute" style={{ color: 'var(--faint)' }}>—</span>}
                  </td>
                  <td onClick={(e) => e.stopPropagation()}>
                    <div className="row" style={{ gap: 4 }}>
                      <Btn variant="subtle" icon="edit" sm onClick={() => setEditing(f)} />
                      <Btn variant="subtle" icon="copy" sm onClick={() => toast('Feld dupliziert')} />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {editing && <FieldEditor field={editing} onClose={() => setEditing(null)} onSave={save} />}
    </div>
  );
}

Object.assign(window, { FieldsScreen, FieldEditor, TypePicker, slugify });

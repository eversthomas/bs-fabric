/* bs-fabric — icons + shared UI primitives */

const I = ({ d, fill, w = 18, vb = 24, sw = 1.8 }) => (
  <svg viewBox={`0 0 ${vb} ${vb}`} width={w} height={w} fill={fill ? 'currentColor' : 'none'}
       stroke={fill ? 'none' : 'currentColor'} strokeWidth={sw} strokeLinecap="round" strokeLinejoin="round">
    {d}
  </svg>
);

const ICONS = {
  dash:   <><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></>,
  layers: <><path d="M12 3l8 4.5-8 4.5-8-4.5L12 3z"/><path d="M4 12l8 4.5 8-4.5"/><path d="M4 16.5l8 4.5 8-4.5"/></>,
  threads:<><path d="M4 6h10"/><circle cx="18" cy="6" r="2"/><path d="M4 12h6"/><circle cx="14" cy="12" r="2"/><path d="M4 18h12"/><circle cx="20" cy="18" r="2"/></>,
  rows:   <><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9.5h18M3 15h18M9 4v16"/></>,
  sliders:<><path d="M4 8h10M18 8h2M4 16h2M10 16h10"/><circle cx="16" cy="8" r="2"/><circle cx="8" cy="16" r="2"/></>,
  plus:   <><path d="M12 5v14M5 12h14"/></>,
  search: <><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></>,
  chevd:  <><path d="M6 9l6 6 6-6"/></>,
  chevr:  <><path d="M9 6l6 6-6 6"/></>,
  chevl:  <><path d="M15 6l-6 6 6 6"/></>,
  check:  <><path d="M5 12.5l4.5 4.5L19 7"/></>,
  x:      <><path d="M6 6l12 12M18 6L6 18"/></>,
  trash:  <><path d="M4 7h16M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2M6 7l1 13a1 1 0 001 1h8a1 1 0 001-1l1-13"/></>,
  copy:   <><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 012-2h8"/></>,
  drag:   <><circle cx="9" cy="6" r="1.4" fill /><circle cx="9" cy="12" r="1.4" fill/><circle cx="9" cy="18" r="1.4" fill/><circle cx="15" cy="6" r="1.4" fill/><circle cx="15" cy="12" r="1.4" fill/><circle cx="15" cy="18" r="1.4" fill/></>,
  eye:    <><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></>,
  edit:   <><path d="M4 20h4l10-10a2.8 2.8 0 00-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/></>,
  back:   <><path d="M19 12H5M11 18l-6-6 6-6"/></>,
  more:   <><circle cx="5" cy="12" r="1.6" fill/><circle cx="12" cy="12" r="1.6" fill/><circle cx="19" cy="12" r="1.6" fill/></>,
  filter: <><path d="M3 5h18l-7 8v6l-4-2v-4L3 5z"/></>,
  external:<><path d="M14 4h6v6M20 4l-9 9M18 13v5a2 2 0 01-2 2H6a2 2 0 01-2-2V8a2 2 0 012-2h5"/></>,
  // field types
  t_text:     <><path d="M5 7V5h14v2M12 5v14M9 19h6"/></>,
  t_textarea: <><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M7 9h10M7 12h10M7 15h6"/></>,
  t_richtext: <><path d="M5 6h6M5 6v12M8 6v12M14 9h5M14 13h5M14 17h3" /><path d="M5 18h6"/></>,
  t_number:   <><path d="M6 9h12M5 15h12M9 5l-1 14M16 5l-1 14"/></>,
  t_media:    <><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.6"/><path d="M21 16l-5-5-8 8"/></>,
  t_relation: <><path d="M9 12a3 3 0 013-3h2M15 12a3 3 0 01-3 3h-2"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="12" r="3"/></>,
  t_repeater: <><rect x="4" y="4" width="12" height="12" rx="2"/><path d="M8 20h10a2 2 0 002-2V8"/></>,
  // template glyphs
  box:  <><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M4 7.5l8 4.5 8-4.5M12 12v9"/></>,
  doc:  <><path d="M6 3h8l4 4v14a0 0 0 01 0 0H6a0 0 0 010 0V3z"/><path d="M14 3v4h4M9 13h6M9 17h4"/></>,
  user: <><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0114 0"/></>,
  cal:  <><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 9h16M9 3v4M15 3v4"/></>,
  save: <><path d="M5 3h11l3 3v15H5V3z"/><path d="M8 3v5h7M8 21v-7h8v7"/></>,
  grid: <><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></>,
  list: <><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></>,
  bolt: <><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/></>,
  link: <><path d="M9 15l6-6M10 7l1-1a4 4 0 016 6l-1 1M14 17l-1 1a4 4 0 01-6-6l1-1"/></>,
  info: <><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></>,
};

function Icon({ name, className = '', size = 18, fill = false }) {
  const d = ICONS[name] || ICONS.info;
  return <span className={className} style={{ display: 'inline-flex' }}><I d={d} w={size} fill={fill} /></span>;
}

const TYPE_ICON = {
  text: 't_text', textarea: 't_textarea', richtext: 't_richtext', number: 't_number',
  media: 't_media', relation: 't_relation', repeater: 't_repeater',
};

function TypeBadge({ type }) {
  const t = window.DB.TYPES[type];
  return (
    <span className="ftype">
      <Icon name={TYPE_ICON[type]} size={13} />
      {t ? t.name : type}
    </span>
  );
}

function Btn({ variant = 'ghost', icon, children, sm, className = '', ...p }) {
  return (
    <button className={`btn ${variant} ${sm ? 'sm' : ''} ${!children ? 'icon' : ''} ${className}`} {...p}>
      {icon && <Icon name={icon} size={sm ? 15 : 16} />}
      {children}
    </button>
  );
}

function Badge({ children, variant = '', className = '', dot }) {
  return <span className={`badge ${variant} ${dot ? 'dot' : ''} ${className}`}>{children}</span>;
}

function StatusBadge({ status }) {
  const s = window.DB.STATUS[status] || { label: status, cls: '' };
  return <Badge variant={s.cls} dot>{s.label}</Badge>;
}

function Check({ on, onClick }) {
  return (
    <span className={`checkbox ${on ? 'on' : ''}`} onClick={onClick} role="checkbox" aria-checked={on}>
      {on && <Icon name="check" size={11} />}
    </span>
  );
}

function Switch({ on, onClick }) {
  return <button className={`switch ${on ? 'on' : ''}`} onClick={onClick} aria-pressed={on} />;
}

function Field({ label, req, opt, hint, children }) {
  return (
    <div className="field">
      {label && (
        <label className="flabel">
          {label} {req && <span className="req">*</span>} {opt && <span className="opt">optional</span>}
        </label>
      )}
      {children}
      {hint && <div className="fhint">{hint}</div>}
    </div>
  );
}

function Select({ value, onChange, children }) {
  return (
    <div className="select-wrap">
      <select className="selectbox" value={value} onChange={onChange}>{children}</select>
      <Icon name="chevd" size={15} />
    </div>
  );
}

// Slide-over sheet
function Sheet({ title, sub, onClose, children, footer, width }) {
  React.useEffect(() => {
    const h = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', h);
    return () => window.removeEventListener('keydown', h);
  }, [onClose]);
  return (
    <>
      <div className="scrim" onClick={onClose} />
      <aside className="sheet" style={width ? { width } : null}>
        <div className="sheet-head">
          <div className="col" style={{ gap: 2 }}>
            <h2>{title}</h2>
            {sub && <span className="muted" style={{ fontSize: 12.5 }}>{sub}</span>}
          </div>
          <Btn variant="subtle" icon="x" sm onClick={onClose} style={{ marginLeft: 'auto' }} />
        </div>
        <div className="sheet-body">{children}</div>
        {footer && <div className="sheet-foot">{footer}</div>}
      </aside>
    </>
  );
}

// Toasts
function useToasts() {
  const [toasts, setToasts] = React.useState([]);
  const push = React.useCallback((msg) => {
    const id = Math.random().toString(36).slice(2);
    setToasts((t) => [...t, { id, msg }]);
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 2600);
  }, []);
  const node = (
    <div className="toast-wrap">
      {toasts.map((t) => (
        <div className="toast" key={t.id}><Icon name="check" size={16} />{t.msg}</div>
      ))}
    </div>
  );
  return [push, node];
}

const NAV = [
  { id: 'dash',      label: 'Übersicht',     icon: 'dash' },
  { id: 'templates', label: 'Templates',     icon: 'layers',  count: () => window.DB.templates.length },
  { id: 'fields',    label: 'Fields',        icon: 'threads', count: () => window.DB.fields.length },
  { id: 'data',      label: 'Einträge',      icon: 'rows' },
  { id: 'settings',  label: 'Einstellungen', icon: 'sliders' },
];

function Sidebar({ route, go }) {
  const active = route.name === 'builder' || route.name === 'templates' ? 'templates'
    : route.name === 'editor' || route.name === 'entry' || route.name === 'data' ? 'data'
    : route.name;
  return (
    <nav className="side">
      <div className="side-brand">
        <div className="brand-mark" />
        <div>
          <div className="brand-name">bs<b>·</b>fabric</div>
          <div className="brand-sub">v2.4 · structured data</div>
        </div>
      </div>
      <div className="side-nav">
        <div className="nav-label">Arbeitsbereich</div>
        {NAV.map((n) => (
          <button key={n.id} className={`nav-item ${active === n.id ? 'active' : ''}`} onClick={() => go(n.id)}>
            <span className="nav-ic"><Icon name={n.icon} size={18} /></span>
            {n.label}
            {n.count && <span className="nav-count">{n.count()}</span>}
          </button>
        ))}
      </div>
      <div className="side-foot">
        <div className="avatar">LV</div>
        <div className="col grow">
          <span className="who">Lena Vogt</span>
          <span className="role">Chefredaktion</span>
        </div>
        <Btn variant="subtle" icon="chevd" sm style={{ color: 'var(--side-muted)' }} />
      </div>
    </nav>
  );
}

function Topbar({ crumbs, onSearch }) {
  return (
    <header className="topbar">
      <div className="crumbs">
        {crumbs.map((c, i) => (
          <React.Fragment key={i}>
            {i > 0 && <span className="sep"><Icon name="chevr" size={13} /></span>}
            {c.go ? <a onClick={c.go}>{c.label}</a> : <span className="here">{c.label}</span>}
          </React.Fragment>
        ))}
      </div>
      <div className="spacer" />
      <div className="search">
        <Icon name="search" size={16} />
        <input placeholder="Suchen…  ⌘K" />
      </div>
    </header>
  );
}

function PageHead({ title, lede, children }) {
  return (
    <div className="page-head">
      <div className="grow">
        <h1>{title}</h1>
        {lede && <p className="lede">{lede}</p>}
      </div>
      {children && <div className="ha">{children}</div>}
    </div>
  );
}

function Empty({ icon = 'info', title, children }) {
  return (
    <div className="empty">
      <div className="ei"><Icon name={icon} size={46} /></div>
      <h4>{title}</h4>
      <div>{children}</div>
    </div>
  );
}

Object.assign(window, {
  Icon, TypeBadge, TYPE_ICON, Btn, Badge, StatusBadge, Check, Switch,
  Field, Select, Sheet, useToasts, Sidebar, Topbar, PageHead, Empty,
});

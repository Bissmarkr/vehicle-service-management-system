import { useEffect, useState } from 'react';
import {
  Bell, CalendarDays, CarFront, Check, ChevronRight,
  FileText, Gauge, History, Home, LogOut, Menu, Plus, Search, Settings, Sparkles,
  Wrench, X, Zap,
} from 'lucide-react';
import api from './services/api';
import { createCheckoutSession } from './services/paymentService';

const statusOrder = ['PENDING', 'CONFIRMED', 'ASSIGNED', 'IN_PROGRESS', 'COMPLETED'];
const statusLabel = { PENDING: 'Requested', CONFIRMED: 'Confirmed', ASSIGNED: 'Mechanic assigned', IN_PROGRESS: 'In progress', COMPLETED: 'Completed', CANCELLED: 'Cancelled' };

function formatDate(value) {
  if (!value) return 'Not scheduled';
  return new Date(value).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}

function money(value) {
  return `PKR ${Number(value || 0).toLocaleString()}`;
}

function apiError(error) {
  if (!error.response) return 'Unable to connect to the Laravel server.';
  return Object.values(error.response.data?.errors || {}).flat().join(' ') || error.response.data?.message || 'The operation failed.';
}

function StatusPill({ status }) {
  return <span className={`customer-status status-${String(status).toLowerCase()}`}><span />{status?.replace('_', ' ')}</span>;
}

function Timeline({ status }) {
  const current = statusOrder.indexOf(status);
  return <div className="booking-timeline">{statusOrder.map((step, index) => <div className={index <= current ? 'timeline-step active' : 'timeline-step'} key={step}><span>{index < current ? <Check size={12} /> : index + 1}</span><small>{statusLabel[step]}</small></div>)}</div>;
}

function Modal({ title, children, onClose }) {
  return <div className="customer-modal-backdrop"><div className="customer-modal"><div className="customer-modal-head"><div><span className="eyebrow">VSMS CUSTOMER</span><h2>{title}</h2></div><button className="icon-button" onClick={onClose} aria-label="Close"><X size={18} /></button></div>{children}</div></div>;
}

function CustomerDashboard({ auth, logout }) {
  const [data, setData] = useState(null);
  const [search, setSearch] = useState('');
  const [mobileNav, setMobileNav] = useState(false);
  const [modal, setModal] = useState(null);
  const [form, setForm] = useState({});
  const [saving, setSaving] = useState(false);
  const [paying, setPaying] = useState(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const load = async (term = search) => {
    try { const response = await api.get('/customer/overview', { params: term ? { search: term } : {} }); setData(response.data?.data || null); } catch (requestError) { setError(apiError(requestError)); }
  };
  // The initial request is an external data synchronization, not derived state.
  // eslint-disable-next-line react-hooks/set-state-in-effect, react-hooks/exhaustive-deps
  useEffect(() => { load(''); }, []);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { const timer = setTimeout(() => { if (search.length > 1 || search.length === 0) load(search); }, 300); return () => clearTimeout(timer); }, [search]);

  const customer = data?.customer || { name: auth.name, email: auth.email };
  const stats = data?.statistics || {};
  const activeBookings = data?.recent_bookings || [];
  const vehicles = data?.vehicles || [];
  const chartMax = Math.max(...(data?.activity || []).map((item) => Number(item.bookings)), 1);
  const nextReminder = activeBookings.find((booking) => ['PENDING', 'CONFIRMED', 'ASSIGNED'].includes(booking.booking_status));

  const openVehicle = (vehicle) => { setError(''); setModal({ type: 'vehicle', editing: vehicle }); setForm(vehicle ? { ...vehicle } : {}); };
  const openBooking = () => { setError(''); setModal({ type: 'booking' }); setForm({}); };
  const submit = async (event) => {
    event.preventDefault(); setSaving(true); setError('');
    try {
      if (modal.type === 'vehicle') {
        await (modal.editing ? api.put(`/vehicles/${modal.editing.vehicle_id}`, form) : api.post('/vehicles', form));
        setModal(null); setMessage('Vehicle saved successfully.');
      } else {
        const response = await api.post('/bookings', form);
        const checkoutUrl = response?.data?.checkout_url || response?.data?.data?.checkout_url;
        if (checkoutUrl) {
          window.location.href = checkoutUrl;
          return;
        }
        setModal(null); setMessage('Service booking created successfully.');
      }
      await load();
    } catch (requestError) { setError(apiError(requestError)); } finally { setSaving(false); }
  };

  const printInvoice = (invoice) => {
    const booking = invoice.booking || {}; const vehicle = booking.vehicle || {}; const service = booking.service || {};
    const popup = window.open('', '_blank', 'width=720,height=720');
    popup.document.write(`<html><head><title>VSMS Invoice #${invoice.invoice_id}</title><style>body{font-family:Arial;padding:40px;color:#14202a}h1{color:#22d3a1}table{width:100%;border-collapse:collapse;margin-top:30px}td{padding:12px;border-bottom:1px solid #ddd}</style></head><body><h1>VSMS</h1><p>Invoice #${invoice.invoice_id} &middot; ${formatDate(invoice.invoice_date)}</p><table><tr><td>Vehicle</td><td>${vehicle.make || ''} ${vehicle.model || ''} (${vehicle.registration_number || '-'})</td></tr><tr><td>Service</td><td>${service.service_name || '-'}</td></tr><tr><td>Service charge</td><td>${money(invoice.service_charge)}</td></tr><tr><td>Parts</td><td>${money(invoice.parts_charge)}</td></tr><tr><td><strong>Total</strong></td><td><strong>${money(invoice.total_amount)}</strong></td></tr></table><script>window.print()</script></body></html>`);
    popup.document.close();
  };

  const payInvoice = async (invoiceId) => {
    setPaying(invoiceId);
    setError('');
    try {
      const response = await createCheckoutSession(invoiceId);
      if (response?.checkout_url) {
        window.location.href = response.checkout_url;
        return;
      }
      throw new Error('Missing checkout URL');
    } catch (requestError) {
      setError(requestError?.response?.data?.message || 'Unable to start payment. Please try again.');
    } finally {
      setPaying(null);
    }
  };

  const nav = [['overview', Home, 'Dashboard'], ['vehicles', CarFront, 'My Vehicles'], ['booking', CalendarDays, 'Book Service'], ['history', History, 'Service History'], ['invoices', FileText, 'Invoices']];
  return <div className="customer-shell">
    <aside className={mobileNav ? 'customer-sidebar open' : 'customer-sidebar'}><div className="customer-brand"><div className="brand-mark"><CarFront size={20} /></div><div><strong>VSMS</strong><span>Vehicle service</span></div></div><nav>{nav.map(([key, Icon, label]) => <button key={key} className={(!modal && key === 'overview') || (modal?.type === 'vehicle' && key === 'vehicles') || (modal?.type === 'booking' && key === 'booking') ? 'customer-nav active' : 'customer-nav'} onClick={() => { setMobileNav(false); if (key === 'vehicles') openVehicle(); else if (key === 'booking') openBooking(); else document.getElementById(`customer-${key}`)?.scrollIntoView({ behavior: 'smooth' }); }}><Icon size={17} /><span>{label}</span></button>)}</nav><div className="sidebar-bottom"><button className="customer-nav" onClick={() => setMessage('Profile settings are managed from your account records.') }><Settings size={17} /><span>Settings</span></button><button className="customer-nav" onClick={logout}><LogOut size={17} /><span>Log out</span></button></div></aside>
    <main className="customer-main"><header className="customer-topbar"><button className="mobile-menu" onClick={() => setMobileNav(!mobileNav)} aria-label="Open menu"><Menu size={20} /></button><div className="customer-search"><Search size={17} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search your vehicles, bookings or invoices" /></div><div className="customer-top-actions"><button className="icon-button" onClick={() => setMessage(data?.notifications?.length ? `${data.notifications.length} booking updates available.` : 'No new notifications.')} aria-label="Notifications"><Bell size={18} />{data?.notifications?.length > 0 && <i />}</button><div className="customer-profile"><div className="avatar">{customer.name?.slice(0, 1).toUpperCase() || 'C'}</div><div><strong>{customer.name || 'Customer'}</strong><span>Customer account</span></div></div></div></header>
      <div className="customer-content"><div className="customer-hero" id="customer-overview"><div><span className="eyebrow"><Zap size={13} /> CUSTOMER CONTROL CENTER</span><h1>Welcome back, <em>{customer.name?.split(' ')[0] || 'there'}</em></h1><p>Keep your vehicles moving with a clearer view of every service moment.</p><div className="hero-actions"><button className="neon-button" onClick={openBooking}><Plus size={17} /> Book a service</button><button className="ghost-button" onClick={() => document.getElementById('customer-vehicles')?.scrollIntoView({ behavior: 'smooth' })}>View vehicles <ChevronRight size={16} /></button></div></div><div className="hero-orbit"><div className="orbit-ring ring-one" /><div className="orbit-ring ring-two" /><CarFront size={76} strokeWidth={1.1} /></div></div>
        {message && <div className="customer-toast"><Check size={16} />{message}<button onClick={() => setMessage('')}><X size={14} /></button></div>}{error && <div className="customer-alert">{error}<button onClick={() => setError('')}><X size={14} /></button></div>}
        <section className="customer-stats">{[[CarFront, 'My vehicles', stats.vehicles || 0, 'registered in your garage', 'mint'], [CalendarDays, 'Active bookings', stats.active_bookings || 0, 'service moments in motion', 'cyan'], [Check, 'Completed services', stats.completed_services || 0, 'completed with VSMS', 'blue'], [FileText, 'Pending payments', money(stats.pending_payments), 'awaiting settlement', 'amber']].map(([Icon, label, value, caption, tone]) => <div className={`customer-stat ${tone}`} key={label}><div className="stat-icon"><Icon size={18} /></div><span>{label}</span><strong>{value}</strong><small>{caption}</small><div className="stat-spark"><i /><i /><i /><i /><i /></div></div>)}</section>
        <div className="customer-grid"><section className="glass-panel vehicles-panel" id="customer-vehicles"><div className="panel-heading"><div><span className="eyebrow">YOUR GARAGE</span><h2>My vehicles</h2></div><button className="text-button" onClick={() => openVehicle()}><Plus size={15} /> Add vehicle</button></div><div className="vehicle-list">{vehicles.length ? vehicles.map((vehicle) => <article className="vehicle-card" key={vehicle.vehicle_id}><div className="vehicle-visual"><CarFront size={42} /><span>VSMS GARAGE</span></div><div className="vehicle-info"><div><span className="vehicle-reg">{vehicle.registration_number}</span><StatusPill status="COMPLETED" /></div><h3>{vehicle.make} <b>{vehicle.model}</b></h3><p>{vehicle.manufacture_year || 'Year not set'} &middot; Personal vehicle</p><div className="vehicle-actions"><button onClick={() => openVehicle(vehicle)}>Edit</button><button onClick={openBooking}>Book service <ChevronRight size={14} /></button></div></div></article>) : <div className="empty-state"><CarFront size={28} /><p>Your garage is empty.</p><button className="neon-button" onClick={() => openVehicle()}><Plus size={16} /> Add your first vehicle</button></div>}</div></section>
          <section className="glass-panel activity-panel"><div className="panel-heading"><div><span className="eyebrow">SERVICE ACTIVITY</span><h2>Booking pulse</h2></div><Gauge size={20} className="panel-icon" /></div><div className="activity-chart">{(data?.activity || []).length ? data.activity.map((item) => <div className="chart-bar" key={item.month}><span style={{ height: `${Math.max(12, Number(item.bookings) / chartMax * 100)}%` }} /><small>{item.month?.slice(5)}</small></div>) : <p className="muted-copy">Your service activity will appear here.</p>}</div><div className="chart-legend"><span><i className="legend-mint" />Bookings</span><span><i className="legend-cyan" />Completed</span></div></section></div>
        <section className="glass-panel bookings-panel" id="customer-booking"><div className="panel-heading"><div><span className="eyebrow">LIVE SERVICE TRACKING</span><h2>Active bookings</h2></div><button className="text-button" onClick={openBooking}><Plus size={15} /> New booking</button></div>{activeBookings.length ? <div className="booking-list">{activeBookings.map((booking) => <article className="booking-row" key={booking.booking_id}><div className="booking-date"><strong>{new Date(booking.preferred_date).getDate()}</strong><span>{new Date(booking.preferred_date).toLocaleDateString(undefined, { month: 'short' })}</span></div><div className="booking-details"><div><h3>{booking.service?.service_name || 'Service booking'}</h3><p>{booking.vehicle?.make} {booking.vehicle?.model} &middot; #{booking.booking_id}</p></div><StatusPill status={booking.booking_status} /><div>{booking.payment_status === 'PENDING' ? <button className="neon-button" onClick={() => payInvoice((data?.invoices || []).find((inv) => inv.booking_id === booking.booking_id)?.invoice_id ?? booking.booking_id)}>Pay Now</button> : null}</div><Timeline status={booking.booking_status} /></div></article>)}</div> : <p className="muted-copy">No bookings found. Start with a service request.</p>}</section>
        <div className="customer-grid lower-grid"><section className="glass-panel" id="customer-history"><div className="panel-heading"><div><span className="eyebrow">MAINTENANCE LOG</span><h2>Recent history</h2></div><History size={20} className="panel-icon" /></div>{(data?.service_history || []).length ? data.service_history.map((item) => <div className="history-row" key={item.history_id}><div className="history-icon"><Wrench size={17} /></div><div><strong>{item.booking?.service?.service_name || item.service_summary || 'Service visit'}</strong><span>{item.vehicle?.registration_number || '-'} &middot; {formatDate(item.service_date)}</span></div><StatusPill status={item.final_status || 'COMPLETED'} /></div>) : <p className="muted-copy">Completed service history will appear here.</p>}</section>
          <section className="glass-panel" id="customer-invoices"><div className="panel-heading"><div><span className="eyebrow">BILLING</span><h2>Recent invoices</h2></div><FileText size={20} className="panel-icon" /></div>{(data?.invoices || []).length ? data.invoices.map((invoice) => <div className="invoice-row" key={invoice.invoice_id}><div><strong>INV-{String(invoice.invoice_id).padStart(4, '0')}</strong><span>{invoice.booking?.service?.service_name || 'Service'} &middot; {formatDate(invoice.invoice_date)}</span></div><b>{money(invoice.total_amount)}</b><div style={{ display: 'flex', gap: 8, alignItems: 'center' }}><button onClick={() => printInvoice(invoice)} aria-label="Print invoice"><FileText size={15} /></button>{invoice.invoice_status === 'UNPAID' ? <button disabled={paying === invoice.invoice_id} onClick={() => payInvoice(invoice.invoice_id)}>{paying === invoice.invoice_id ? 'Processing...' : 'Pay Now'}</button> : <span>PAID</span>}</div></div>) : <p className="muted-copy">Your invoices will appear after a service is billed.</p>}</section></div>
        <section className="reminder-panel"><div className="reminder-orb"><Sparkles size={23} /></div><div><span className="eyebrow">SERVICE REMINDER</span><h2>{nextReminder ? `${nextReminder.vehicle?.make || 'Your vehicle'} may be due for attention.` : 'No upcoming service reminders.'}</h2><p>{nextReminder ? `Your ${nextReminder.vehicle?.model || 'vehicle'} has a ${nextReminder.service?.service_name || 'service'} request for ${formatDate(nextReminder.preferred_date)}.` : 'We will surface your next service moment here.'}</p></div>{nextReminder && <button className="neon-button" onClick={openBooking}>Book service <ChevronRight size={16} /></button>}</section>
      </div>
    </main>
    {modal?.type === 'vehicle' && <Modal title={modal.editing ? 'Edit vehicle' : 'Add a vehicle'} onClose={() => setModal(null)}><form className="customer-form" onSubmit={submit}><label>Registration number<input required value={form.registration_number || ''} onChange={(event) => setForm({ ...form, registration_number: event.target.value })} /></label><div className="form-row"><label>Make<input required value={form.make || ''} onChange={(event) => setForm({ ...form, make: event.target.value })} /></label><label>Model<input required value={form.model || ''} onChange={(event) => setForm({ ...form, model: event.target.value })} /></label></div><label>Manufacture year<input type="number" min="1900" max="2100" value={form.manufacture_year || ''} onChange={(event) => setForm({ ...form, manufacture_year: event.target.value })} /></label>{error && <div className="customer-alert">{error}</div>}<button disabled={saving} className="neon-button" type="submit">{saving ? 'Saving...' : 'Save vehicle'}</button></form></Modal>}
    {modal?.type === 'booking' && <Modal title="Book a service" onClose={() => setModal(null)}><form className="customer-form" onSubmit={submit}><label>My vehicle<select required value={form.vehicle_id || ''} onChange={(event) => setForm({ ...form, vehicle_id: event.target.value })}><option value="">Choose a vehicle</option>{vehicles.map((vehicle) => <option value={vehicle.vehicle_id} key={vehicle.vehicle_id}>{vehicle.make} {vehicle.model} · {vehicle.registration_number}</option>)}</select></label><label>Service type<select required value={form.service_type_id || ''} onChange={(event) => setForm({ ...form, service_type_id: event.target.value })}><option value="">Choose a service</option>{(data?.services || []).map((service) => <option value={service.service_type_id} key={service.service_type_id}>{service.service_name} · {money(service.price)}</option>)}</select></label><div className="form-row"><label>Preferred date<input required type="date" value={form.preferred_date || ''} onChange={(event) => setForm({ ...form, preferred_date: event.target.value })} /></label><label>Preferred time<input type="time" value={form.preferred_time || ''} onChange={(event) => setForm({ ...form, preferred_time: event.target.value })} /></label></div><label>Notes<textarea rows="3" value={form.customer_notes || ''} onChange={(event) => setForm({ ...form, customer_notes: event.target.value })} /></label>{error && <div className="customer-alert">{error}</div>}<button disabled={saving} className="neon-button" type="submit">{saving ? 'Requesting...' : 'Request service'}</button></form></Modal>}
  </div>;
}

export default CustomerDashboard;

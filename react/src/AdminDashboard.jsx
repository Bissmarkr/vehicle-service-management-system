import { useEffect, useState } from 'react';
import {
  Activity, ArrowUpRight, CalendarDays, CarFront, CheckCircle2, Clock3,
  CreditCard, DollarSign, Gauge, MoreHorizontal, Users, Wrench,
} from 'lucide-react';
import api from './services/api';

const statusLabels = {
  PENDING: 'Pending',
  CONFIRMED: 'Confirmed',
  ASSIGNED: 'Assigned',
  IN_PROGRESS: 'In progress',
  COMPLETED: 'Completed',
  CANCELLED: 'Cancelled',
};

const money = (value) => `LKR ${Number(value || 0).toLocaleString()}`;
const formatDate = (value) => value ? new Date(value).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '-';
const today = () => new Date().toISOString().slice(0, 10);

function StatusBadge({ value }) {
  return <span className={`admin-status status-${String(value || 'PENDING').toLowerCase()}`}><i />{statusLabels[value] || String(value || 'Pending').replaceAll('_', ' ')}</span>;
}

function MetricCard({ icon: Icon, label, value, detail, tone = 'mint', onClick }) {
  return <button className={`admin-metric ${tone}`} onClick={onClick} type="button"><span className="admin-metric-icon"><Icon size={17} /></span><span className="admin-metric-label">{label}</span><strong>{value}</strong><small>{detail}</small><span className="admin-metric-arrow"><ArrowUpRight size={15} /></span><span className="admin-metric-spark"><i /><i /><i /><i /><i /></span></button>;
}

export default function AdminDashboard({ onNavigate, auth }) {
  const [dashboard, setDashboard] = useState(null);
  const [bookings, setBookings] = useState([]);
  const [mechanics, setMechanics] = useState([]);
  const [services, setServices] = useState({});
  const [revenue, setRevenue] = useState({});
  const [paymentRequests, setPaymentRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const [dashboardResponse, bookingsResponse, mechanicsResponse, servicesResponse, revenueResponse, paymentsResponse] = await Promise.all([
        api.get('/dashboard'),
        api.get('/bookings'),
        api.get('/mechanics'),
        api.get('/reports/services'),
        api.get('/reports/revenue'),
        api.get('/admin/payment-requests'),
      ]);
      setDashboard(dashboardResponse.data?.data || {});
      setBookings(bookingsResponse.data?.data || []);
      setMechanics(mechanicsResponse.data?.data || []);
      setServices(servicesResponse.data?.data || {});
      setRevenue(revenueResponse.data?.data || {});
      setPaymentRequests(paymentsResponse.data?.data || []);
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to load the admin dashboard.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const activeBookings = bookings.filter((booking) => ['PENDING', 'CONFIRMED', 'ASSIGNED', 'IN_PROGRESS'].includes(booking.booking_status));
  const todayBookings = bookings.filter((booking) => booking.preferred_date === today());
  const inService = bookings.filter((booking) => booking.booking_status === 'IN_PROGRESS');
  const completedToday = bookings.filter((booking) => booking.booking_status === 'COMPLETED' && booking.updated_at?.slice?.(0, 10) === today());
  const pendingRequests = paymentRequests.filter((payment) => payment.admin_status === 'PENDING_APPROVAL' || (payment.payment_type === 'remaining' && payment.admin_status === 'PENDING_REVIEW'));
  const metrics = [
    [Users, 'Total customers', dashboard?.customers, 'Registered accounts', 'mint', 'customers'],
    [CarFront, 'Total vehicles', dashboard?.vehicles, 'Vehicles in the garage', 'cyan', 'vehicles'],
    [CalendarDays, 'Active bookings', activeBookings.length, 'Requests in motion', 'blue', 'bookings'],
    [Wrench, 'Total mechanics', dashboard?.mechanics, 'Service team roster', 'violet', 'mechanics'],
    [Clock3, 'Pending services', dashboard?.pending_bookings, 'Awaiting action', 'amber', 'bookings'],
    [CreditCard, 'Pending payments', pendingRequests.length, 'Approval requests', 'rose', 'payment-requests'],
    [DollarSign, 'Revenue collected', money(revenue.total), 'From completed payments', 'mint', 'payments'],
    [CheckCircle2, 'Completed services', dashboard?.completed_services, 'Jobs closed', 'cyan', 'bookings'],
  ];

  return <div className="admin-dashboard-page">
    <section className="admin-hero"><div><span className="admin-eyebrow"><Gauge size={13} /> SERVICE CENTER CONTROL</span><h1>Welcome back, <em>{auth?.name || 'Administrator'}</em></h1><p>Monitor and manage your vehicle service center from one place.</p><div className="admin-hero-actions"><button className="admin-primary-button" onClick={() => onNavigate('bookings')} type="button">Review bookings <ArrowUpRight size={16} /></button><button className="admin-ghost-button" onClick={() => onNavigate('payment-requests')} type="button">Payment requests <span>{pendingRequests.length}</span></button></div></div><div className="admin-hero-orbit"><div /><div /><CarFront size={60} strokeWidth={1.1} /></div></section>
    {error && <div className="admin-alert">{error}<button type="button" onClick={() => setError('')}>Dismiss</button></div>}
    <div className="admin-section-heading"><div><span className="admin-eyebrow">LIVE SNAPSHOT</span><h2>Center statistics</h2></div><button className="admin-refresh" onClick={load} type="button">{loading ? 'Refreshing...' : 'Refresh data'}</button></div>
    <section className="admin-metrics">{metrics.map(([Icon, label, value, detail, tone, target]) => <MetricCard key={label} icon={Icon} label={label} value={value ?? '-'} detail={detail} tone={tone} onClick={() => onNavigate(target)} />)}</section>
    <section className="admin-overview-grid"><div className="admin-panel overview-panel"><div className="admin-panel-heading"><div><span className="admin-eyebrow">SERVICE CENTER OVERVIEW</span><h2>Today at a glance</h2></div><Activity size={19} /></div><div className="admin-overview-cards"><button onClick={() => onNavigate('bookings')} type="button"><CalendarDays size={17} /><strong>{todayBookings.length}</strong><span>Today's bookings</span></button><button onClick={() => onNavigate('bookings')} type="button"><CarFront size={17} /><strong>{inService.length}</strong><span>Vehicles in service</span></button><button onClick={() => onNavigate('mechanics')} type="button"><Wrench size={17} /><strong>{mechanics.length}</strong><span>Mechanics on roster</span></button><button onClick={() => onNavigate('bookings')} type="button"><CheckCircle2 size={17} /><strong>{completedToday.length}</strong><span>Completed today</span></button><button onClick={() => onNavigate('payment-requests')} type="button"><CreditCard size={17} /><strong>{pendingRequests.length}</strong><span>Pending approvals</span></button><button onClick={() => onNavigate('invoices')} type="button"><DollarSign size={17} /><strong>{revenue.unpaid_invoices ?? '-'}</strong><span>Unpaid invoices</span></button></div></div><div className="admin-panel service-mix-panel"><div className="admin-panel-heading"><div><span className="admin-eyebrow">BOOKING MIX</span><h2>Service pulse</h2></div><Gauge size={19} /></div><div className="service-bars">{Object.entries(services).length ? Object.entries(services).map(([status, total]) => <div className="service-bar-row" key={status}><span>{statusLabels[status] || status}</span><div><i style={{ width: `${Math.min(100, Number(total) / Math.max(...Object.values(services).map(Number), 1) * 100)}%` }} /></div><b>{total}</b></div>) : <p className="admin-muted">No booking activity available.</p>}</div><div className="admin-total-line"><span>Revenue collected</span><strong>{money(revenue.total)}</strong></div></div></section>
    <section className="admin-panel recent-bookings"><div className="admin-panel-heading"><div><span className="admin-eyebrow">WORK QUEUE</span><h2>Recent bookings</h2></div><button className="admin-text-button" onClick={() => onNavigate('bookings')} type="button">View all <ArrowUpRight size={15} /></button></div><div className="admin-booking-table"><div className="admin-table-head"><span>Booking</span><span>Customer / vehicle</span><span>Service</span><span>Date</span><span>Status</span><span>Action</span></div>{bookings.slice(0, 8).map((booking) => <div className="admin-table-row" key={booking.booking_id}><strong>#{booking.booking_id}</strong><span><b>{booking.customer?.full_name || '-'}</b><small>{booking.vehicle?.registration_number || `${booking.vehicle?.make || ''} ${booking.vehicle?.model || ''}`}</small></span><span>{booking.service?.service_name || '-'}</span><span>{formatDate(booking.preferred_date)}<small>{booking.preferred_time || 'Time not set'}</small></span><StatusBadge value={booking.booking_status} /><button className="admin-icon-button" onClick={() => onNavigate('bookings')} title="Open booking" type="button"><MoreHorizontal size={17} /></button></div>)}{!loading && !bookings.length && <p className="admin-muted">No bookings found.</p>}</div></section>
    <section className="admin-bottom-grid"><div className="admin-panel"><div className="admin-panel-heading"><div><span className="admin-eyebrow">PAYMENT DESK</span><h2>Payment requests</h2></div><CreditCard size={19} /></div>{pendingRequests.slice(0, 4).map((payment) => <button className="admin-payment-row" key={payment.payment_id} onClick={() => onNavigate('payment-requests')} type="button"><span className="admin-payment-avatar">{payment.customer?.full_name?.slice(0, 1) || 'P'}</span><span><b>{payment.payment_type === 'remaining' ? 'Remaining payment completed' : payment.customer?.full_name || 'Customer payment'}</b><small>Booking #{payment.booking_id} · {money(payment.payment_amount)}</small></span><Clock3 size={16} /></button>)}{!pendingRequests.length && <p className="admin-muted">No payment requests waiting.</p>}</div><div className="admin-panel"><div className="admin-panel-heading"><div><span className="admin-eyebrow">OPERATIONS</span><h2>Quick actions</h2></div><ArrowUpRight size={19} /></div><div className="admin-quick-actions"><button onClick={() => onNavigate('customers')} type="button"><Users size={17} />Add customer</button><button onClick={() => onNavigate('vehicles')} type="button"><CarFront size={17} />Manage vehicles</button><button onClick={() => onNavigate('services')} type="button"><Wrench size={17} />Service catalog</button><button onClick={() => onNavigate('reports')} type="button"><Activity size={17} />View reports</button></div></div></section>
  </div>;
}

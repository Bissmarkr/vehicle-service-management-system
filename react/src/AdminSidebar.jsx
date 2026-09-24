import {
  Bell, CalendarDays, CarFront, ChartNoAxesCombined, CreditCard, FileText,
  Gauge, LogOut, Package, Settings, Users, Wrench, X,
} from 'lucide-react';

const groups = [
  { label: 'Workspace', items: [['dashboard', 'Dashboard', Gauge]] },
  { label: 'Management', items: [['customers', 'Customers', Users], ['vehicles', 'Vehicles', CarFront], ['services', 'Services', Wrench], ['mechanics', 'Mechanics', Wrench], ['spare-parts', 'Spare parts', Package]] },
  { label: 'Operations', items: [['bookings', 'All bookings', CalendarDays], ['invoices', 'Invoices', FileText], ['payments', 'Payments', CreditCard], ['payment-requests', 'Payment requests', Bell]] },
  { label: 'Insights', items: [['reports', 'Reports', ChartNoAxesCombined]] },
];

export default function AdminSidebar({ page, onNavigate, onLogout, pendingRequests, mobileOpen, onClose }) {
  return <div className={mobileOpen ? 'admin-sidebar-inner open' : 'admin-sidebar-inner'}>
    <div className="admin-brand"><div className="admin-brand-mark">VS</div><div><strong>VSMS</strong><span>Service operations</span></div><button className="admin-sidebar-close" onClick={onClose} type="button"><X size={18} /></button></div>
    <div className="admin-sidebar-scroll">{groups.map((group) => <div className="admin-nav-group" key={group.label}><span className="admin-nav-label">{group.label}</span>{group.items.map(([key, label, Icon]) => <button className={page === key ? 'admin-nav-item active' : 'admin-nav-item'} key={key} onClick={() => { onNavigate(key); onClose?.(); }} type="button"><Icon size={16} /><span>{label}</span>{key === 'payment-requests' && pendingRequests > 0 && <b>{pendingRequests}</b>}</button>)}</div>)}</div>
    <div className="admin-sidebar-footer"><button className="admin-nav-item" onClick={() => onNavigate('profile')} type="button"><Settings size={16} /><span>Settings</span></button><button className="admin-nav-item" onClick={onLogout} type="button"><LogOut size={16} /><span>Logout</span></button></div>
  </div>;
}

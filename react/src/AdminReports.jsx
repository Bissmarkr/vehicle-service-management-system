import { useEffect, useState } from 'react';
import { Activity, BarChart3, CheckCircle2, DollarSign, FileText } from 'lucide-react';
import api from './services/api';

const money = (value) => `LKR ${Number(value || 0).toLocaleString()}`;

export default function AdminReports() {
  const [summary, setSummary] = useState(null);
  const [revenue, setRevenue] = useState(null);
  const [services, setServices] = useState({});
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.all([api.get('/reports/dashboard'), api.get('/reports/revenue'), api.get('/reports/services')])
      .then(([summaryResponse, revenueResponse, servicesResponse]) => { setSummary(summaryResponse.data?.data || {}); setRevenue(revenueResponse.data?.data || {}); setServices(servicesResponse.data?.data || {}); })
      .catch((requestError) => setError(requestError.response?.data?.message || 'Unable to load reports.'));
  }, []);

  const cards = [[DollarSign, 'Revenue collected', money(revenue?.total), 'Completed payments'], [BarChart3, 'Bookings', summary?.bookings ?? '-', 'All service bookings'], [CheckCircle2, 'Completed services', summary?.completed_services ?? '-', 'Closed jobs'], [FileText, 'Unpaid invoices', revenue?.unpaid_invoices ?? '-', 'Needs follow-up']];
  const max = Math.max(...Object.values(services).map(Number), 1);
  return <div className="admin-report-page"><div className="admin-section-heading"><div><span className="admin-eyebrow"><Activity size={13} /> REPORTING</span><h1>Service center reports</h1><p>Live summaries from Laravel and MySQL.</p></div></div>{error && <div className="admin-alert">{error}</div>}<div className="admin-report-cards">{cards.map(([Icon, label, value, detail]) => <div className="admin-report-card" key={label}><Icon size={18} /><span>{label}</span><strong>{value}</strong><small>{detail}</small></div>)}</div><div className="admin-panel report-chart"><div className="admin-panel-heading"><div><span className="admin-eyebrow">BOOKING STATUS</span><h2>Service pipeline</h2></div><BarChart3 size={19} /></div>{Object.entries(services).map(([status, total]) => <div className="report-bar-row" key={status}><span>{status.replaceAll('_', ' ')}</span><div><i style={{ width: `${Number(total) / max * 100}%` }} /></div><b>{total}</b></div>)}</div></div>;
}

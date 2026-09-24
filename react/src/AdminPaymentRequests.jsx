import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from './services/api';

const money = (value) => `LKR ${Number(value || 0).toLocaleString()}`;
const formatDate = (value) => value ? new Date(value).toLocaleString() : '-';

function requestStatus(request) {
  if (request.payment_type === 'remaining' && request.admin_status === 'READ') return 'Read';
  if (request.payment_type === 'remaining' && ['PAID', 'COMPLETED'].includes(String(request.payment_status).toUpperCase())) return 'New request';
  if (request.admin_status === 'APPROVED') return 'Approved';
  if (request.admin_status === 'REJECTED') return 'Rejected';
  return 'Pending approval';
}

export default function AdminPaymentRequests() {
  const navigate = useNavigate();
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const response = await api.get('/admin/payment-requests');
      setRequests(response.data?.data || []);
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to load payment requests.');
    } finally {
      setLoading(false);
    }
  };

  // The initial request synchronizes this view with the external admin API.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { load(); }, []);

  const openRequest = async (request) => {
    setSelected(request);
    if (request.payment_type !== 'remaining' || request.admin_status !== 'PENDING_REVIEW') return;
    try {
      await api.post(`/admin/payment-requests/${request.payment_id}/read`);
      setRequests((current) => current.map((item) => item.payment_id === request.payment_id ? { ...item, admin_status: 'READ' } : item));
      setSelected({ ...request, admin_status: 'READ' });
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to mark payment request as read.');
    }
  };

  const approve = async (request) => {
    try {
      await api.post(`/admin/payment-requests/${request.payment_id}/approve`);
      await load();
      setSelected(null);
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to approve payment.');
    }
  };

  const reject = async (request) => {
    const rejectionReason = window.prompt('Reason for rejecting this payment:');
    if (!rejectionReason?.trim()) return;
    try {
      await api.post(`/admin/payment-requests/${request.payment_id}/reject`, { rejection_reason: rejectionReason.trim() });
      await load();
      setSelected(null);
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to reject payment.');
    }
  };

  const newRequestCount = requests.filter((request) => ['PENDING_APPROVAL', 'PENDING_REVIEW'].includes(request.admin_status)).length;

  return <div className="card table-card">
    <div className="section-heading"><div><h3>Payment Requests</h3><small>New requests: {newRequestCount}</small></div><button className="btn btn-outline" onClick={load}>Refresh</button></div>
    {error && <div className="alert error">{error}</div>}
    {loading ? <p>Loading payment requests...</p> : <div className="admin-request-table"><table><thead><tr><th>Payment</th><th>Type</th><th>Booking / invoice</th><th>Customer</th><th>Vehicle</th><th>Service</th><th>Total / paid</th><th>Balance</th><th>Stripe / date</th><th>Status</th><th>Actions</th></tr></thead><tbody>{requests.length ? requests.map((request) => <tr key={request.payment_id}><td>#{request.payment_id}</td><td>{request.payment_type?.toUpperCase()}</td><td>#{request.booking_id}<br /><small>INV-{request.invoice_id}</small></td><td>{request.customer?.full_name}<br /><small>{request.customer?.email}</small></td><td>{request.vehicle?.registration_number || '-'}</td><td>{request.service?.service_name || '-'}</td><td>{money(request.total_invoice_amount)}<br /><small>Paid {money(request.total_paid)}</small><br /><small>{request.payment_type === 'remaining' ? `Remaining ${money(request.payment_amount)}` : `Advance ${money(request.payment_amount)}`}</small></td><td>{money(request.remaining_amount)}</td><td><small>{request.stripe_payment_id || request.stripe_session_id || '-'}</small><br />{formatDate(request.payment_date)}</td><td>{request.payment_status}<br />{requestStatus(request)}{request.rejection_reason && <><br /><small>{request.rejection_reason}</small></>}</td><td><div className="admin-request-actions"><button className="btn btn-outline" onClick={() => openRequest(request)}>View Details</button><button className="btn btn-outline" onClick={() => navigate('bookings')}>View Booking</button><button className="btn btn-outline" onClick={() => navigate('invoices')}>View Invoice</button>{request.payment_type === 'advance' && request.admin_status === 'PENDING_APPROVAL' && <><button className="btn btn-primary" onClick={() => approve(request)}>Approve Payment</button><button className="btn btn-danger" onClick={() => reject(request)}>Reject Payment</button></>}</div></td></tr>) : <tr><td colSpan="11">No payment requests found.</td></tr>}</tbody></table></div>}
    {selected && <div className="card admin-request-details"><h3>Payment #{selected.payment_id}</h3><p>Booking #{selected.booking_id} · Invoice #{selected.invoice_id}</p><p>Stripe payment: {selected.stripe_payment_id || '-'}</p><p>Stripe session: {selected.stripe_session_id || '-'}</p><p>Payment type: {selected.payment_type || '-'}</p><p>Payment status: {selected.payment_status}</p><p>Total paid: {money(selected.total_paid)} · Balance: {money(selected.remaining_amount)}</p><p>Admin status: {requestStatus(selected)}</p><button className="btn btn-outline" onClick={() => setSelected(null)}>Close Details</button></div>}
  </div>;
}

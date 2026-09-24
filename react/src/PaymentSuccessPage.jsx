import { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import api from './services/api';

export default function PaymentSuccessPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const sessionId = searchParams.get('session_id');
  const [payment, setPayment] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchStatus = async () => {
      if (!sessionId) {
        setLoading(false);
        return;
      }

      try {
        const response = await api.get('/payments/status', {
          params: { session_id: sessionId },
        });
        setPayment(response.data);
      } catch {
        setPayment({ success: false, message: 'Unable to confirm payment status.' });
      } finally {
        setLoading(false);
      }
    };

    fetchStatus();
  }, [sessionId]);

  if (loading) {
    return <div style={{ padding: 40 }}><h2>Verifying payment...</h2></div>;
  }

  const isPaid = payment?.paid === true || ['PAID', 'COMPLETED'].includes(String(payment?.payment_status || payment?.status || '').toUpperCase());
  const adminStatus = payment?.admin_status || payment?.payment?.admin_status;
  const isRemaining = payment?.payment_type === 'remaining';
  const totalPaid = Number(payment?.total_paid || 0);

  return (
    <div style={{ maxWidth: 760, margin: '40px auto', padding: 24, fontFamily: 'sans-serif' }}>
      <h2>{isPaid ? (isRemaining ? 'Remaining Payment Completed' : 'Payment Successful') : 'Payment Status'}</h2>
      {isPaid ? (
        <><h3>{isRemaining ? '✓ Remaining Payment Completed' : adminStatus === 'APPROVED' ? 'Advance Payment Approved ✓' : adminStatus === 'REJECTED' ? 'Payment Rejected' : 'Payment Submitted ✓'}</h3><p>Booking ID: #{payment?.booking?.booking_id || payment?.booking_id}</p><p>Service: {payment?.booking?.service?.service_name}</p>{isRemaining ? <><p>Total: LKR {Number(payment?.invoice?.total_amount || 0).toLocaleString()}</p><p>Advance Paid: LKR {Number(payment?.invoice?.advance_amount || payment?.advance_amount || 0).toLocaleString()}</p><p>Remaining Paid: LKR {Number(payment?.payment?.payment_amount || 0).toLocaleString()}</p><p>Total Paid: LKR {totalPaid.toLocaleString()}</p><p>Payment Status: ✓ FULLY PAID</p><p>Invoice Status: ✓ {payment?.invoice_status || 'PAID'}</p></> : <><p>Advance Amount: LKR {Number(payment?.advance_amount || payment?.invoice?.advance_amount || 0).toLocaleString()}</p><p>Payment Date: {payment?.payment?.payment_date ? new Date(payment.payment.payment_date).toLocaleString() : 'Confirmed by Stripe'}</p><p>Payment Method: {payment?.payment?.payment_method || 'CARD'}</p><p>Payment Status: {payment?.payment_status || 'COMPLETED'}</p><p>{adminStatus === 'APPROVED' ? 'Advance Payment Approved ✓' : adminStatus === 'REJECTED' ? `Payment Rejected: ${payment?.rejection_reason || payment?.payment?.rejection_reason || 'Please contact the service center.'}` : 'Waiting for Admin Approval'}</p><p>Booking Status: {payment?.booking?.booking_status || 'PENDING'}</p></>}</>
      ) : (
        <p>Payment could not be confirmed yet. Please check your invoice or try again.</p>
      )}
      <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginTop: 24 }}>
        <button onClick={() => navigate('/customer/dashboard')}>Back to Dashboard</button>
        <button onClick={() => navigate('/customer/dashboard')}>View Booking</button>
        <Link to="/customer/dashboard">View Invoice</Link>
      </div>
    </div>
  );
}

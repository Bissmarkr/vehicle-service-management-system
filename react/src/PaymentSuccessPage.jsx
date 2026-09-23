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
      } catch (error) {
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

  const isPaid = payment?.paid === true;

  return (
    <div style={{ maxWidth: 760, margin: '40px auto', padding: 24, fontFamily: 'sans-serif' }}>
      <h2>{isPaid ? 'Payment Successful' : 'Payment Status'}</h2>
      {isPaid ? (
        <>
          <p>Invoice Number: {payment?.invoice_id ?? 'N/A'}</p>
          <p>Payment Status: {payment?.status ?? 'PAID'}</p>
          <p>Stripe checkout session has been verified by the backend.</p>
        </>
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

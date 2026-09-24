import { Link, useNavigate, useSearchParams } from 'react-router-dom';

export default function PaymentCancelPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const bookingId = searchParams.get('booking_id');
  const isRemaining = searchParams.get('payment_type') === 'remaining';

  return (
    <div style={{ maxWidth: 760, margin: '40px auto', padding: 24, fontFamily: 'sans-serif' }}>
      <h2>Payment Cancelled</h2>
      <p>{isRemaining ? 'Your remaining payment has not been completed.' : 'Your advance payment has not been completed.'}</p>
      <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginTop: 24 }}>
        <button onClick={() => navigate(isRemaining && bookingId ? `/customer/pending-payment/${bookingId}` : '/customer/dashboard')}>Try Again</button>
        <button onClick={() => navigate('/customer/dashboard')}>View Booking</button>
        <Link to="/customer/dashboard">Back to Dashboard</Link>
      </div>
    </div>
  );
}

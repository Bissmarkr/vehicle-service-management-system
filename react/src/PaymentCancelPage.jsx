import { Link, useNavigate } from 'react-router-dom';

export default function PaymentCancelPage() {
  const navigate = useNavigate();

  return (
    <div style={{ maxWidth: 760, margin: '40px auto', padding: 24, fontFamily: 'sans-serif' }}>
      <h2>Payment Cancelled</h2>
      <p>Your payment was not completed.</p>
      <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginTop: 24 }}>
        <button onClick={() => navigate('/customer/dashboard')}>Try Again</button>
        <button onClick={() => navigate('/customer/dashboard')}>View Booking</button>
        <Link to="/customer/dashboard">Back to Dashboard</Link>
      </div>
    </div>
  );
}

import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api from './services/api';
import { createCheckoutSession } from './services/paymentService';

const money = (value) => `LKR ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

export default function BookingConfirmationPage() {
  const { bookingId } = useParams();
  const navigate = useNavigate();
  const [booking, setBooking] = useState(null);
  const [loading, setLoading] = useState(true);
  const [paying, setPaying] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get(`/bookings/${bookingId}`)
      .then((response) => setBooking(response.data.data))
      .catch((requestError) => setError(requestError.response?.data?.message || 'Unable to load this booking.'))
      .finally(() => setLoading(false));
  }, [bookingId]);

  const payAdvance = async () => {
    if (!booking?.invoice?.invoice_id) { setError('Invoice not found for this booking.'); return; }
    setPaying(true); setError('');
    try {
      const response = await createCheckoutSession(booking.invoice.invoice_id);
      window.location.href = response.checkout_url;
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to start advance payment.');
      setPaying(false);
    }
  };

  if (loading) return <main style={{ maxWidth: 760, margin: '48px auto', padding: 24 }}><h2>Loading booking...</h2></main>;
  if (!booking) return <main style={{ maxWidth: 760, margin: '48px auto', padding: 24 }}><h2>Booking unavailable</h2><p>{error}</p></main>;

  const invoice = booking.invoice;
  return <main style={{ maxWidth: 760, margin: '48px auto', padding: 24, fontFamily: 'sans-serif' }}>
    <h1>Booking Confirmation</h1>
    {error && <p role="alert">{error}</p>}
    <p>Booking ID: #{booking.booking_id}</p>
    <p>Vehicle: {booking.vehicle?.make} {booking.vehicle?.model}</p>
    <p>Service: {booking.service?.service_name}</p>
    <p>Booking Date: {booking.preferred_date}</p>
    <p>Booking Time: {booking.preferred_time || 'Not specified'}</p>
    <p>Service Total: {money(invoice?.total_amount)}</p>
    <p>Advance Payment: {money(invoice?.advance_amount)}</p>
    <p>Remaining Amount: {money(invoice?.remaining_amount)}</p>
    <p>Payment Status: {invoice?.advance_payment_status || 'PENDING'}</p>
    {invoice?.advance_payment_status === 'PAID' ? <p>Advance payment completed. Booking status: {booking.booking_status}</p> : <button disabled={paying} onClick={payAdvance}>{paying ? 'Opening Stripe...' : 'Confirm Booking & Pay Advance'}</button>}
    <button onClick={() => navigate('/customer/dashboard')}>Back to Dashboard</button>
  </main>;
}

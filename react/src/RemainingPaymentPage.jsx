import { useEffect, useState } from 'react';
import { ArrowLeft, CarFront, Check, CreditCard, FileText, LoaderCircle, ShieldCheck } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import api from './services/api';
import { createRemainingCheckoutSession } from './services/paymentService';

const money = (value) => `LKR ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

function errorMessage(error) {
  return error.response?.data?.message || 'Unable to load the remaining payment.';
}

export default function RemainingPaymentPage() {
  const { bookingId } = useParams();
  const navigate = useNavigate();
  const [booking, setBooking] = useState(null);
  const [loading, setLoading] = useState(true);
  const [paying, setPaying] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    api.get(`/bookings/${bookingId}`)
      .then((response) => setBooking(response.data.data))
      .catch((requestError) => setError(errorMessage(requestError)))
      .finally(() => setLoading(false));
  }, [bookingId]);

  const payRemaining = async () => {
    setPaying(true);
    setError('');
    try {
      const response = await createRemainingCheckoutSession(bookingId);
      if (!response.checkout_url) throw new Error('Missing checkout URL');
      window.location.href = response.checkout_url;
    } catch (requestError) {
      setError(requestError.response?.data?.message || 'Unable to start the remaining payment.');
      setPaying(false);
    }
  };

  if (loading) return <main className="payment-page"><div className="payment-card"><LoaderCircle className="payment-loader" size={24} /><p>Loading payment summary...</p></div></main>;
  if (!booking) return <main className="payment-page"><div className="payment-card"><h1>Payment unavailable</h1><p className="payment-error">{error}</p><button className="payment-secondary" onClick={() => navigate('/customer/dashboard')}><ArrowLeft size={16} /> Back to dashboard</button></div></main>;

  const invoice = booking.invoice || {};
  const remaining = Number(booking.remaining_amount ?? invoice.remaining_amount ?? 0);
  const totalPaid = Number(invoice.total_amount || 0) - remaining;
  const remainingPaid = String(booking.remaining_payment_status || invoice.remaining_payment_status || '').toUpperCase() === 'PAID' || remaining <= 0;

  return <main className="payment-page"><div className="payment-card"><div className="payment-card-head"><div><span className="payment-eyebrow">REMAINING PAYMENT</span><h1>{remainingPaid ? 'Payment complete' : 'Settle your balance'}</h1><p>{remainingPaid ? 'This invoice has been fully paid.' : 'Your advance payment is recorded. This checkout covers the remaining balance only.'}</p></div><span className="payment-card-icon"><CreditCard size={21} /></span></div>{error && <div className="payment-error" role="alert">{error}</div>}<div className="payment-summary"><div><span>Booking ID</span><strong>#{booking.booking_id}</strong></div><div><span>Vehicle</span><strong><CarFront size={15} /> {booking.vehicle?.make} {booking.vehicle?.model}</strong></div><div><span>Service</span><strong>{booking.service?.service_name || 'Vehicle service'}</strong></div><div><span>Service total</span><strong>{money(invoice.total_amount)}</strong></div><div><span>Advance paid</span><strong className="payment-positive"><Check size={15} /> {money(invoice.advance_amount)}</strong></div><div><span>Total paid</span><strong>{money(totalPaid)}</strong></div><div className="payment-summary-total"><span>Remaining amount</span><strong>{money(remaining)}</strong></div></div><div className="payment-state-line"><ShieldCheck size={17} /><span>Advance status <b>PAID</b></span><i /><span>Remaining status <b>{remainingPaid ? 'PAID' : 'PENDING'}</b></span></div>{!remainingPaid && <button className="payment-primary" disabled={paying} onClick={payRemaining}>{paying ? <><LoaderCircle className="payment-loader" size={17} /> Opening Stripe...</> : <>Pay remaining amount <CreditCard size={17} /></>}</button>}<div className="payment-actions"><button className="payment-secondary" onClick={() => navigate('/customer/dashboard')}><ArrowLeft size={16} /> My dashboard</button><button className="payment-secondary" onClick={() => navigate('/customer/dashboard')}><FileText size={16} /> View invoices</button></div></div></main>;
}

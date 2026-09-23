import api from './api';

export const createCheckoutSession = async (invoiceId) => {
  const response = await api.post('/payments/create-checkout-session', {
    invoice_id: invoiceId,
  });

  return response.data;
};

export const getPaymentStatus = async (sessionId) => {
  const response = await api.get('/payments/status', {
    params: { session_id: sessionId },
  });

  return response.data;
};

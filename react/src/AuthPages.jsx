import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  AlertCircle, ArrowRight, CarFront, Check, Eye, EyeOff, LockKeyhole,
  LogIn, Mail, Phone, ShieldCheck, User, UserPlus, Wrench,
} from 'lucide-react';
import api from './services/api';
import { useAuth } from './context/AuthContext';

function apiError(error) {
  if (!error.response) return 'Unable to connect to the Laravel server.';
  if (error.response.status === 401) return 'Invalid email or password.';
  if (error.response.status === 422) return Object.values(error.response.data?.errors || {}).flat().join(' ') || error.response.data?.message || 'Please correct the form.';
  if (error.response.status >= 500) return 'Laravel returned a server error. Please try again.';
  return error.response.data?.message || 'The operation failed.';
}

function AuthBrand({ register = false }) {
  return <section className="auth-brand-panel">
    <div className="auth-brand-top"><div className="auth-logo"><CarFront size={21} /></div><div><strong>VSMS</strong><span>Vehicle service</span></div></div>
    <div className="auth-brand-copy"><span className="auth-kicker"><Wrench size={14} /> Workshop intelligence</span><h1>Vehicle Service <em>Management System.</em></h1><p>Smart vehicle service management for modern workshops.</p></div>
    <div className="auth-vehicle-art" aria-hidden="true"><div className="auth-art-glow" /><div className="auth-art-ring ring-a" /><div className="auth-art-ring ring-b" /><CarFront size={126} strokeWidth={1} /><span>VSMS / SERVICE CENTER</span></div>
    <div className="auth-brand-footer"><ShieldCheck size={16} /><span>Connected service operations</span><i /><span>{register ? 'Built for every workshop' : 'Secure access for your team'}</span></div>
  </section>;
}

function PasswordField({ label, value, onChange, visible, onToggle, autoComplete, error }) {
  return <label className="auth-field"><span>{label}</span><div className={error ? 'auth-input-wrap has-error' : 'auth-input-wrap'}><LockKeyhole size={17} /><input required type={visible ? 'text' : 'password'} value={value} onChange={onChange} autoComplete={autoComplete} /><button type="button" className="auth-visibility" onClick={onToggle} aria-label={visible ? `Hide ${label.toLowerCase()}` : `Show ${label.toLowerCase()}`}>{visible ? <EyeOff size={17} /> : <Eye size={17} />}</button></div></label>;
}

function AuthError({ message }) {
  return message && <div className="auth-error" role="alert"><AlertCircle size={17} /><span>{message}</span></div>;
}

function AuthFooter({ register, onNavigate }) {
  return <div className="auth-switch">{register ? 'Already have an account?' : "Don't have an account?"}<button type="button" onClick={() => onNavigate(register ? '/login' : '/register')}>{register ? 'Sign in' : 'Create account'} <ArrowRight size={15} /></button></div>;
}

export function LoginPage() {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [form, setForm] = useState({ email: '', password: '' });
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setSubmitting(true);
    try {
      const response = await api.post('/login', form);
      const user = response.data.user;
      const role = String(user.role).toLowerCase();
      login({ ...user, role, token: response.data.token });
      navigate(`/${role}/dashboard`, { replace: true });
    } catch (requestError) {
      setError(apiError(requestError));
    } finally {
      setSubmitting(false);
    }
  };

  return <div className="auth-shell"><AuthBrand /><main className="auth-content"><section className="auth-card"><div className="auth-card-heading"><span className="auth-card-icon"><LogIn size={19} /></span><span className="auth-eyebrow">CUSTOMER ACCESS</span><h2>Welcome back</h2><p>Sign in to continue to your VSMS account.</p></div><AuthError message={error} /><form className="auth-form" onSubmit={submit} noValidate><label className="auth-field"><span>Username or email</span><div className="auth-input-wrap"><User size={17} /><input required value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} autoComplete="username" /></div></label><PasswordField label="Password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} visible={showPassword} onToggle={() => setShowPassword(!showPassword)} autoComplete="current-password" /><div className="auth-form-options"><label className="auth-check"><input type="checkbox" disabled /> <span>Remember me</span></label><button type="button" className="auth-text-button" disabled title="Password reset is not configured">Forgot password?</button></div><button className="auth-submit" disabled={submitting} type="submit">{submitting ? <><span className="auth-spinner" /> Signing in...</> : <>Sign in <ArrowRight size={17} /></>}</button></form><AuthFooter onNavigate={navigate} /></section></main></div>;
}

export function RegisterPage() {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmation, setShowConfirmation] = useState(false);
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const update = (field) => (event) => setForm({ ...form, [field]: event.target.value });
  const submit = async (event) => {
    event.preventDefault();
    setError('');
    if (form.password !== form.password_confirmation) { setError('Passwords do not match.'); return; }
    if (form.password.length < 8) { setError('Password must be at least 8 characters.'); return; }
    setSubmitting(true);
    try {
      const response = await api.post('/register', form);
      const user = response.data.user;
      login({ ...user, role: String(user.role).toLowerCase(), token: response.data.token });
      navigate('/customer/dashboard', { replace: true });
    } catch (requestError) {
      setError(apiError(requestError));
    } finally {
      setSubmitting(false);
    }
  };

  return <div className="auth-shell"><AuthBrand register /><main className="auth-content"><section className="auth-card auth-card-register"><div className="auth-card-heading"><span className="auth-card-icon"><UserPlus size={19} /></span><span className="auth-eyebrow">CUSTOMER REGISTRATION</span><h2>Create your account</h2><p>Join VSMS and manage your vehicle services with ease.</p></div><AuthError message={error} /><form className="auth-form" onSubmit={submit} noValidate><label className="auth-field"><span>Full name</span><div className="auth-input-wrap"><User size={17} /><input required value={form.name} onChange={update('name')} autoComplete="name" /></div></label><label className="auth-field"><span>Email address</span><div className="auth-input-wrap"><Mail size={17} /><input required type="email" value={form.email} onChange={update('email')} autoComplete="email" /></div></label><label className="auth-field"><span>Phone number</span><div className="auth-input-wrap"><Phone size={17} /><input required value={form.phone} onChange={update('phone')} autoComplete="tel" /></div></label><div className="auth-form-grid"><PasswordField label="Password" value={form.password} onChange={update('password')} visible={showPassword} onToggle={() => setShowPassword(!showPassword)} autoComplete="new-password" /><PasswordField label="Confirm password" value={form.password_confirmation} onChange={update('password_confirmation')} visible={showConfirmation} onToggle={() => setShowConfirmation(!showConfirmation)} autoComplete="new-password" /></div><div className="auth-requirement"><Check size={14} /> Minimum 8 characters</div><button className="auth-submit" disabled={submitting} type="submit">{submitting ? <><span className="auth-spinner" /> Creating account...</> : <>Create account <ArrowRight size={17} /></>}</button></form><AuthFooter register onNavigate={navigate} /></section></main></div>;
}

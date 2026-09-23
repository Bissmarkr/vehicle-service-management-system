import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import AppActions from './AppActions';

export default function App() {
  return <BrowserRouter><AuthProvider><AppActions /></AuthProvider></BrowserRouter>;
}

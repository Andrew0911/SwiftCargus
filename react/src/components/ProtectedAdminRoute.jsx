import { Navigate } from 'react-router-dom';

const ProtectedAdminRoute = ({ children }) => {
    const isAdmin = localStorage.getItem('isAdmin') === 'true';

    return isAdmin ? children : <Navigate to="/" />;
};

export default ProtectedAdminRoute;
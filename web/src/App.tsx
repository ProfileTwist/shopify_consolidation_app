import { BrowserRouter, Navigate, Route, Routes } from "react-router-dom"
import { AuthProvider } from "@/lib/auth"
import ProtectedRoute from "@/components/ProtectedRoute"
import Layout from "@/components/Layout"
import LoginPage from "@/pages/LoginPage"
import OrdersPage from "@/pages/OrdersPage"
import PayoutsPage from "@/pages/PayoutsPage"
import ProductsPage from "@/pages/ProductsPage"
import ConnectionsPage from "@/pages/ConnectionsPage"
import UsersPage from "@/pages/UsersPage"

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route
            element={
              <ProtectedRoute>
                <Layout />
              </ProtectedRoute>
            }
          >
            <Route path="/" element={<OrdersPage />} />
            <Route path="/payouts" element={<PayoutsPage />} />
            <Route path="/products" element={<ProductsPage />} />
            <Route path="/connections" element={<ConnectionsPage />} />
            <Route path="/users" element={<UsersPage />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}

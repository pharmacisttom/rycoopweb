import React, { lazy, Suspense, useCallback, useEffect } from 'react';
import { Routes, Route, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import { useToast } from './context/ToastContext';
import { useInactivityTimeout } from './hooks/useInactivityTimeout';

// Core Layout Shell (Synchronously loaded for instant UI render)
import TopBar from './components/layout/TopBar';
import Navbar from './components/layout/Navbar';
import Footer from './components/layout/Footer';
import QuickActionDock from './components/layout/QuickActionDock';
import AuthModal from './components/member/AuthModal';
import CampaignModal from './components/common/CampaignModal';
import AIChatWidget from './components/common/AIChatWidget';
import CookieConsent from './components/common/CookieConsent';
import PageSkeletonLoader from './components/common/PageSkeletonLoader';
import SessionTimeoutModal from './components/common/SessionTimeoutModal';

// Synchronous Fast-Entry Pages
import HomePage from './pages/HomePage';
import LoginPage from './pages/LoginPage';

// Lazy-Loaded Route Chunks (Performance & Code Splitting)
const MemberDashboardPage = lazy(() => import('./pages/MemberDashboardPage'));
const AdminDashboardPage = lazy(() => import('./pages/AdminDashboardPage'));
const CalculatorPage = lazy(() => import('./pages/CalculatorPage'));
const LoansPage = lazy(() => import('./pages/LoansPage'));
const DepositsPage = lazy(() => import('./pages/DepositsPage'));
const LoanChecklistPage = lazy(() => import('./pages/LoanChecklistPage'));
const WelfarePage = lazy(() => import('./pages/WelfarePage'));
const EServicePage = lazy(() => import('./pages/EServicePage'));
const DocumentsPage = lazy(() => import('./pages/DocumentsPage'));
const NewsPage = lazy(() => import('./pages/NewsPage'));
const ContactPage = lazy(() => import('./pages/ContactPage'));
const AboutPage = lazy(() => import('./pages/AboutPage'));
const BoardPage = lazy(() => import('./pages/BoardPage'));
const BoardOfficerPage = lazy(() => import('./pages/BoardOfficerPage'));
const StatisticsPage = lazy(() => import('./pages/StatisticsPage'));
const ProfilePage = lazy(() => import('./pages/ProfilePage'));
const VerifyReceiptPage = lazy(() => import('./pages/VerifyReceiptPage'));
const NotFoundPage = lazy(() => import('./pages/NotFoundPage'));
const ServiceUnavailablePage = lazy(() => import('./pages/ServiceUnavailablePage'));

const memberPortalEnabled = import.meta.env.VITE_FEATURE_MEMBER_PORTAL === 'true';

// RYCOOP LED Member Check Pages
const AdminLedDashboardPage = lazy(() => import('./pages/AdminLedDashboardPage'));
const AdminLedSearchPage = lazy(() => import('./pages/AdminLedSearchPage'));
const AdminLedReviewPage = lazy(() => import('./pages/AdminLedReviewPage'));
const AdminLedBatchPage = lazy(() => import('./pages/AdminLedBatchPage'));
const AdminLedSchemaPage = lazy(() => import('./pages/AdminLedSchemaPage'));

// Scroll to top component on route changes
function ScrollToTop() {
  const { pathname, hash } = useLocation();
  useEffect(() => {
    if (hash) {
      const targetId = hash.slice(1);
      const timeoutId = window.setTimeout(() => {
        document.getElementById(targetId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 0);
      return () => window.clearTimeout(timeoutId);
    }
    window.scrollTo(0, 0);
    return undefined;
  }, [pathname, hash]);
  return null;
}

const publicRoutes = [
  ['/about', AboutPage],
  ['/board', BoardPage],
  ['/board_committee', BoardPage],
  ['/board_officer', BoardOfficerPage],
  ['/statistics', StatisticsPage],
  ['/assets', StatisticsPage],
  ['/financial-assets', StatisticsPage],
  ['/deposits', DepositsPage],
  ['/rates', DepositsPage],
  ['/loans', LoansPage],
  ['/calculator', CalculatorPage],
  ['/loan-checklist', LoanChecklistPage],
  ['/loan-readiness', LoanChecklistPage],
  ['/loans/checklist', LoanChecklistPage],
  ['/dividend-estimator', CalculatorPage],
  ['/welfare', WelfarePage],
  ['/eservice', EServicePage],
  ['/documents', DocumentsPage],
  ['/news', NewsPage],
  ['/announcements', NewsPage],
  ['/events', NewsPage],
  ['/calendar', NewsPage],
  ['/contact', ContactPage],
  ['/complaints', ContactPage],
  ['/faqs', ContactPage],
];

const memberRoutes = [
  '/dashboard', '/member', '/member/dashboard', '/member/shares',
  '/member/deposits', '/member/loans', '/member/loan-requests',
  '/member/complaints', '/member/receipts', '/e-tracking', '/tracking',
  '/loan-requests', '/portal',
];

const profileRoutes = ['/member/profile', '/profile', '/settings', '/edit-profile'];
const adminRoutes = ['/admin', '/admin/dashboard'];

export default function App() {
  const { isLoggedIn, logout } = useAuth();
  const { toast } = useToast();
  const navigate = useNavigate();

  // Inactivity Timeout Management (20 minutes inactivity / 2 minutes warning)
  const handleInactivityTimeout = useCallback(() => {
    logout();
    navigate('/login');
    toast.warning('เซสชันหมดอายุเนื่องจากไม่มีการใช้งานเป็นเวลา 20 นาที กรุณาเข้าสู่ระบบใหม่อีกครั้ง', 'เซสชันหมดอายุ');
  }, [logout, navigate, toast]);

  const handleManualLogout = useCallback(() => {
    logout();
    navigate('/');
    toast.info('ออกจากระบบเรียบร้อยแล้ว');
  }, [logout, navigate, toast]);

  const {
    showWarning,
    remainingSeconds,
    extendSession,
    logoutNow
  } = useInactivityTimeout({
    isLoggedIn,
    onTimeout: handleInactivityTimeout,
    onLogout: handleManualLogout
  });

  const handleExtendSession = useCallback(() => {
    extendSession();
    toast.success('ต่ออายุเซสชันการใช้งานเรียบร้อยแล้ว');
  }, [extendSession, toast]);

  return (
    <div className="app-container">
      <ScrollToTop />
      <TopBar />
      <Navbar />

      <main className="main-content">
        <Suspense fallback={<PageSkeletonLoader />}>
          <Routes>
            <Route path="/" element={<HomePage />} />
            {publicRoutes.map(([path, Page]) => (
              <Route
                key={path}
                path={path}
                element={path === '/eservice' && !memberPortalEnabled ? <ServiceUnavailablePage /> : <Page />}
              />
            ))}

            <Route path="/verify-receipt" element={<VerifyReceiptPage />} />
            <Route path="/verify-receipt/:token" element={<VerifyReceiptPage />} />

            {memberRoutes.map((path) => <Route key={path} path={path} element={memberPortalEnabled ? <MemberDashboardPage /> : <ServiceUnavailablePage />} />)}
            {profileRoutes.map((path) => <Route key={path} path={path} element={memberPortalEnabled ? <ProfilePage /> : <ServiceUnavailablePage />} />)}
            <Route path="/service-unavailable" element={<ServiceUnavailablePage />} />
            <Route path="/staff" element={<AdminDashboardPage />} />
            <Route path="/staff/dashboard" element={<AdminDashboardPage />} />
            {adminRoutes.map((path) => <Route key={path} path={path} element={<AdminDashboardPage />} />)}

            {/* RYCOOP LED Member Check Routes */}
            <Route path="/admin/led" element={<AdminLedDashboardPage />} />
            <Route path="/admin/led/dashboard" element={<AdminLedDashboardPage />} />
            <Route path="/admin/led/search" element={<AdminLedSearchPage />} />
            <Route path="/admin/led/review" element={<AdminLedReviewPage />} />
            <Route path="/admin/led/batch" element={<AdminLedBatchPage />} />
            <Route path="/admin/led/schema" element={<AdminLedSchemaPage />} />

            <Route path="/login" element={memberPortalEnabled ? <LoginPage /> : <ServiceUnavailablePage />} />
            <Route path="/admin/login" element={<LoginPage />} />

            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </Suspense>
      </main>

      <Footer />
      <QuickActionDock />
      <AIChatWidget />
      <CookieConsent />
      {memberPortalEnabled && <AuthModal />}
      <CampaignModal />

      {/* Inactivity Warning Modal */}
      <SessionTimeoutModal
        isOpen={showWarning}
        remainingSeconds={remainingSeconds}
        onExtendSession={handleExtendSession}
        onLogout={logoutNow}
      />
    </div>
  );
}

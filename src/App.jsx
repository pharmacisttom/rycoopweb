import React, { lazy, Suspense, useCallback, useEffect } from 'react';
import { Navigate, Routes, Route, useLocation, useNavigate } from 'react-router-dom';
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
const MemberDividendsPage = lazy(() => import('./pages/MemberDividendsPage'));
const AdminDividendImportPage = lazy(() => import('./pages/AdminDividendImportPage'));
const AdminMembersDashboardPage = lazy(() => import('./pages/AdminMembersDashboardPage'));
const AdminMembersPage = lazy(() => import('./pages/AdminMembersPage'));
const AdminMemberDetailPage = lazy(() => import('./pages/AdminMemberDetailPage'));
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
const PrivacyPolicyPage = lazy(() => import('./pages/PrivacyPolicyPage'));

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
  ['/privacy/policy', PrivacyPolicyPage],
  ['/privacy/cookies', PrivacyPolicyPage],
];

const memberRoutes = [
  '/dashboard', '/member', '/member/dashboard', '/member/shares',
  '/member/deposits', '/member/loans', '/member/loan-requests',
  '/member/complaints', '/member/receipts', '/e-tracking', '/tracking',
  '/loan-requests', '/portal',
];

const profileRoutes = ['/member/profile', '/profile', '/settings', '/edit-profile'];
const adminRoutes = ['/admin', '/admin/dashboard'];
const staffRoles = [
  'staff', 'manager', 'executive', 'finance', 'loan_officer', 'welfare_officer',
  'pr_officer', 'document_officer', 'complaint_officer', 'auditor', 'it_admin'
];

function StaffProtectedRoute({ children, roles }) {
  const { user, isLoggedIn, sessionStatus } = useAuth();

  if (sessionStatus === 'checking') return <PageSkeletonLoader />;
  if (!isLoggedIn || sessionStatus !== 'online') {
    return <Navigate to="/admin/login" replace />;
  }
  if (roles && !roles.includes(user?.role)) {
    return <Navigate to={user?.role === 'member_admin' ? '/admin/members/dashboard' : user?.role === 'member' ? '/member/dividends' : user?.role === 'super_admin' ? '/admin/dashboard' : '/staff/dashboard'} replace />;
  }
  return children;
}

export default function App() {
  const { isLoggedIn, logout } = useAuth();
  const { toast } = useToast();
  const navigate = useNavigate();

  // Inactivity Timeout Management (20 minutes inactivity / 2 minutes warning)
  const handleInactivityTimeout = useCallback(() => {
    logout();
    navigate('/admin/login');
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
            <Route path="/staff" element={<StaffProtectedRoute roles={staffRoles}><AdminDashboardPage /></StaffProtectedRoute>} />
            <Route path="/staff/dashboard" element={<StaffProtectedRoute roles={staffRoles}><AdminDashboardPage /></StaffProtectedRoute>} />
            {adminRoutes.map((path) => <Route key={path} path={path} element={<StaffProtectedRoute roles={['super_admin']}><AdminDashboardPage /></StaffProtectedRoute>} />)}

            {/* RYCOOP LED Member Check Routes */}
            <Route path="/admin/dividends/import" element={<StaffProtectedRoute roles={['super_admin', 'member_admin']}><AdminDividendImportPage /></StaffProtectedRoute>} />
            <Route path="/admin/members/dashboard" element={<StaffProtectedRoute roles={['super_admin', 'member_admin']}><AdminMembersDashboardPage /></StaffProtectedRoute>} />
            <Route path="/admin/members" element={<StaffProtectedRoute roles={['super_admin', 'member_admin']}><AdminMembersPage /></StaffProtectedRoute>} />
            <Route path="/admin/members/:id" element={<StaffProtectedRoute roles={['super_admin', 'member_admin']}><AdminMemberDetailPage /></StaffProtectedRoute>} />
            <Route path="/admin/led" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedDashboardPage /></StaffProtectedRoute>} />
            <Route path="/admin/led/dashboard" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedDashboardPage /></StaffProtectedRoute>} />
            <Route path="/admin/led/search" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedSearchPage /></StaffProtectedRoute>} />
            <Route path="/admin/led/review" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedReviewPage /></StaffProtectedRoute>} />
            <Route path="/admin/led/batch" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedBatchPage /></StaffProtectedRoute>} />
            <Route path="/admin/led/schema" element={<StaffProtectedRoute roles={['super_admin', ...staffRoles]}><AdminLedSchemaPage /></StaffProtectedRoute>} />

            <Route path="/login" element={<LoginPage />} />
            <Route path="/member/login" element={<LoginPage />} />
            <Route path="/member/dividends" element={<MemberDividendsPage />} />
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

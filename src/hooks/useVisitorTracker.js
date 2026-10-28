import { useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import { recordPageVisit } from '../services/api';

const VISITOR_ID_KEY = 'rycoop_vid_v3';
const VISITOR_EXP_KEY = 'rycoop_vid_exp_v3';
const SESSION_ID_KEY = 'rycoop_vsid_v3';
const SESSION_TS_KEY = 'rycoop_vsid_ts_v3';
const LAST_HIT_KEY = 'rycoop_vhit_v3';

const VISITOR_RETENTION_MS = 90 * 24 * 60 * 60 * 1000; // 90 days
const SESSION_TIMEOUT_MS = 30 * 60 * 1000; // 30 minutes inactivity
const DEDUP_WINDOW_MS = 15 * 1000; // 15 seconds same-page duplicate suppression

const EXCLUDED_ROUTE_PREFIXES = [
  '/admin',
  '/member',
  '/api',
  '/login',
  '/logout',
  '/portal',
  '/dashboard',
  '/staff',
  '/profile',
  '/settings',
  '/verify-tax-cert',
  '/auth',
  '/mfa',
  '/two-factor',
];

/**
 * Generate a cryptographically secure random identifier without Math.random().
 */
function generateSecureToken(prefix = 'v') {
  try {
    if (typeof crypto !== 'undefined') {
      if (typeof crypto.randomUUID === 'function') {
        return `${prefix}_${crypto.randomUUID().replace(/-/g, '')}`;
      }
      if (typeof crypto.getRandomValues === 'function') {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
        return `${prefix}_${hex}`;
      }
    }
  } catch (err) {
    // Fallback if crypto API is restricted
  }
  return `${prefix}_${Date.now().toString(36)}`;
}

/**
 * Determine whether a route path is an approved public route.
 */
export function isTrackablePublicPath(pathname) {
  if (!pathname || typeof pathname !== 'string') return false;
  const clean = pathname.trim().toLowerCase();
  if (!clean.startsWith('/')) return false;
  for (const prefix of EXCLUDED_ROUTE_PREFIXES) {
    if (clean === prefix || clean.startsWith(`${prefix}/`)) {
      return false;
    }
  }
  return true;
}

/**
 * Sanitize referrer to origin + pathname only (strip query secrets & tokens).
 */
function sanitizeReferrer(rawReferrer) {
  if (!rawReferrer || typeof rawReferrer !== 'string') return '';
  try {
    const parsed = new URL(rawReferrer);
    return `${parsed.origin}${parsed.pathname}`.slice(0, 255);
  } catch (err) {
    return '';
  }
}

/**
 * Retrieve or rotate pseudonymous visitor ID (90d) and sliding session ID (30m).
 */
function getOrRotateVisitorAndSession() {
  const now = Date.now();
  let visitorId = '';
  let sessionId = '';

  try {
    const storedVisitor = localStorage.getItem(VISITOR_ID_KEY);
    const storedVisitorExp = Number(localStorage.getItem(VISITOR_EXP_KEY) || 0);
    if (storedVisitor && storedVisitorExp > now) {
      visitorId = storedVisitor;
    } else {
      visitorId = generateSecureToken('vid');
      localStorage.setItem(VISITOR_ID_KEY, visitorId);
    }
    localStorage.setItem(VISITOR_EXP_KEY, String(now + VISITOR_RETENTION_MS));

    const storedSession = localStorage.getItem(SESSION_ID_KEY);
    const storedSessionTs = Number(localStorage.getItem(SESSION_TS_KEY) || 0);
    if (storedSession && storedSessionTs > 0 && now - storedSessionTs <= SESSION_TIMEOUT_MS) {
      sessionId = storedSession;
    } else {
      sessionId = generateSecureToken('sid');
      localStorage.setItem(SESSION_ID_KEY, sessionId);
    }
    localStorage.setItem(SESSION_TS_KEY, String(now));
  } catch (err) {
    try {
      visitorId = sessionStorage.getItem(VISITOR_ID_KEY) || generateSecureToken('vid');
      sessionId = sessionStorage.getItem(SESSION_ID_KEY) || generateSecureToken('sid');
      sessionStorage.setItem(VISITOR_ID_KEY, visitorId);
      sessionStorage.setItem(SESSION_ID_KEY, sessionId);
    } catch (innerErr) {
      visitorId = generateSecureToken('vid');
      sessionId = generateSecureToken('sid');
    }
  }

  return { visitorId, sessionId };
}

/**
 * Suppress duplicate page views on the same path within 15 seconds across reloads/StrictMode.
 */
function shouldSuppressDuplicateHit(cleanPath) {
  const now = Date.now();
  try {
    const raw = sessionStorage.getItem(LAST_HIT_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed && parsed.path === cleanPath && now - Number(parsed.ts || 0) < DEDUP_WINDOW_MS) {
        return true;
      }
    }
    sessionStorage.setItem(LAST_HIT_KEY, JSON.stringify({ path: cleanPath, ts: now }));
  } catch (err) {
    // Ignore storage errors
  }
  return false;
}

/**
 * Anonymous, PDPA-Compliant Visitor Tracking Hook (RYCOOPWEB 3.0)
 * - Tracks only approved public routes
 * - Never sends query-string tokens or private route paths
 * - Uses CSPRNG pseudonymous visitor & session identifiers
 */
export function useVisitorTracker() {
  const location = useLocation();
  const lastTrackedRef = useRef({ path: '', ts: 0 });

  useEffect(() => {
    const pathname = location.pathname || '/';
    if (!isTrackablePublicPath(pathname)) {
      return undefined;
    }

    const now = Date.now();
    if (
      lastTrackedRef.current.path === pathname &&
      now - lastTrackedRef.current.ts < DEDUP_WINDOW_MS
    ) {
      return undefined;
    }

    if (shouldSuppressDuplicateHit(pathname)) {
      lastTrackedRef.current = { path: pathname, ts: now };
      return undefined;
    }

    lastTrackedRef.current = { path: pathname, ts: now };
    const { visitorId, sessionId } = getOrRotateVisitorAndSession();

    const timer = setTimeout(() => {
      try {
        recordPageVisit({
          visitor_id: visitorId,
          session_id: sessionId,
          page_url: pathname,
          page_title: (typeof document !== 'undefined' && document.title)
            ? document.title.slice(0, 180)
            : 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
          referrer: typeof document !== 'undefined' ? sanitizeReferrer(document.referrer) : '',
        });
      } catch (err) {
        // Silent fail for offline or tracking blocker
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [location.pathname]);
}

export default useVisitorTracker;

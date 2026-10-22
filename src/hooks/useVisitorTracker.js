import { useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import { recordPageVisit } from '../services/api';

/**
 * Anonymous, PDPA-Compliant Visitor Tracking Hook
 * Tracks page transitions and registers visitor sessions.
 */
export function useVisitorTracker() {
  const location = useLocation();
  const lastPathRef = useRef('');

  useEffect(() => {
    // Generate or retrieve anonymous session ID
    let sessionId = '';
    try {
      sessionId = sessionStorage.getItem('rycoop_vsession');
      if (!sessionId) {
        sessionId = 'sess_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
        sessionStorage.setItem('rycoop_vsession', sessionId);
      }
    } catch (e) {
      sessionId = 'sess_' + Date.now().toString(36);
    }

    const currentPath = location.pathname + location.search;
    if (lastPathRef.current === currentPath) {
      return;
    }
    lastPathRef.current = currentPath;

    // Small delay to allow component title updates
    const timer = setTimeout(() => {
      try {
        recordPageVisit({
          session_id: sessionId,
          page_url: currentPath,
          page_title: document.title || 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด',
          referrer: document.referrer || ''
        });
      } catch (err) {
        // Silent fail for offline / tracking blocker
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [location.pathname, location.search]);
}

export default useVisitorTracker;

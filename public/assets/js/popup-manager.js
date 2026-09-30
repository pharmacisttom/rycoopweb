/**
 * RayongCoop Popup Campaign Manager
 * Handles triggers (load, delay, scroll, exit), priority queues, frequency rules, and analytics.
 */

const PopupManager = (function() {
    let activePopups = [];
    let currentPopup = null;

    function init(popupsData) {
        if (!popupsData || !popupsData.length) return;
        activePopups = popupsData.sort((a, b) => getPriorityWeight(b.priority) - getPriorityWeight(a.priority));

        // Evaluate top-priority popup eligibility
        for (const popup of activePopups) {
            if (isEligible(popup)) {
                currentPopup = popup;
                setupTrigger(popup);
                break; // Show only highest priority eligible popup
            }
        }
    }

    function getPriorityWeight(priority) {
        switch (priority) {
            case 'critical': return 4;
            case 'high': return 3;
            case 'normal': return 2;
            case 'low': return 1;
            default: return 2;
        }
    }

    function isEligible(popup) {
        const key = `popup_seen_${popup.id}`;
        const dismissKey = `popup_dismiss_${popup.id}`;

        if (localStorage.getItem(dismissKey)) {
            return false;
        }

        const freq = popup.frequency || 'session';
        if (freq === 'always') {
            return true;
        } else if (freq === 'session') {
            return !sessionStorage.getItem(key);
        } else if (freq === 'daily') {
            const lastSeen = localStorage.getItem(key);
            if (!lastSeen) return true;
            return (Date.now() - parseInt(lastSeen, 10)) > 24 * 60 * 60 * 1000;
        } else if (freq === 'x_days') {
            const days = popup.frequency_days || 7;
            const lastSeen = localStorage.getItem(key);
            if (!lastSeen) return true;
            return (Date.now() - parseInt(lastSeen, 10)) > days * 24 * 60 * 60 * 1000;
        }
        return true;
    }

    function recordSeen(popup) {
        const key = `popup_seen_${popup.id}`;
        sessionStorage.setItem(key, '1');
        localStorage.setItem(key, Date.now().toString());
    }

    function setupTrigger(popup) {
        const mode = popup.display_mode || 'load';
        const delay = (popup.delay_seconds || 0) * 1000;

        if (mode === 'load') {
            if (delay > 0) {
                setTimeout(() => showPopup(popup), delay);
            } else {
                showPopup(popup);
            }
        } else if (mode === 'scroll') {
            const targetPercent = popup.scroll_percent || 50;
            const onScroll = function() {
                const scrollPos = window.scrollY;
                const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
                if (totalHeight > 0 && (scrollPos / totalHeight * 100) >= targetPercent) {
                    window.removeEventListener('scroll', onScroll);
                    showPopup(popup);
                }
            };
            window.addEventListener('scroll', onScroll);
        } else if (mode === 'exit') {
            const onMouseLeave = function(e) {
                if (e.clientY <= 0) {
                    document.removeEventListener('mouseleave', onMouseLeave);
                    showPopup(popup);
                }
            };
            document.addEventListener('mouseleave', onMouseLeave);
        }
    }

    function showPopup(popup) {
        recordSeen(popup);
        trackEvent(popup.id, 'impression');

        const modalEl = document.getElementById('campaignPopupModal');
        if (!modalEl) return;

        // Populate popup content
        const titleEl = document.getElementById('popupModalTitle');
        const bodyEl = document.getElementById('popupModalBody');
        const footerEl = document.getElementById('popupModalFooter');

        if (titleEl) titleEl.textContent = popup.title;

        let bodyHtml = '';
        if (popup.desktop_image) {
            const rawImage = String(popup.desktop_image).trim().replaceAll('\\', '/');
            let imgSrc = rawImage;
            if (!/^https?:\/\//i.test(rawImage) && !rawImage.startsWith('data:')) {
                if (rawImage.startsWith('/')) imgSrc = rawImage;
                else if (rawImage.startsWith('assets/') || rawImage.startsWith('storage/uploads/')) imgSrc = `/${rawImage}`;
                else imgSrc = `/storage/uploads/${rawImage.replace(/^\/+/, '')}`;
                imgSrc = (window.APP_URL || '') + imgSrc;
            }
            bodyHtml += `<div class="text-center mb-3"><img src="${imgSrc}" class="img-fluid rounded-3 shadow-sm" alt="${popup.title}" onerror="this.style.display='none'"></div>`;
        }
        if (popup.content) {
            bodyHtml += `<div class="popup-content-text mb-3">${popup.content}</div>`;
        }
        if (bodyEl) bodyEl.innerHTML = bodyHtml;

        let footerHtml = `
            <div class="form-check me-auto">
                <input class="form-check-input" type="checkbox" id="chkDoNotShowAgain">
                <label class="form-check-label text-muted small" for="chkDoNotShowAgain">ไม่ต้องแสดงอีก</label>
            </div>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
        `;
        if (popup.button_text && popup.button_url) {
            footerHtml += `<a href="${popup.button_url}" target="${popup.button_target || '_self'}" class="btn btn-primary" id="btnPopupCTA">${popup.button_text}</a>`;
        }
        if (footerEl) footerEl.innerHTML = footerHtml;

        // Bind events
        document.getElementById('chkDoNotShowAgain')?.addEventListener('change', function(e) {
            if (e.target.checked) {
                localStorage.setItem(`popup_dismiss_${popup.id}`, '1');
                trackEvent(popup.id, 'dismiss');
            } else {
                localStorage.removeItem(`popup_dismiss_${popup.id}`);
            }
        });

        document.getElementById('btnPopupCTA')?.addEventListener('click', function() {
            trackEvent(popup.id, 'cta_click');
        });

        modalEl.addEventListener('hidden.bs.modal', function() {
            trackEvent(popup.id, 'close');
        }, { once: true });

        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    }

    function trackEvent(popupId, eventType) {
        fetch(window.APP_URL + '/api/popups/event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                popup_id: popupId,
                event_type: eventType,
                page_path: window.location.pathname
            })
        }).catch(err => {});
    }

    return {
        init: init
    };
})();

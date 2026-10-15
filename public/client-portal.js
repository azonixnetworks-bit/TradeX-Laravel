(() => {
    'use strict';

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const STORE = 'tradex.client.session.v1';
    const REGISTRATION = 'tradex.client.registration.v1';
    const INSTALLATION = 'tradex.client.installation.v1';
    const BROKER_INTENT = 'tradex.client.broker-intent.v1';

    let session = parseSession(STORE);
    let registration = parseSession(REGISTRATION);
    let currentView = 'overview';
    let paymentMethods = [];

    function parseSession(key) {
        try {
            return JSON.parse(sessionStorage.getItem(key) || 'null');
        } catch {
            sessionStorage.removeItem(key);
            return null;
        }
    }

    function createUuid() {
        if (crypto.randomUUID) {
            return crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (value) => {
            const random = Math.random() * 16 | 0;
            const nibble = value === 'x' ? random : (random & 3 | 8);

            return nibble.toString(16);
        });
    }

    function installationId() {
        let id = localStorage.getItem(INSTALLATION);

        if (! id) {
            id = createUuid();
            localStorage.setItem(INSTALLATION, id);
        }

        return id;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[character]));
    }

    function saveSession(data) {
        session = {
            ...session,
            ...data,
            stored_at: Date.now(),
        };

        sessionStorage.setItem(STORE, JSON.stringify(session));
    }

    function clearSession() {
        sessionStorage.removeItem(STORE);
        session = null;
    }

    function setMessage(text, type = '') {
        const message = ! $('#portal').classList.contains('hidden')
            ? $('#portal-message')
            : $('#auth-message');

        if (message) {
            message.innerHTML = text
                ? `<div class="notice ${type}">${escapeHtml(text)}</div>`
                : '';
        }
    }

    async function refreshSession() {
        if (! session?.refresh_token) {
            return false;
        }

        const response = await fetch('/api/v1/auth/refresh', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                refresh_token: session.refresh_token,
                installation_id: installationId(),
            }),
        });

        const body = await response.json().catch(() => ({}));

        if (! response.ok || body.success === false || ! body.data?.access_token) {
            clearSession();
            return false;
        }

        saveSession(body.data);

        return true;
    }

    async function api(path, options = {}, retried = false) {
        const {
            method = 'GET',
            body,
            headers = {},
        } = options;
        const response = await fetch(path, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(session?.access_token
                    ? { Authorization: `Bearer ${session.access_token}` }
                    : {}),
                ...headers,
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        const payload = await response.json().catch(() => ({}));

        if (response.status === 401 && ! retried && await refreshSession()) {
            return api(path, options, true);
        }

        if (! response.ok || payload.success === false) {
            throw new Error(
                payload?.error?.message
                || payload?.message
                || `Request failed (${response.status})`
            );
        }

        return payload.data;
    }

    async function apiForm(path, form, headers = {}, retried = false) {
        const response = await fetch(path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(session?.access_token
                    ? { Authorization: `Bearer ${session.access_token}` }
                    : {}),
                ...headers,
            },
            body: form,
        });
        const payload = await response.json().catch(() => ({}));

        if (response.status === 401 && ! retried && await refreshSession()) {
            return apiForm(path, form, headers, true);
        }

        if (! response.ok || payload.success === false) {
            throw new Error(
                payload?.error?.message
                || payload?.message
                || `Request failed (${response.status})`
            );
        }

        return payload.data;
    }

    function show(screen) {
        $('#landing').classList.toggle('hidden', screen !== 'landing');
        $('#auth').classList.toggle('hidden', screen !== 'auth');
        $('#portal').classList.toggle('hidden', screen !== 'portal');
        $('#signout').classList.toggle('hidden', ! session);

        if (screen === 'portal') {
            loadView(currentView);
        }
    }

    function showRegistration(step, values = {}) {
        registration = { ...registration, step, ...values };
        sessionStorage.setItem(REGISTRATION, JSON.stringify(registration));
        $('#login-panel').classList.add('hidden');
        $('#register-panel').classList.add('hidden');
        $('#registration-flow').classList.remove('hidden');

        const labels = {
            email: ['Email verification', 'Verify your email.', 'Enter the code sent to your email address.', 'Email code', 'Verify email'],
            whatsapp: ['WhatsApp verification', 'Verify your WhatsApp number.', 'Enter the six-digit code sent to your WhatsApp number.', 'WhatsApp code', 'Verify WhatsApp'],
            totp: ['Authenticator setup', 'Protect your account.', 'Scan this QR code using an authenticator app, then enter its current six-digit code.', 'Authenticator code', 'Activate account'],
        };
        const current = labels[step];

        $('#registration-step').textContent = current[0];
        $('#registration-title').textContent = current[1];
        $('#registration-copy').textContent = current[2];
        $('#verification-label').textContent = current[3];
        $('#verification-submit').textContent = current[4];
        $('#verification-code').value = '';
        $('#totp-qr').classList.toggle('hidden', step !== 'totp');

        if (step === 'totp') {
            $('#totp-qr').src = registration.qr_code_data_uri || '';
        }
    }

    function card(title, body, action = '') {
        return `<article class="item"><h3>${escapeHtml(title)}</h3><p>${escapeHtml(body || '')}</p>${action ? `<div class="actions">${action}</div>` : ''}</article>`;
    }

    async function loadView(view) {
        const content = $('#portal-content');
        content.classList.add('loading');
        content.innerHTML = '<p class="empty">Loading current account data…</p>';

        try {
            if (view === 'overview') {
                const data = await api('/api/v1/dashboard');
                const account = data.account || {};
                const learning = data.learning || {};
                content.innerHTML = `
                    <div class="eyebrow">Account overview</div>
                    <h2>Welcome, ${escapeHtml(account.name || session?.user?.name || 'Trader')}.</h2>
                    <div class="data-grid">
                        <div class="metric"><span>Account</span><strong>${escapeHtml(account.account_type || 'FREE')}</strong></div>
                        <div class="metric"><span>Premium access</span><strong>${account.premium_access ? 'Active' : 'Not active'}</strong></div>
                        <div class="metric"><span>Lessons completed</span><strong>${escapeHtml(learning.completed_lessons ?? 0)}</strong></div>
                    </div>
                    <div class="notice">Your account, access and learning data are read from the TradeX database. Purchase eligibility is enforced by the server.</div>`;
                return;
            }

            if (view === 'learning') {
                const data = await api('/api/v1/education/courses');
                const items = data.items || [];
                content.innerHTML = `
                    <div class="eyebrow">Education</div><h2>My learning</h2>
                    <div class="course-list">${items.length
                        ? items.map((course) => card(
                            course.title || 'Course',
                            course.short_description || course.access_level || '',
                            course.can_access
                                ? `<button data-course="${escapeHtml(course.uuid)}">Open course</button>`
                                : '<span class="notice">Premium access required</span>'
                        )).join('')
                        : '<p class="empty">No published courses are available for this account yet.</p>'}
                    </div>`;
                $$('[data-course]', content).forEach((button) => {
                    button.addEventListener('click', () => openCourse(button.dataset.course));
                });
                return;
            }

            if (view === 'purchase') {
                const data = await api('/api/v1/payments/methods');
                const methods = data.methods || [];
                paymentMethods = methods;
                content.innerHTML = `
                    <div class="eyebrow">Access</div><h2>${escapeHtml(data.product?.name || 'Education Premium')}</h2>
                    <p>${data.purchase_allowed ? 'Choose an available secure payment method. The amount and access are issued by TradeX, not this page.' : 'Your current account does not need a new premium purchase.'}</p>
                    <div class="method-list">${methods.map((method) => card(
                        method.display_name,
                        method.available
                            ? `${method.amount?.amount || ''} ${method.amount?.currency || ''}`.trim()
                            : `Unavailable: ${method.unavailable_reason || 'not configured'}`,
                        method.available
                            ? `<button class="primary" data-pay="${escapeHtml(method.code)}">Continue with ${escapeHtml(method.display_name)}</button>`
                            : ''
                    )).join('')}</div>`;
                $$('[data-pay]', content).forEach((button) => {
                    button.addEventListener('click', () => startPayment(button.dataset.pay));
                });
                return;
            }

            const data = await api('/api/v1/copy-trade/brokers');
            const brokers = data.brokers || [];
            const pending = parseSession(BROKER_INTENT);
            content.innerHTML = `
                <div class="eyebrow">Copy trading</div><h2>Broker onboarding</h2>
                <p>Open an eligible partner account through TradeX first. When the broker has issued your live account number, submit it here for verification.</p>
                ${pending ? `<form id="broker-account-form" class="form-grid"><div class="notice">Partner opening started with ${escapeHtml(pending.broker_name || 'the selected broker')}. Submit only your issued live account number.</div><label class="field"><span>Broker account number</span><input name="account_number" inputmode="numeric" pattern="[0-9]+" required maxlength="15"></label><button class="primary" type="submit">Submit for verification</button></form>` : ''}
                <div class="broker-list">${brokers.map((item) => card(
                    item.broker_name || 'Broker',
                    item.market_category || '',
                    `<button data-broker="${escapeHtml(item.broker_market_uuid || '')}">Open partner account</button>`
                )).join('') || '<p class="empty">No broker onboarding options are currently available.</p>'}</div>`;
            $$('[data-broker]', content).forEach((button) => {
                button.addEventListener('click', () => openBroker(button.dataset.broker));
            });
            $('#broker-account-form', content)?.addEventListener('submit', submitBrokerAccount);
        } catch (error) {
            content.innerHTML = `<div class="notice error">${escapeHtml(error.message)}</div>`;
        } finally {
            content.classList.remove('loading');
        }
    }

    async function openCourse(id) {
        if (! id) {
            return;
        }

        try {
            const data = await api(`/api/v1/education/courses/${encodeURIComponent(id)}`);
            const course = data.course || {};
            const content = $('#portal-content');
            content.innerHTML = `
                <div class="eyebrow">Course</div><h2>${escapeHtml(course.title || 'Course')}</h2>
                <p>${escapeHtml(course.description || course.short_description || '')}</p>
                <div class="course-list">${(data.modules || []).map((module) => card(
                    module.title || 'Module',
                    module.short_description || `${(module.lessons || []).length} lesson(s)`,
                    (module.lessons || []).map((lesson) => lesson.can_access
                        ? `<button data-lesson="${escapeHtml(lesson.uuid)}">${escapeHtml(lesson.title || 'Open lesson')}</button>`
                        : `<span class="notice">${escapeHtml(lesson.title || 'Lesson')} — locked</span>`
                    ).join(' ')
                )).join('') || '<p class="empty">No published modules are available.</p>'}</div>`;
            $$('[data-lesson]', content).forEach((button) => {
                button.addEventListener('click', () => openLesson(button.dataset.lesson));
            });
        } catch (error) {
            setMessage(error.message, 'error');
        }
    }

    async function openLesson(id) {
        try {
            const data = await api(`/api/v1/education/lessons/${encodeURIComponent(id)}`);
            const lesson = data.lesson || {};
            const content = $('#portal-content');
            content.innerHTML = `
                <div class="eyebrow">${escapeHtml(data.course?.title || 'Education')} / ${escapeHtml(data.module?.title || 'Lesson')}</div>
                <h2>${escapeHtml(lesson.title || 'Lesson')}</h2>
                <p>${escapeHtml(lesson.description || lesson.short_description || '')}</p>
                <div id="video-player"></div>
                <div class="course-list">${(data.videos || []).map((video) => card(
                    video.title || 'Lesson video',
                    video.playback_ready ? 'Protected streaming is ready.' : 'This video is still being prepared.',
                    video.playback_ready
                        ? `<button class="primary" data-video="${escapeHtml(video.uuid)}">Play secure video</button>`
                        : ''
                )).join('') || '<p class="empty">No ready videos are available for this lesson.</p>'}</div>`;
            $$('[data-video]', content).forEach((button) => {
                button.addEventListener('click', () => playVideo(id, button.dataset.video));
            });
        } catch (error) {
            setMessage(error.message, 'error');
        }
    }

    function loadHlsLibrary() {
        if (window.Hls) {
            return Promise.resolve(window.Hls);
        }

        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-tradex-hls]');

            if (existing) {
                existing.addEventListener('load', () => resolve(window.Hls), { once: true });
                existing.addEventListener('error', () => reject(new Error('Secure video player failed to load.')), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/hls.js@1.5.20/dist/hls.min.js';
            script.async = true;
            script.crossOrigin = 'anonymous';
            script.dataset.tradexHls = 'true';
            script.onload = () => window.Hls
                ? resolve(window.Hls)
                : reject(new Error('Secure video player is unavailable.'));
            script.onerror = () => reject(new Error('Secure video player failed to load.'));
            document.head.appendChild(script);
        });
    }

    async function playVideo(lessonId, videoId) {
        const mount = $('#video-player');
        mount.innerHTML = '<p class="empty">Preparing your protected stream…</p>';

        try {
            const data = await api(
                `/api/v1/education/videos/${encodeURIComponent(videoId)}/playback-token`,
                { method: 'POST' }
            );
            const video = document.createElement('video');
            video.className = 'video';
            video.controls = true;
            video.playsInline = true;
            video.preload = 'metadata';
            video.setAttribute('controlsList', 'nodownload');
            mount.replaceChildren(video);

            if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = data.playback_url;
            } else {
                const Hls = await loadHlsLibrary();

                if (! Hls?.isSupported()) {
                    throw new Error('This browser cannot play protected HLS video.');
                }

                const hls = new Hls({ enableWorker: true });
                hls.loadSource(data.playback_url);
                hls.attachMedia(video);
                video._tradexHls = hls;
            }

            let lastSaved = -1;
            const saveProgress = async (status) => {
                const position = Math.floor(video.currentTime || 0);

                if (status === 'IN_PROGRESS' && position < lastSaved + 25) {
                    return;
                }

                lastSaved = position;
                await api(`/api/v1/education/lessons/${encodeURIComponent(lessonId)}/progress`, {
                    method: 'PATCH',
                    body: {
                        status,
                        last_video_uuid: videoId,
                        last_position_seconds: position,
                    },
                });
            };

            video.addEventListener('timeupdate', () => {
                saveProgress('IN_PROGRESS').catch(() => {});
            });
            video.addEventListener('ended', () => {
                saveProgress('COMPLETED')
                    .then(() => setMessage('Lesson progress marked complete.', 'success'))
                    .catch(() => {});
            });
        } catch (error) {
            mount.innerHTML = `<div class="notice error">${escapeHtml(error.message)}</div>`;
        }
    }

    async function startPayment(provider) {
        try {
            const onlineEndpoints = {
                STRIPE: '/api/v1/payments/stripe/checkout-sessions',
                NOWPAYMENTS: '/api/v1/payments/nowpayments/invoices',
                PAYPAL: '/api/v1/payments/paypal/orders',
            };
            const endpoint = onlineEndpoints[provider];

            if (! endpoint) {
                const method = paymentMethods.find((item) => item.code === provider);

                if (! method) {
                    throw new Error('This payment method is no longer available.');
                }

                return showManualPayment(method);
            }

            const order = await api(endpoint, {
                method: 'POST',
                headers: { 'Idempotency-Key': `web-${createUuid()}` },
            });

            if (! order.approval_url) {
                throw new Error('The payment provider did not return a secure checkout address.');
            }

            window.location.assign(order.approval_url);
        } catch (error) {
            setMessage(error.message, 'error');
        }
    }

    function showManualPayment(method) {
        const content = $('#portal-content');
        const instructions = method.instructions || {};
        const amount = `${method.amount?.amount || ''} ${method.amount?.currency || ''}`.trim();

        if (method.code === 'LOCAL_BANK') {
            content.innerHTML = `
                <div class="eyebrow">Manual payment</div><h2>${escapeHtml(method.display_name)}</h2>
                <div class="notice">Pay exactly ${escapeHtml(amount)} to ${escapeHtml(instructions.bank_name || 'the displayed bank')} (${escapeHtml(instructions.branch || 'branch')}). Account holder: ${escapeHtml(instructions.account_holder || '')}; account: ${escapeHtml(instructions.account_number || '')}. Submit your own deposit evidence only.</div>
                <form id="manual-bank-form" class="form-grid" enctype="multipart/form-data">
                    <label class="field"><span>Depositor name</span><input name="depositor_name" required minlength="2" maxlength="120"></label>
                    <label class="field"><span>Bank reference</span><input name="bank_reference" required minlength="2" maxlength="191"></label>
                    <label class="field"><span>Deposit date</span><input name="deposit_date" type="date" max="${new Date().toISOString().slice(0, 10)}" required></label>
                    <label class="field"><span>Proof (JPG, PNG or PDF; max 5 MB)</span><input name="proof_file" type="file" accept="image/jpeg,image/png,application/pdf" required></label>
                    <button class="primary" type="submit">Submit bank deposit for review</button>
                </form>`;
            $('#manual-bank-form', content).addEventListener('submit', submitManualBank);
            return;
        }

        if (method.code === 'USDT_TRC20') {
            content.innerHTML = `
                <div class="eyebrow">Manual payment</div><h2>${escapeHtml(method.display_name)}</h2>
                <div class="notice">Send exactly ${escapeHtml(amount)} ${escapeHtml(instructions.token || 'USDT')} on ${escapeHtml(instructions.network || 'the required network')} to: ${escapeHtml(instructions.wallet_address || '')}. Do not send from an unsupported network.</div>
                <form id="manual-usdt-form" class="form-grid">
                    <label class="field"><span>Transaction hash</span><input name="tx_hash" required pattern="[A-Fa-f0-9]{64}" maxlength="64"></label>
                    <label class="field"><span>Amount sent</span><input name="amount" required inputmode="decimal" placeholder="0.00"></label>
                    <label class="field"><span>Sender wallet (optional)</span><input name="sender_address" maxlength="100"></label>
                    <button class="primary" type="submit">Submit USDT transfer for review</button>
                </form>`;
            $('#manual-usdt-form', content).addEventListener('submit', submitManualUsdt);
            return;
        }

        content.innerHTML = `
            <div class="eyebrow">Manual payment</div><h2>${escapeHtml(method.display_name)}</h2>
            <p>This method requires an account-specific payment instruction. It is available only after the receiving account is configured for your country.</p>
            <button id="back-to-payments">Back to payment methods</button>`;
        $('#back-to-payments', content).addEventListener('click', () => loadView('purchase'));
    }

    async function submitManualBank(event) {
        event.preventDefault();

        try {
            await apiForm(
                '/api/v1/payments/bank-deposits',
                new FormData(event.currentTarget),
                { 'Idempotency-Key': `web-${createUuid()}` }
            );
            await loadView('purchase');
            setMessage('Your bank deposit evidence was submitted for TradeX review.', 'success');
        } catch (error) {
            setMessage(error.message, 'error');
        }
    }

    async function submitManualUsdt(event) {
        event.preventDefault();

        try {
            await apiForm(
                '/api/v1/payments/usdt-deposits',
                new FormData(event.currentTarget),
                { 'Idempotency-Key': `web-${createUuid()}` }
            );
            await loadView('purchase');
            setMessage('Your USDT transfer reference was submitted for TradeX review.', 'success');
        } catch (error) {
            setMessage(error.message, 'error');
        }
    }

    async function openBroker(id) {
        if (! id) {
            return;
        }

        try {
            const result = await api(
                `/api/v1/copy-trade/broker-markets/${encodeURIComponent(id)}/open-account-action`,
                { method: 'POST' }
            );

            if (! result.open_account_via_tradex_url) {
                throw new Error(result.notice || 'The selected broker is unavailable.');
            }

            sessionStorage.setItem(BROKER_INTENT, JSON.stringify(result));
            window.open(result.open_account_via_tradex_url, '_blank', 'noopener');
            await loadView('brokers');
        } catch (error) {
            setMessage(error.message, 'error');
        }
    }

    async function submitBrokerAccount(event) {
        event.preventDefault();
        const pending = parseSession(BROKER_INTENT);

        if (! pending?.onboarding_intent_uuid) {
            return;
        }

        try {
            const form = new FormData(event.currentTarget);
            const data = await api('/api/v1/copy-trade/broker-accounts', {
                method: 'POST',
                body: {
                    onboarding_intent_uuid: pending.onboarding_intent_uuid,
                    account_number: String(form.get('account_number') || '').trim(),
                },
            });
            sessionStorage.removeItem(BROKER_INTENT);
            await loadView('brokers');
            setMessage(`Broker account submitted. Status: ${data.broker_account?.status || 'PENDING_VERIFICATION'}.`, 'success');
        } catch (error) {
            setMessage(error.message, 'error');
        }
    }

    $$('.hero [data-screen]').forEach((button) => {
        button.addEventListener('click', () => {
            show('auth');
            $('#login-panel').classList.toggle('hidden', button.dataset.screen !== 'login');
            $('#register-panel').classList.toggle('hidden', button.dataset.screen !== 'register');

            if (registration) {
                showRegistration(registration.step);
            }
        });
    });

    $('#register-form').addEventListener('submit', async (event) => {
        event.preventDefault();

        try {
            const result = await api('/api/v1/auth/register/start', {
                method: 'POST',
                body: Object.fromEntries(new FormData(event.currentTarget)),
            });
            registration = {
                registration_id: result.registration_id,
                registration_token: result.registration_token,
            };
            showRegistration('email');
        } catch (error) {
            setMessage(error.message, 'error');
        }
    });

    $('#verification-form').addEventListener('submit', async (event) => {
        event.preventDefault();

        if (! registration) {
            return;
        }

        const code = $('#verification-code').value.trim();

        try {
            if (registration.step === 'email') {
                await api('/api/v1/auth/register/email/verify', {
                    method: 'POST',
                    body: { ...registration, otp_code: code },
                });
                showRegistration('whatsapp');
                return;
            }

            if (registration.step === 'whatsapp') {
                await api('/api/v1/auth/register/whatsapp/verify', {
                    method: 'POST',
                    body: { ...registration, otp_code: code },
                });
                const setup = await api('/api/v1/auth/register/totp/setup', {
                    method: 'POST',
                    body: registration,
                });
                showRegistration('totp', { qr_code_data_uri: setup.qr_code_data_uri });
                return;
            }

            await api('/api/v1/auth/register/totp/confirm', {
                method: 'POST',
                body: { ...registration, totp_code: code },
            });
            sessionStorage.removeItem(REGISTRATION);
            registration = null;
            $('#registration-flow').classList.add('hidden');
            $('#login-panel').classList.remove('hidden');
            setMessage('Your account is active. Sign in with your authenticator code.', 'success');
        } catch (error) {
            setMessage(error.message, 'error');
        }
    });

    $('#verification-resend').addEventListener('click', async () => {
        if (! registration) {
            return;
        }

        try {
            const endpoint = registration.step === 'email'
                ? '/api/v1/auth/register/email/resend'
                : '/api/v1/auth/register/whatsapp/resend';
            await api(endpoint, { method: 'POST', body: registration });
            setMessage('A new verification code was requested.', 'success');
        } catch (error) {
            setMessage(error.message, 'error');
        }
    });

    $('#login-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const values = Object.fromEntries(new FormData(event.currentTarget));

        try {
            const result = await api('/api/v1/auth/login', {
                method: 'POST',
                body: {
                    email: values.email,
                    password: values.password,
                    installation_id: installationId(),
                    platform: 'WEB',
                    device_name: navigator.userAgent.slice(0, 140),
                    device_model: navigator.platform || 'Web browser',
                    os_version: navigator.userAgent.slice(0, 90),
                    app_version: 'portal-1.1.0',
                    [values.mfa_type]: values.mfa,
                },
            });
            saveSession(result);
            show('portal');
            setMessage('Secure session established.', 'success');
        } catch (error) {
            setMessage(error.message, 'error');
        }
    });

    $('#signout').addEventListener('click', async () => {
        try {
            await api('/api/v1/auth/logout', { method: 'POST' });
        } catch {
            // The browser still clears its local session if it is already expired.
        }

        clearSession();
        show('landing');
    });

    $$('.side button').forEach((button) => {
        button.addEventListener('click', () => {
            currentView = button.dataset.view;
            $$('.side button').forEach((item) => item.classList.toggle('active', item === button));
            loadView(currentView);
        });
    });

    if (session) {
        show('portal');
    } else {
        show('landing');
    }

    if (registration) {
        show('auth');
        showRegistration(registration.step);
    }
})();

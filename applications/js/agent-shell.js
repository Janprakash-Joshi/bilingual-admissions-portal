(() => {
    const topBar = document.querySelector('.ad-top');
    if (!topBar) return;

    const menu = document.getElementById('sidebar');
    const drawer = document.querySelector('.ad-drawer-btn');
    const scrim = document.querySelector('.ad-scrim');
    const account = document.querySelector('.ad-account');
    const drawerClose = document.querySelector('.ad-drawer-close');

    function showMenu(open) {
        if (!menu || !scrim || !drawer) return;
        menu.classList.toggle('ad-open', open);
        drawer.setAttribute('aria-expanded', String(open));
        scrim.hidden = !open;
        if (open) drawerClose?.focus();
    }
    drawer?.addEventListener('click', () => showMenu(drawer.getAttribute('aria-expanded') !== 'true'));
    drawerClose?.addEventListener('click', () => { showMenu(false); drawer?.focus(); });
    scrim?.addEventListener('click', () => { showMenu(false); drawer?.focus(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Tab' && menu?.classList.contains('ad-open')) {
            const items = menu.querySelectorAll('a, button');
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
        if (event.key !== 'Escape') return;
        if (drawer?.getAttribute('aria-expanded') === 'true') {
            showMenu(false);
            drawer.focus();
        }
        if (account?.open) {
            account.open = false;
            account.querySelector('summary').focus();
        }
    });
    document.addEventListener('click', event => {
        if (account && !account.contains(event.target)) account.open = false;
    });
    window.matchMedia('(min-width: 901px)').addEventListener('change', () => showMenu(false));

    // On the existing application forms, update the shared header and menu in place.
    // A page reload could discard a chosen course, an entered value or an attached file.
    if (topBar.dataset.adLanguageMode === 'shell') {
        const links = topBar.querySelectorAll('[data-ad-lang]');
        let switching = false;
        links.forEach(link => link.addEventListener('click', async event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            if (switching || link.hasAttribute('aria-current')) return;
            switching = true;
            try {
                const response = await fetch('agent_shell_language?lang=' + link.dataset.adLang, {cache: 'no-store'});
                if (!response.ok) throw new Error('language');
                const {lang, labels} = await response.json();
                document.documentElement.lang = lang === 'ja' ? 'ja' : 'en';
                document.title = labels[topBar.dataset.adTitle] + ' · ' + labels.portal;
                document.querySelectorAll('[data-ad-label]').forEach(el => { el.textContent = labels[el.dataset.adLabel]; });
                document.querySelectorAll('[data-ad-aria]').forEach(el => { el.setAttribute('aria-label', labels[el.dataset.adAria]); });
                document.querySelectorAll('[data-ad-alt]').forEach(el => { el.alt = labels[el.dataset.adAlt]; });
                links.forEach(el => {
                    if (el.dataset.adLang === lang) el.setAttribute('aria-current', 'true');
                    else el.removeAttribute('aria-current');
                });
                Object.assign(window.agentShellLabels, labels);
                const url = new URL(location.href);
                if (url.searchParams.has('lang')) {
                    url.searchParams.set('lang', lang);
                    history.replaceState(history.state, '', url);
                }
            } catch {
                window.alert(window.agentShellLabels.languageFailed);
            } finally {
                switching = false;
            }
        }));
    }

    // Keep the existing file preview on document pages that actually have these controls.
    const fileInput = document.getElementById('Profile');
    const preview = document.getElementById('preview');
    if (fileInput && preview) {
        let objectUrl;
        fileInput.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            preview.replaceChildren();
            const file = fileInput.files[0];
            if (!file) return;
            const img = document.createElement('img');
            if (file.type.startsWith('image/')) {
                objectUrl = URL.createObjectURL(file);
                img.src = objectUrl;
                img.alt = window.agentShellLabels.filePreview;
            } else if (file.type === 'application/pdf') {
                img.src = '../img/pdf.png';
                img.alt = window.agentShellLabels.pdfPreview;
                img.style.width = '100px';
            } else {
                preview.textContent = window.agentShellLabels.badPreview;
                return;
            }
            preview.append(img);
        });
    }

    const notice = document.getElementById('globalNotifBox');
    if (!notice) return;
    let checking = false;
    async function checkNotices() {
        if (document.hidden || checking) return;
        checking = true;
        try {
            const response = await fetch('check_global_notifications', {cache: 'no-store'});
            if (response.status === 401 || response.status === 403) {
                location.replace('../index');
                return;
            }
            if (response.ok) notice.hidden = (await response.text()).trim() !== '1';
        } catch {
            // Keep the last confirmed state and retry on the next interval.
        } finally {
            checking = false;
        }
    }
    checkNotices();
    setInterval(checkNotices, 10000);
    document.addEventListener('visibilitychange', checkNotices);
})();

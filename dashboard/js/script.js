(() => {
    'use strict';
    const body = document.body;
    const words = JSON.parse(document.getElementById('admin-js-labels')?.textContent || '{}');
    const sidebar = document.getElementById('sidebar');
    const menuButton = document.querySelector('.admin-menu-button');
    const scrim = document.querySelector('.admin-scrim');
    const account = document.querySelector('.admin-account');
    const token = body.dataset.csrf || '';
    function setMenu(open) {
        sidebar?.classList.toggle('open', open);
        menuButton?.setAttribute('aria-expanded', String(open));
        if (scrim) scrim.hidden = !open;
    }
    window.toggleSidebar = () => setMenu(!sidebar?.classList.contains('open'));
    window.toggleDropdown = () => {
        if (account) account.open = !account.open;
    };
    menuButton?.addEventListener('click', window.toggleSidebar);
    scrim?.addEventListener('click', () => setMenu(false));
    document.addEventListener('click', (event) => {
        if (account?.open && !account.contains(event.target)) account.open = false;
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (sidebar?.classList.contains('open')) {
                setMenu(false);
                menuButton?.focus();
            }
            if (account?.open) {
                account.open = false;
                account.querySelector('summary')?.focus();
            }
        }
    });
    window.matchMedia('(min-width: 901px)').addEventListener('change', (e) => {
        if (e.matches) setMenu(false);
    });

    const post = (path, params) =>
        fetch(path, {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams({ ...params, csrf: token })
        });
    if (body.dataset.loginAlert === '1') post('alert.php', {}).catch(() => {});
    window.updateStatus = (status, applicationId) =>
        post('update_status', { status, application_id: applicationId })
            .then((r) => {
                if (!r.ok) throw Error();
            })
            .catch(() => window.alert(words.failed));

    function addToken(form) {
        if (!form || form.method.toLowerCase() !== 'post' || form.elements.namedItem('csrf')) return;
        const target = new URL(form.action || location.href, location.href);
        if (target.origin !== location.origin || !/\/(send|save_admin)(\.php)?$/.test(target.pathname))
            return;
        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'csrf';
        field.value = token;
        form.append(field);
    }
    document.querySelectorAll('form').forEach(addToken);
    let dirty = false;
    document.addEventListener('input', (e) => {
        if (e.target.form?.method === 'post') dirty = true;
    });
    document.querySelectorAll('.admin-languages a').forEach((a) =>
        a.addEventListener('click', (event) => {
            if (dirty && !window.confirm(words.discard)) event.preventDefault();
        })
    );
    document.addEventListener(
        'submit',
        (e) => {
            const form = e.target;
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                e.preventDefault();
                return;
            }
            addToken(form);
            if ((form.matches('.actionBtn') || e.submitter?.matches('.doc-btn')) && window.Swal) {
                Swal.fire({
                    title: e.submitter?.matches('.doc-btn') ? words.generating : words.sending,
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });
            }
        },
        true
    );
    document.getElementById('deleteRejectedBtn')?.addEventListener('click', async (event) => {
        if (!window.confirm(words.delete_confirm)) return;
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const response = await post('delete_rejected.php', {});
            if (!response.ok || !(await response.json()).success) throw Error();
            location.reload();
        } catch {
            window.alert(words.failed);
            button.disabled = false;
        }
    });

    document.querySelectorAll('[data-breakdown]').forEach((panel) => {
        const rows = [...panel.querySelectorAll('[data-label]')];
        const search = panel.querySelector('input[type=search]');
        const more = panel.querySelector('[data-expand]');
        if (!more) return;
        let expanded = false;
        function draw() {
            const term = (search?.value || '').trim().toLocaleLowerCase();
            let count = 0;
            rows.forEach((row) => {
                const matches = row.dataset.label.toLocaleLowerCase().includes(term);
                row.hidden = !matches || (!term && !expanded && count >= 6);
                if (matches) count++;
            });
            panel.querySelector('[data-no-match]').hidden = count > 0;
            more.hidden = !!term || count <= 6;
            more.textContent = expanded ? more.dataset.less : more.dataset.more;
            more.setAttribute('aria-expanded', String(expanded));
        }
        more.addEventListener('click', () => {
            expanded = !expanded;
            draw();
        });
        search?.addEventListener('input', draw);
        draw();
    });
})();

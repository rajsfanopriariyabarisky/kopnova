</div>

    <footer style="padding:16px 28px; border-top:1px solid #E2E8F0; background:#FFFFFF; color:#64748B; font-size:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
            <strong style="color:#064E3B;">KOPNOVA</strong> — Sistem Rantai Pasok &amp; Distribusi Koperasi Terpadu
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="width:7px; height:7px; border-radius:50%; background:#10B981; display:inline-block;"></span>
            <span>Sistem Aktif • <?= date('Y') ?></span>
        </div>
    </footer>
</main>

<script>
    function toggleKopSidebar(forceOpen) {
        const sb  = document.getElementById('mainSidebar');
        const bd  = document.getElementById('kopBackdrop');
        const btn = document.getElementById('kopMobileMenuBtn');
        if (!sb) return;

        const nextState = (typeof forceOpen === 'boolean')
            ? forceOpen
            : !sb.classList.contains('open');

        sb.classList.toggle('open', nextState);
        if (bd) bd.style.display = nextState ? 'block' : 'none';
        if (btn) btn.setAttribute('aria-expanded', nextState ? 'true' : 'false');

        if (window.innerWidth <= 960) {
            document.body.style.overflow = nextState ? 'hidden' : '';
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            toggleKopSidebar(false);
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 960) {
            toggleKopSidebar(false);
            document.body.style.overflow = '';
        }
    });

    // Otomatis posisikan item menu aktif agar terlihat saat sidebar di-scroll
    (function () {
        const activeLink = document.querySelector('#mainSidebar .kop-nav-link.active');
        if (activeLink) {
            activeLink.scrollIntoView({ block: 'nearest' });
        }
    })();
</script>
</body>
</html>
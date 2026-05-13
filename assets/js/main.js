// ============================================================
// Referral Network Portal — Main JS
// ============================================================

// Notification dropdown toggle
document.addEventListener('DOMContentLoaded', function () {

    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
        });

        document.addEventListener('click', function () {
            notifDropdown.classList.remove('show');
        });

        notifDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    // File upload drag-and-drop
    const uploadZone = document.getElementById('uploadZone');
    const fileInput  = document.getElementById('documentFile');

    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', function () {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', function (e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                showFileName(e.dataTransfer.files[0].name);
            }
        });

        fileInput.addEventListener('change', function () {
            if (fileInput.files.length > 0) {
                showFileName(fileInput.files[0].name);
            }
        });

        function showFileName(name) {
            const p = uploadZone.querySelector('p');
            if (p) p.innerHTML = '<i class="bi bi-file-earmark-check" style="color:#059669"></i> ' + name;
        }
    }

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(function (el) {
        setTimeout(function () {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.5s';
            setTimeout(function () { el.remove(); }, 500);
        }, 5000);
    });

    // Confirm dangerous actions
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // Estimated value → live commission preview
    const estInput   = document.getElementById('estimated_amount');
    const commPreview = document.getElementById('commissionPreview');
    const tierRate    = parseFloat(document.getElementById('tierRate')?.value || 0);

    if (estInput && commPreview && tierRate) {
        estInput.addEventListener('input', function () {
            const broker = parseFloat(estInput.value) * 0.01; // assume ~1% upfront
            const comm   = broker * (tierRate / 100);
            commPreview.textContent = '$' + comm.toFixed(2) + ' (est.)';
        });
    }
});

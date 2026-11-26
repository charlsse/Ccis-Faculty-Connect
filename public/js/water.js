document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-gateway-start]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.submitting === 'yes') { event.preventDefault(); return; }
        form.dataset.submitting = 'yes';
        const button = form.querySelector('button'); button.disabled = true; button.textContent = 'Opening secure checkout…';
    }));
    window.addEventListener('pageshow', () => document.querySelectorAll('[data-gateway-start]').forEach(form => {
        delete form.dataset.submitting; const button = form.querySelector('button'); button.disabled = false; button.textContent = 'Continue to Payment';
    }));
    document.querySelectorAll('[data-proof-drop]').forEach(zone => {
        const input = zone.querySelector('[data-proof-input]');
        const preview = zone.querySelector('[data-proof-preview]');
        const remove = zone.querySelector('[data-proof-remove]');
        const replace = zone.querySelector('[data-proof-replace]');
        let imageUrl;
        const render = () => {
            if (imageUrl) URL.revokeObjectURL(imageUrl);
            preview.replaceChildren(); input.setCustomValidity('');
            const file = input.files[0];
            remove.hidden = replace.hidden = !file;
            if (!file) return;
            const caption = document.createElement('p'); caption.textContent = file.name;
            preview.append(caption);
            if (!['image/jpeg', 'image/png', 'application/pdf'].includes(file.type) || file.size > 5 * 1024 * 1024) {
                input.setCustomValidity('Choose a JPEG, PNG or PDF no larger than 5 MB.');
                caption.textContent = input.validationMessage; return;
            }
            if (file.type.startsWith('image/')) {
                imageUrl = URL.createObjectURL(file);
                const img = document.createElement('img'); img.src = imageUrl; img.alt = 'Selected receipt preview'; preview.append(img);
            } else { caption.append(' — PDF selected. Check the file before submitting.'); }
        };
        input.addEventListener('change', render);
        remove.addEventListener('click', () => { input.value = ''; render(); input.focus(); });
        replace.addEventListener('click', () => input.click());
        zone.addEventListener('dragover', event => { event.preventDefault(); zone.classList.add('is-dragging'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-dragging'));
        zone.addEventListener('drop', event => {
            event.preventDefault(); zone.classList.remove('is-dragging');
            if (event.dataTransfer.files.length === 1) { input.files = event.dataTransfer.files; render(); }
        });
    });
    const confirmForm = (form, summary) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'water-dialog';
        const title = document.createElement('h2');
        title.textContent = 'Confirm payment action';
        title.id = 'water-confirm-title';
        dialog.setAttribute('aria-labelledby', title.id);
        const text = document.createElement('pre');
        text.textContent = summary;
        const actions = document.createElement('div');
        actions.className = 'water-actions';
        const cancel = document.createElement('button');
        cancel.type = 'button'; cancel.textContent = 'Go back';
        const submit = document.createElement('button');
        submit.type = 'button'; submit.textContent = 'Confirm and submit';
        submit.className = 'button button-primary';
        cancel.onclick = () => dialog.close();
        submit.onclick = () => { form.dataset.confirmed = 'yes'; dialog.close(); form.requestSubmit(); };
        actions.append(cancel, submit); dialog.append(title, text, actions);
        const previousFocus = document.activeElement;
        dialog.addEventListener('close', () => { dialog.remove(); previousFocus?.focus(); });
        document.body.append(dialog); dialog.showModal(); cancel.focus();
    };
    document.querySelectorAll('[data-water-confirm], [data-water-summary]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.confirmed === 'yes') {
                form.querySelectorAll('button[type=submit], button:not([type])').forEach(button => { button.disabled = true; button.textContent = 'Saving…'; });
                return;
            }
            event.preventDefault();
            let summary = form.dataset.waterConfirm || '';
            if (form.hasAttribute('data-water-summary')) {
                const data = new FormData(form);
                summary = `Amount: PHP ${data.get('submitted_amount')}\nPayment date: ${data.get('payment_date')}\nPayment time: ${data.get('payment_time')}\n`;
                if (data.has('reference_number')) summary += `Reference: ${data.get('reference_number')}\nProof: ${data.get('proof')?.name || 'None'}\n\nThis submission will await manual verification. It is not a payment confirmation.`;
                else summary += '\nThis records received cash and generates a verified receipt.';
            }
            confirmForm(form, summary);
        });
    });
    document.querySelectorAll('[data-water-copy]').forEach(button => button.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(document.getElementById(button.dataset.waterCopy).textContent); button.textContent = 'Copied'; }
        catch { button.textContent = 'Select the reference to copy'; }
    }));
    document.querySelectorAll('[data-water-print]').forEach(button => button.addEventListener('click', () => window.print()));
});

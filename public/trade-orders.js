(function () {
    'use strict';

    const config = window.TradeOrdersConfig || {};
    const actionUrl = config.basePath + 'trade/orders/action/';

    function text(tag, value, className) {
        const el = document.createElement(tag);
        el.textContent = value;
        if (className) el.className = className;
        return el;
    }

    function summaryLine(label, value) {
        const line = document.createElement('div');
        line.className = 'd-flex justify-content-between';
        line.appendChild(text('span', label, 'text-muted'));
        line.appendChild(text('span', value));
        return line;
    }

    function render(data) {
        const order = data.order;
        const body = document.getElementById('orderModalBody');
        body.replaceChildren();

        document.getElementById('orderModalTitle').textContent = order.reference + ' - ' + order.statusLabel;

        const who = document.createElement('div');
        who.className = 'mb-3';
        who.appendChild(text('div', order.company, 'fw-semibold'));
        [order.contact, order.email, order.phone].filter(Boolean).forEach(function (line) {
            who.appendChild(text('div', line, 'small text-muted'));
        });
        body.appendChild(who);

        const facts = document.createElement('div');
        facts.className = 'mb-3';
        facts.appendChild(summaryLine('Submitted', order.submitted));
        facts.appendChild(summaryLine('Customer PO', order.poReference || '-'));
        facts.appendChild(summaryLine('Payment terms', order.paymentTerms));
        facts.appendChild(summaryLine('Trade discount', order.discount));
        body.appendChild(facts);

        const table = document.createElement('table');
        table.className = 'table table-sm';
        const head = table.createTHead().insertRow();
        ['Product', 'Qty', 'Unit (ex tax)', 'Line (ex tax)'].forEach(function (label) {
            head.appendChild(text('th', label));
        });
        const tbody = table.createTBody();
        data.items.forEach(function (item) {
            const row = tbody.insertRow();
            const product = row.insertCell();
            product.appendChild(text('div', item.title));
            if (item.reference) product.appendChild(text('div', item.reference, 'small text-muted'));
            row.insertCell().textContent = item.quantity;
            row.insertCell().textContent = item.unit;
            row.insertCell().textContent = item.line;
        });
        body.appendChild(table);

        const totals = document.createElement('div');
        totals.className = 'ms-auto';
        totals.style.maxWidth = '16rem';
        totals.appendChild(summaryLine('Subtotal', order.subtotal));
        totals.appendChild(summaryLine('Tax', order.tax));
        totals.appendChild(text('div', '', 'border-top my-1'));
        const total = summaryLine('Total', order.total);
        total.classList.add('fw-semibold');
        totals.appendChild(total);
        body.appendChild(totals);
    }

    async function view(id) {
        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'details', id: id, csrf_token: window.csrfToken })
            });
            const result = await response.json();
            if (!result.success) {
                adminAlert(result.error || 'Could not load this order');
                return;
            }
            render(result);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('orderModal')).show();
        } catch (error) {
            adminAlert('An error occurred');
        }
    }

    document.addEventListener('list:action', function (e) {
        if (e.detail.action === 'view') view(e.detail.id);
    });
})();

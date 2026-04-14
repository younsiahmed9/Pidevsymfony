let balance = 0;
const balanceDisplay = document.getElementById('balance');
const form = document.getElementById('transaction-form');
const amountInput = document.getElementById('amount');
const typeSelect = document.getElementById('transaction-type');
const historyList = document.getElementById('history-list');

function updateBalanceDisplay() {
    balanceDisplay.textContent = `$${balance.toFixed(2)}`;
}

function addHistory(amount, type) {
    const li = document.createElement('li');
    li.className = type === 'withdraw' ? 'withdraw' : '';
    li.innerHTML = `<span>${type === 'add' ? '+' : '-'}$${amount.toFixed(2)}</span> <span>${type === 'add' ? 'Added' : 'Withdrawn'}</span>`;
    historyList.prepend(li);
}

form.addEventListener('submit', function(e) {
    e.preventDefault();
    const amount = parseFloat(amountInput.value);
    const type = typeSelect.value;
    if (isNaN(amount) || amount <= 0) {
        alert('Please enter a valid amount.');
        return;
    }
    if (type === 'add') {
        balance += amount;
        addHistory(amount, 'add');
    } else if (type === 'withdraw') {
        if (amount > balance) {
            alert('Insufficient balance.');
            return;
        }
        balance -= amount;
        addHistory(amount, 'withdraw');
    }
    updateBalanceDisplay();
    form.reset();
});

updateBalanceDisplay();

// SPA navigation logic
const pages = {
    overview: document.querySelector('.dashboard-row'),
    wallet: null, // Placeholder for wallet page
    debit: null, // Placeholder for debit card page
    analytics: null, // Placeholder for analytics page
    log: null // Placeholder for log activity page
};

function showPage(page) {
    // Hide all dashboard rows (main content)
    document.querySelectorAll('.dashboard-row, .wallet-page, .debit-page, .analytics-page, .log-page').forEach(el => {
        el.style.display = 'none';
    });
    // Show the selected page
    if (page === 'overview') {
        document.querySelectorAll('.dashboard-row').forEach(el => el.style.display = 'flex');
    } else {
        let pageClass = `.${page}-page`;
        let pageEl = document.querySelector(pageClass);
        if (pageEl) pageEl.style.display = 'block';
    }
    // Update active menu
    document.querySelectorAll('.main-menu ul li').forEach((li, idx) => {
        li.classList.remove('active');
        if (
            (page === 'overview' && idx === 0) ||
            (page === 'wallet' && idx === 1) ||
            (page === 'debit' && idx === 2) ||
            (page === 'analytics' && idx === 3) ||
            (page === 'log' && idx === 4)
        ) {
            li.classList.add('active');
        }
    });
}

// Add event listeners to sidebar menu
const menuItems = document.querySelectorAll('.main-menu ul li');
menuItems[0].addEventListener('click', () => showPage('overview'));
menuItems[1].addEventListener('click', () => showPage('wallet'));
menuItems[2].addEventListener('click', () => showPage('debit'));
menuItems[3].addEventListener('click', () => showPage('analytics'));
menuItems[4].addEventListener('click', () => showPage('log'));

// Add placeholder pages to DOM if not present
function addDynamicPages() {
    const main = document.querySelector('.main-content main');
    if (!document.querySelector('.wallet-page')) {
        const wallet = document.createElement('div');
        wallet.className = 'wallet-page';
        wallet.style.display = 'none';
        wallet.innerHTML = '<h2>Wallet</h2><p>Wallet details and actions here.</p>';
        main.appendChild(wallet);
    }
    if (!document.querySelector('.debit-page')) {
        const debit = document.createElement('div');
        debit.className = 'debit-page';
        debit.style.display = 'none';
        debit.innerHTML = '<h2>Debit Card</h2><p>Debit card management here.</p>';
        main.appendChild(debit);
    }
    if (!document.querySelector('.analytics-page')) {
        const analytics = document.createElement('div');
        analytics.className = 'analytics-page';
        analytics.style.display = 'none';
        analytics.innerHTML = '<h2>Analytics</h2><p>Analytics and charts here.</p>';
        main.appendChild(analytics);
    }
    if (!document.querySelector('.log-page')) {
        const log = document.createElement('div');
        log.className = 'log-page';
        log.style.display = 'none';
        log.innerHTML = '<h2>Log Activity</h2><p>Activity logs here.</p>';
        main.appendChild(log);
    }
}
addDynamicPages();
showPage('overview');

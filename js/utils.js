function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU');
}

function formatMoney(amount) {
    if (amount === null || amount === undefined) return '0,00 Br';
    return new Intl.NumberFormat('ru-RU', {
        style: 'currency',
        currency: 'BYN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount).replace('BYN', 'Br').trim();
}

function formatPhone(phone) {
    if (!phone) return '-';
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 12) {
        return `+${cleaned.slice(0,3)} (${cleaned.slice(3,5)}) ${cleaned.slice(5,8)}-${cleaned.slice(8,10)}-${cleaned.slice(10,12)}`;
    }
    return phone;
}

function getStatusText(status) {
    const statuses = {
        'active': 'Активен',
        'draft': 'Черновик',
        'expired': 'Истек',
        'terminated': 'Расторгнут'
    };
    return statuses[status] || status;
}

function getStatusClass(status) {
    const classes = {
        'active': 'badge-success',
        'draft': 'badge-warning',
        'expired': 'badge-danger',
        'terminated': 'badge-secondary'
    };
    return classes[status] || 'badge-secondary';
}

function escapeHtml(text) {
    if (!text) return text;
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    } else {
        alert(message);
    }
}

function showSuccess(message) {
    const successDiv = document.getElementById('successMessage');
    if (successDiv) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
        setTimeout(() => {
            successDiv.style.display = 'none';
        }, 3000);
    } else {
        alert(message);
    }
}

function checkAuth() {
    const user = localStorage.getItem('user');
    if (!user) {
        window.location.href = 'login.html';
        return null;
    }
    return JSON.parse(user);
}

function logout() {
    localStorage.removeItem('user');
    window.location.href = 'login.html';
}

function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const result = {};
    for (const [key, value] of params) {
        result[key] = value;
    }
    return result;
}

function getCurrentDate() {
    const date = new Date();
    return date.toISOString().split('T')[0];
}

function getDateAfterDays(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString().split('T')[0];
}

function downloadFile(content, fileName, contentType) {
    const blob = new Blob([content], { type: contentType });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = fileName;
    link.click();
    URL.revokeObjectURL(link.href);
}

function exportToCSV(data, filename) {
    if (!data || data.length === 0) {
        showError('Нет данных для экспорта');
        return;
    }
    
    const headers = Object.keys(data[0]);
    const csvRows = [];
    
    csvRows.push(headers.join(';'));
    
    for (const row of data) {
        const values = headers.map(header => {
            const val = row[header] || '';
            return `"${val.toString().replace(/"/g, '""')}"`;
        });
        csvRows.push(values.join(';'));
    }
    
    downloadFile(csvRows.join('\n'), filename, 'text/csv;charset=utf-8;');
}
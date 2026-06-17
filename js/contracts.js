async function loadContracts() {
    try {
        const response = await fetch('api/contracts.php');
        const contracts = await response.json();
        displayContracts(contracts);
    } catch (error) {
        showError('Ошибка загрузки договоров');
    }
}

function displayContracts(contracts) {
    const tbody = document.getElementById('contractsList');
    if (!tbody) return;
    
    if (contracts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Нет данных</td></tr>';
        return;
    }
    
    const today = new Date();
    
    tbody.innerHTML = contracts.map(c => {
        const expDate = c.expiration_date ? new Date(c.expiration_date) : null;
        const daysLeft = expDate ? Math.floor((expDate - today) / (1000 * 60 * 60 * 24)) : null;
        
        return `
        <tr>
            <td><strong>${escapeHtml(c.number) || 'Б/Н'}</strong></td>
            <td>${escapeHtml(c.agency_name) || '-'}</td>
            <td>${c.signing_date || '-'}</td>
            <td>
                ${c.expiration_date || 'Бессрочно'}
                ${c.status === 'active' && daysLeft && daysLeft < 30 ? 
                    `<span class="badge badge-warning">${daysLeft} дн.</span>` : ''}
            </td>
            <td>${formatMoney(c.total_amount)}</td>
            <td><span class="badge ${getStatusClass(c.status)}">${getStatusText(c.status)}</span></td>
            <td class="actions">
                <button class="btn btn-sm btn-info" onclick="viewContract(${c.id})">👁️</button>
                <button class="btn btn-sm btn-warning" onclick="editContract(${c.id})">✏️</button>
                <button class="btn btn-sm btn-primary" onclick="printContract(${c.id})">🖨️</button>
                <button class="btn btn-sm btn-danger" onclick="deleteContract(${c.id})">🗑️</button>
            </td>
        </tr>
    `}).join('');
}

function viewContract(id) {
    window.location.href = `contract-view.html?id=${id}`;
}

function editContract(id) {
    window.location.href = `contract-edit.html?id=${id}`;
}

function printContract(id) {
    window.open(`contract-print.html?id=${id}`, '_blank');
}

async function loadAgenciesSelect() {
    try {
        const response = await fetch('api/agencies.php');
        const agencies = await response.json();
        
        const select = document.getElementById('agencyId');
        if (!select) return;
        
        select.innerHTML = '<option value="">Выберите агентство</option>' +
            agencies.filter(a => a.is_active).map(a => 
                `<option value="${a.id}">${escapeHtml(a.name)}</option>`
            ).join('');
    } catch (error) {
        showError('Ошибка загрузки агентств');
    }
}

async function loadTemplatesSelect() {
    try {
        const response = await fetch('api/templates.php');
        const templates = await response.json();
        
        const select = document.getElementById('templateId');
        if (!select) return;
        
        select.innerHTML = '<option value="">Выберите шаблон</option>' +
            templates.map(t => 
                `<option value="${t.id}">${escapeHtml(t.name)}</option>`
            ).join('');
    } catch (error) {
        showError('Ошибка загрузки шаблонов');
    }
}

async function loadServices() {
    try {
        const response = await fetch('api/services.php');
        const services = await response.json();
        
        const container = document.getElementById('servicesContainer');
        if (!container) return;
        
        container.innerHTML = services.map(s => `
            <div class="service-item">
                <label class="checkbox">
                    <input type="checkbox" class="service-checkbox" value="${s.id}" data-price="${s.default_price || 0}">
                    <span>${escapeHtml(s.name)}</span>
                </label>
                <div class="service-details" style="display: none; margin-left: 30px; margin-top: 10px;">
                    <input type="number" class="service-price form-control" placeholder="Цена, Br" value="${s.default_price || 0}" step="0.01" min="0">
                    <input type="number" class="service-quantity form-control" placeholder="Количество" value="1" min="1">
                    <input type="text" class="service-description form-control" placeholder="Описание">
                </div>
            </div>
        `).join('');
        
        document.querySelectorAll('.service-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const details = this.closest('.service-item').querySelector('.service-details');
                if (details) {
                    details.style.display = this.checked ? 'block' : 'none';
                }
                calculateTotal();
            });
        });
        
        document.querySelectorAll('.service-price, .service-quantity').forEach(input => {
            input.addEventListener('input', calculateTotal);
        });
        
    } catch (error) {
        showError('Ошибка загрузки услуг');
    }
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.service-item').forEach(item => {
        const checkbox = item.querySelector('.service-checkbox');
        if (checkbox && checkbox.checked) {
            const price = parseFloat(item.querySelector('.service-price')?.value) || 0;
            const quantity = parseInt(item.querySelector('.service-quantity')?.value) || 1;
            total += price * quantity;
        }
    });
    
    const totalInput = document.getElementById('totalAmount');
    if (totalInput) {
        totalInput.value = total.toFixed(2);
    }
}

// Сохранение договора
async function saveContract() {
    const services = [];
    document.querySelectorAll('.service-item').forEach(item => {
        const checkbox = item.querySelector('.service-checkbox');
        if (checkbox && checkbox.checked) {
            services.push({
                service_id: checkbox.value,
                price: parseFloat(item.querySelector('.service-price')?.value) || 0,
                quantity: parseInt(item.querySelector('.service-quantity')?.value) || 1,
                description: item.querySelector('.service-description')?.value || ''
            });
        }
    });
    
    const id = document.getElementById('contractId')?.value;
    const data = {
        id: id || undefined,
        number: document.getElementById('number')?.value,
        agency_id: document.getElementById('agencyId').value,
        template_id: document.getElementById('templateId').value,
        user_id: JSON.parse(localStorage.getItem('user')).id,
        signing_date: document.getElementById('signingDate').value,
        expiration_date: document.getElementById('expirationDate').value,
        status: document.getElementById('status').value,
        total_amount: parseFloat(document.getElementById('totalAmount').value) || 0,
        terms: document.getElementById('terms')?.value,
        notes: document.getElementById('notes')?.value,
        services: services
    };
    
    if (!data.agency_id) {
        showError('Выберите агентство');
        return;
    }
    
    const method = id ? 'PUT' : 'POST';
    
    try {
        const response = await fetch('api/contracts.php', {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(id ? 'Договор обновлен' : 'Договор создан');
            setTimeout(() => {
                window.location.href = 'contracts.html';
            }, 1000);
        } else {
            showError(result.error || 'Ошибка сохранения');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}

// Удаление договора
async function deleteContract(id) {
    if (!confirm('Вы уверены, что хотите удалить договор?')) return;
    
    try {
        const response = await fetch(`api/contracts.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Договор удален');
            loadContracts();
        } else {
            showError(result.error || 'Ошибка удаления');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}

function generateContractNumber() {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    document.getElementById('number').value = `Д-${year}-${month}-${random}`;
}
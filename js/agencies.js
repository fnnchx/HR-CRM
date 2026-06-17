async function loadAgencies() {
    try {
        const response = await fetch('api/agencies.php');
        const agencies = await response.json();
        displayAgencies(agencies);
    } catch (error) {
        showError('Ошибка загрузки агентств');
    }
}

function displayAgencies(agencies) {
    const tbody = document.getElementById('agenciesList');
    if (!tbody) return;
    
    if (agencies.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Нет данных</td></tr>';
        return;
    }
    
    tbody.innerHTML = agencies.map(a => `
        <tr>
            <td><strong>${escapeHtml(a.name)}</strong></td>
            <td>${escapeHtml(a.email) || '-'}</td>
            <td>${escapeHtml(a.phone) ? formatPhone(a.phone) : '-'}</td>
            <td>${escapeHtml(a.contact_person) || '-'}</td>
            <td><span class="badge badge-info">${a.active_contracts || 0}</span></td>
            <td>
                <span class="badge ${a.is_active ? 'badge-success' : 'badge-secondary'}">
                    ${a.is_active ? 'Активно' : 'Неактивно'}
                </span>
            </td>
            <td class="actions">
                <button class="btn btn-sm btn-info" onclick="viewAgency(${a.id})">👁️</button>
                <button class="btn btn-sm btn-warning" onclick="editAgency(${a.id})">✏️</button>
                <button class="btn btn-sm btn-secondary" onclick="historyAgency(${a.id})">📋</button>
                <button class="btn btn-sm btn-danger" onclick="deleteAgency(${a.id})">🗑️</button>
            </td>
        </tr>
    `).join('');
}

function viewAgency(id) {
    window.location.href = `agency-view.html?id=${id}`;
}

function editAgency(id) {
    window.location.href = `agency-edit.html?id=${id}`;
}

function historyAgency(id) {
    window.location.href = `agency-history.html?id=${id}`;
}

async function saveAgency() {
    const id = document.getElementById('agencyId')?.value;
    const data = {
        id: id || undefined,
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        contact_person: document.getElementById('contactPerson').value,
        contact_phone: document.getElementById('contactPhone').value,
        is_active: document.getElementById('isActive')?.checked ? 1 : 0
    };
    
    if (!data.name) {
        showError('Название агентства обязательно');
        return;
    }
    
    const method = id ? 'PUT' : 'POST';
    
    try {
        const response = await fetch('api/agencies.php', {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(id ? 'Агентство обновлено' : 'Агентство добавлено');
            setTimeout(() => {
                window.location.href = 'agencies.html';
            }, 1000);
        } else {
            showError(result.error || 'Ошибка сохранения');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}

async function deleteAgency(id) {
    if (!confirm('Вы уверены, что хотите удалить это агентство?')) return;
    
    try {
        const response = await fetch(`api/agencies.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Агентство удалено');
            loadAgencies();
        } else {
            showError(result.error || 'Ошибка удаления');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}

async function loadRequisites(agencyId) {
    try {
        const response = await fetch(`api/requisites.php?agency_id=${agencyId}`);
        const requisites = await response.json();
        
        const tbody = document.getElementById('requisitesList');
        if (!tbody) return;
        
        if (requisites.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">Нет данных</td></tr>';
            return;
        }
        
        tbody.innerHTML = requisites.map(r => `
            <tr>
                <td>${escapeHtml(r.bank_name) || '-'}</td>
                <td>${escapeHtml(r.unp) || '-'}</td>
                <td>${escapeHtml(r.okpo) || '-'}</td>
                <td>${r.valid_from || '-'}</td>
                <td>${r.valid_to || 'По настоящее время'}</td>
                <td>
                    ${!r.valid_to ? '<span class="badge badge-success">Текущие</span>' : ''}
                </td>
            </tr>
        `).join('');
        
    } catch (error) {
        showError('Ошибка загрузки реквизитов');
    }
}

async function saveRequisites() {
    const data = {
        agency_id: document.getElementById('agencyId').value,
        bank_name: document.getElementById('bankName').value,
        unp: document.getElementById('unp').value,
        okpo: document.getElementById('okpo').value,
        bic: document.getElementById('bic').value,
        account_number: document.getElementById('accountNumber').value,
        legal_address: document.getElementById('legalAddress').value,
        director_name: document.getElementById('directorName').value,
        valid_from: document.getElementById('validFrom').value || getCurrentDate(),
        valid_to: document.getElementById('validTo').value || null,
        is_current: document.getElementById('isCurrent')?.checked || false
    };
    
    try {
        const response = await fetch('api/requisites.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Реквизиты добавлены');
            loadRequisites(data.agency_id);
            
            document.getElementById('requisitesForm')?.reset();
        } else {
            showError(result.error || 'Ошибка сохранения');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }

async function loadAgencyForEdit(id) {
    try {
        const response = await fetch(`api/agencies.php?id=${id}`);
        const agency = await response.json();
        
        if (agency) {
            document.getElementById('agencyId').value = agency.id;
            document.getElementById('name').value = agency.name || '';
            document.getElementById('email').value = agency.email || '';
            document.getElementById('phone').value = agency.phone || '';
            document.getElementById('contactPerson').value = agency.contact_person || '';
            document.getElementById('contactPhone').value = agency.contact_phone || '';
            document.getElementById('isActive').checked = agency.is_active == 1;
        }
    } catch (error) {
        showError('Ошибка загрузки данных агентства');
    }
}

async function updateAgency() {
    const id = document.getElementById('agencyId').value;
    const data = {
        id: id,
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        contact_person: document.getElementById('contactPerson').value,
        contact_phone: document.getElementById('contactPhone').value,
        is_active: document.getElementById('isActive').checked ? 1 : 0
    };
    
    if (!data.name) {
        showError('Название агентства обязательно');
        return;
    }
    
    try {
        const response = await fetch('api/agencies.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Агентство обновлено');
            setTimeout(() => {
                window.location.href = 'agencies.html';
            }, 1000);
        } else {
            showError(result.error || 'Ошибка обновления');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}
}
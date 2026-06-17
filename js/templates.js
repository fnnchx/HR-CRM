async function loadTemplates() {
    try {
        const response = await fetch('api/templates.php');
        const templates = await response.json();
        displayTemplates(templates);
    } catch (error) {
        showError('Ошибка загрузки шаблонов');
    }
}

function displayTemplates(templates) {
    const tbody = document.getElementById('templatesList');
    if (!tbody) return;
    
    if (templates.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Нет данных</td></tr>';
        return;
    }
    
    tbody.innerHTML = templates.map(t => `
        <tr>
            <td><strong>${escapeHtml(t.name)}</strong></td>
            <td>${escapeHtml(t.type) || 'Стандартный'}</td>
            <td>${t.created_at ? formatDate(t.created_at) : '-'}</td>
            <td>
                <span class="badge ${t.is_active ? 'badge-success' : 'badge-secondary'}">
                    ${t.is_active ? 'Активен' : 'Неактивен'}
                </span>
            </td>
            <td class="actions">
                <button class="btn btn-sm btn-info" onclick="viewTemplate(${t.id})">👁️</button>
                <button class="btn btn-sm btn-warning" onclick="editTemplate(${t.id})">✏️</button>
                <button class="btn btn-sm btn-danger" onclick="deleteTemplate(${t.id})">🗑️</button>
            </td>
        </tr>
    `).join('');
}

function viewTemplate(id) {
    window.location.href = `template-view.html?id=${id}`;
}

function editTemplate(id) {
    window.location.href = `template-edit.html?id=${id}`;
}

async function loadTemplate(id) {
    try {
        const response = await fetch(`api/templates.php?id=${id}`);
        const template = await response.json();
        
        if (template) {
            document.getElementById('templateId').value = template.id;
            document.getElementById('name').value = template.name || '';
            document.getElementById('type').value = template.type || 'standard';
            document.getElementById('content').value = template.content || '';
        }
    } catch (error) {
        showError('Ошибка загрузки шаблона');
    }
}

async function saveTemplate() {
    const id = document.getElementById('templateId')?.value;
    const data = {
        id: id || undefined,
        name: document.getElementById('name').value,
        type: document.getElementById('type').value,
        content: document.getElementById('content').value
    };
    
    if (!data.name || !data.content) {
        showError('Заполните все обязательные поля');
        return;
    }
    
    const method = id ? 'PUT' : 'POST';
    
    try {
        const response = await fetch('api/templates.php', {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess(id ? 'Шаблон обновлен' : 'Шаблон создан');
            setTimeout(() => {
                window.location.href = 'templates.html';
            }, 1000);
        } else {
            showError(result.error || 'Ошибка сохранения');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}

async function deleteTemplate(id) {
    if (!confirm('Вы уверены, что хотите удалить шаблон?')) return;
    
    try {
        const response = await fetch(`api/templates.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Шаблон удален');
            loadTemplates();
        } else {
            showError(result.error || 'Ошибка удаления');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}

function previewTemplate() {
    const content = document.getElementById('content').value;
    const preview = document.getElementById('preview');
    
    if (!preview) return;
    
    let previewContent = content
        .replace(/{{number}}/g, 'Д-2026-01-001')
        .replace(/{{date}}/g, formatDate(getCurrentDate()))
        .replace(/{{agency_name}}/g, 'ООО "Кадровое агентство"')
        .replace(/{{amount}}/g, '1 500,00 Br')
        .replace(/{{expiration_date}}/g, '31.12.2026');
    
    preview.innerHTML = `<div class="template-preview">${previewContent.replace(/\n/g, '<br>')}</div>`;
}
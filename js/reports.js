async function loadExpiringReport() {
    const days = document.getElementById('expiringDays')?.value || 30;
    
    try {
        const response = await fetch(`api/reports.php?type=expiring&days=${days}`);
        const data = await response.json();
        
        window.expiringData = data;
        
        let html = `
            <div class="report-header">
                <h3>Договоры, истекающие в ближайшие ${days} дней</h3>
                <p>По состоянию на ${formatDate(getCurrentDate())}</p>
            </div>
        `;
        
        if (data.error) {
            html += `<p class="alert alert-error">${data.error}</p>`;
        } else if (data.length === 0) {
            html += '<p class="alert alert-info">Нет истекающих договоров</p>';
        } else {
            html += '<div style="overflow-x: auto;"><table class="table"><thead><tr>';
            html += '<th>Номер</th><th>Агентство</th><th>Дата истечения</th><th>Осталось дней</th><th>Сумма</th>';
            html += '</tr></thead><tbody>';
            
            let total = 0;
            data.forEach(item => {
                total += parseFloat(item.total_amount) || 0;
                html += `<tr>
                    <td>${escapeHtml(item.number) || 'Б/Н'}</td>
                    <td>${escapeHtml(item.agency_name)}</td>
                    <td>${item.expiration_date}</td>
                    <td><span class="badge badge-warning">${item.days_left} дн.</span></td>
                    <td>${formatMoney(item.total_amount)}</td>
                </tr>`;
            });
            
            html += `<tr class="total-row">
                <td colspan="4"><strong>ИТОГО:</strong></td>
                <td><strong>${formatMoney(total)}</strong></td>
            </tr>`;
            
            html += '</tbody></table></div>';
        }
        
        document.getElementById('expiringReport').innerHTML = html;
        
    } catch (error) {
        document.getElementById('expiringReport').innerHTML = '<p class="alert alert-error">Ошибка загрузки отчета</p>';
    }
}

async function loadAgencyReport() {
    try {
        const response = await fetch('api/reports.php?type=by_agency');
        const data = await response.json();
        
        window.agencyData = data;
        
        let html = `
            <div class="report-header">
                <h3>Статистика по агентствам</h3>
                <p>По состоянию на ${formatDate(getCurrentDate())}</p>
            </div>
        `;
        
        if (data.error) {
            html += `<p class="alert alert-error">${data.error}</p>`;
        } else if (data.length === 0) {
            html += '<p class="alert alert-info">Нет данных</p>';
        } else {
            html += '<div style="overflow-x: auto;"><table class="table"><thead><tr>';
            html += '<th>Агентство</th><th>Email</th><th>Телефон</th><th>Всего договоров</th><th>Активных</th><th>Сумма</th>';
            html += '</tr></thead><tbody>';
            
            let totalContracts = 0;
            let totalActive = 0;
            let totalAmount = 0;
            
            data.forEach(item => {
                totalContracts += parseInt(item.total_contracts) || 0;
                totalActive += parseInt(item.active_contracts) || 0;
                totalAmount += parseFloat(item.total_amount) || 0;
                
                html += `<tr>
                    <td><strong>${escapeHtml(item.name)}</strong></td>
                    <td>${escapeHtml(item.email) || '-'}</td>
                    <td>${escapeHtml(item.phone) || '-'}</td>
                    <td>${item.total_contracts || 0}</td>
                    <td>${item.active_contracts || 0}</td>
                    <td>${formatMoney(item.total_amount)}</td>
                </tr>`;
            });
            
            html += `<tr class="total-row">
                <td colspan="3"><strong>ИТОГО:</strong></td>
                <td><strong>${totalContracts}</strong></td>
                <td><strong>${totalActive}</strong></td>
                <td><strong>${formatMoney(totalAmount)}</strong></td>
            </tr>`;
            
            html += '</tbody></table></div>';
        }
        
        document.getElementById('agencyReport').innerHTML = html;
        
    } catch (error) {
        document.getElementById('agencyReport').innerHTML = '<p class="alert alert-error">Ошибка загрузки отчета</p>';
    }
}

async function loadMonthlyReport() {
    try {
        const response = await fetch('api/reports.php?type=monthly&year=2026');
        const data = await response.json();
        
        window.monthlyData = data;
        
        let html = `
            <div class="report-header">
                <h3>Статистика по месяцам 2026 года</h3>
                <p>Количество и сумма договоров</p>
            </div>
        `;
        
        if (data.error) {
            html += `<p class="alert alert-error">${data.error}</p>`;
        } else if (data.length === 0) {
            html += '<p class="alert alert-info">Нет данных за 2026 год</p>';
        } else {
            const months = [
                'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
            ];
            
            html += '<div style="overflow-x: auto;"><table class="table"><thead><tr>';
            html += '<th>Месяц</th><th>Всего договоров</th><th>Активных</th><th>Сумма</th>';
            html += '</tr></thead><tbody>';
            
            let totalContracts = 0;
            let totalActive = 0;
            let totalAmount = 0;
            
            const monthlyData = Array(12).fill().map((_, i) => ({
                month: i + 1,
                total: 0,
                active: 0,
                amount: 0
            }));
            
            data.forEach(item => {
                monthlyData[item.month - 1] = item;
            });
            
            monthlyData.forEach(item => {
                totalContracts += parseInt(item.total) || 0;
                totalActive += parseInt(item.active) || 0;
                totalAmount += parseFloat(item.amount) || 0;
                
                html += `<tr>
                    <td><strong>${months[item.month - 1]}</strong></td>
                    <td>${item.total || 0}</td>
                    <td>${item.active || 0}</td>
                    <td>${formatMoney(item.amount)}</td>
                </tr>`;
            });
            
            html += `<tr class="total-row">
                <td><strong>ИТОГО:</strong></td>
                <td><strong>${totalContracts}</strong></td>
                <td><strong>${totalActive}</strong></td>
                <td><strong>${formatMoney(totalAmount)}</strong></td>
            </tr>`;
            
            html += '</tbody></table></div>';
        }
        
        document.getElementById('monthlyReport').innerHTML = html;
        
    } catch (error) {
        document.getElementById('monthlyReport').innerHTML = '<p class="alert alert-error">Ошибка загрузки отчета</p>';
    }
}

// Загрузка отчета по всем договорам
async function loadContractsReport() {
    try {
        const response = await fetch('api/reports.php?type=contracts');
        const data = await response.json();
        
        window.contractsData = data;
        
        let html = `
            <div class="report-header">
                <h3>Все договоры</h3>
                <p>По состоянию на ${formatDate(getCurrentDate())}</p>
            </div>
        `;
        
        if (data.error) {
            html += `<p class="alert alert-error">${data.error}</p>`;
        } else if (data.length === 0) {
            html += '<p class="alert alert-info">Нет данных</p>';
        } else {
            html += '<div style="overflow-x: auto;"><table class="table"><thead><tr>';
            html += '<th>Номер</th><th>Агентство</th><th>Дата подписания</th><th>Срок действия</th><th>Статус</th><th>Сумма</th>';
            html += '</tr></thead><tbody>';
            
            let total = 0;
            data.forEach(item => {
                total += parseFloat(item.total_amount) || 0;
                html += `<tr>
                    <td>${escapeHtml(item.number) || 'Б/Н'}</td>
                    <td>${escapeHtml(item.agency_name)}</td>
                    <td>${item.signing_date || '-'}</td>
                    <td>${item.expiration_date || 'Бессрочно'}</td>
                    <td><span class="badge ${getStatusClass(item.status)}">${getStatusText(item.status)}</span></td>
                    <td>${formatMoney(item.total_amount)}</td>
                </tr>`;
            });
            
            html += `<tr class="total-row">
                <td colspan="5"><strong>ИТОГО:</strong></td>
                <td><strong>${formatMoney(total)}</strong></td>
            </tr>`;
            
            html += '</tbody></table></div>';
        }
        
        const container = document.getElementById('contractsReport');
        if (container) {
            container.innerHTML = html;
        }
        
    } catch (error) {
        const container = document.getElementById('contractsReport');
        if (container) {
            container.innerHTML = '<p class="alert alert-error">Ошибка загрузки отчета</p>';
        }
    }
}

function exportToCSV(type) {
    let data = [];
    let filename = '';
    let headers = [];
    
    switch(type) {
        case 'expiring':
            data = window.expiringData || [];
            filename = `expiring_contracts_${getCurrentDate()}`;
            headers = ['Номер договора', 'Агентство', 'Дата истечения', 'Осталось дней', 'Сумма, Br'];
            break;
        case 'agencies':
            data = window.agencyData || [];
            filename = `agencies_stats_${getCurrentDate()}`;
            headers = ['Агентство', 'Email', 'Телефон', 'Всего договоров', 'Активных договоров', 'Общая сумма, Br'];
            break;
        case 'monthly':
            data = window.monthlyData || [];
            filename = `monthly_stats_2026`;
            headers = ['Месяц', 'Всего договоров', 'Активных договоров', 'Сумма, Br'];
            break;
        case 'contracts':
            data = window.contractsData || [];
            filename = `all_contracts_${getCurrentDate()}`;
            headers = ['Номер договора', 'Агентство', 'Дата подписания', 'Срок действия', 'Статус', 'Сумма, Br'];
            break;
    }
    
    if (!data || data.length === 0) {
        showError('Нет данных для экспорта');
        return;
    }
    
    let csvRows = [];
    
    csvRows.push(`"Отчет сгенерирован: ${new Date().toLocaleString('ru-RU')}"`);
    csvRows.push('');
    
    csvRows.push(headers.join(';'));
    
    data.forEach(row => {
        const values = headers.map(header => {
            let value = '';
            switch(header) {
                case 'Номер договора':
                    value = row.number || row['Номер договора'] || 'Б/Н';
                    break;
                case 'Агентство':
                    value = row.agency_name || row.name || row['Агентство'] || '-';
                    break;
                case 'Дата истечения':
                    value = row.expiration_date || row['Дата истечения'] || '-';
                    break;
                case 'Осталось дней':
                    value = row.days_left || row['Осталось дней'] || '-';
                    break;
                case 'Сумма, Br':
                case 'Общая сумма, Br':
                    value = formatMoneyForCSV(row.total_amount || row.amount || row['Сумма'] || 0);
                    break;
                case 'Email':
                    value = row.email || '-';
                    break;
                case 'Телефон':
                    value = row.phone || '-';
                    break;
                case 'Всего договоров':
                    value = row.total_contracts || row.total || 0;
                    break;
                case 'Активных договоров':
                    value = row.active_contracts || row.active || 0;
                    break;
                case 'Месяц':
                    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
                    value = months[(row.month || 1) - 1];
                    break;
                case 'Дата подписания':
                    value = row.signing_date || '-';
                    break;
                case 'Срок действия':
                    value = row.expiration_date || 'Бессрочно';
                    break;
                case 'Статус':
                    value = getStatusTextForCSV(row.status);
                    break;
                default:
                    value = row[Object.keys(row)[0]] || '-';
            }
            return `"${String(value).replace(/"/g, '""')}"`;
        });
        csvRows.push(values.join(';'));
    });
    
    csvRows.push('');
    csvRows.push(`"Итого записей: ${data.length}"`);
    
    const csvContent = csvRows.join('\n');
    const blob = new Blob(["\uFEFF" + csvContent], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute("download", `${filename}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    
    showSuccess(`Экспорт завершен: ${filename}.csv`);
}

function exportToPDF(type) {
    let title = '';
    let data = [];
    let headers = [];
    
    switch(type) {
        case 'expiring':
            title = 'Истекающие договоры';
            data = window.expiringData || [];
            headers = ['Номер договора', 'Агентство', 'Дата истечения', 'Осталось дней', 'Сумма, Br'];
            break;
        case 'agencies':
            title = 'Статистика по агентствам';
            data = window.agencyData || [];
            headers = ['Агентство', 'Email', 'Телефон', 'Всего договоров', 'Активных', 'Сумма, Br'];
            break;
        case 'monthly':
            title = 'Статистика по месяцам 2026';
            data = window.monthlyData || [];
            headers = ['Месяц', 'Всего договоров', 'Активных', 'Сумма, Br'];
            break;
        case 'contracts':
            title = 'Все договоры';
            data = window.contractsData || [];
            headers = ['Номер', 'Агентство', 'Дата подписания', 'Срок действия', 'Статус', 'Сумма, Br'];
            break;
    }
    
    if (!data || data.length === 0) {
        showError('Нет данных для экспорта');
        return;
    }
    
    let html = `
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>${title}</title>
        <style>
            body {
                font-family: 'Segoe UI', Arial, sans-serif;
                margin: 2cm;
                font-size: 12px;
            }
            h1 {
                color: #2e7d32;
                text-align: center;
                margin-bottom: 10px;
            }
            .report-date {
                text-align: center;
                color: #666;
                margin-bottom: 30px;
                font-size: 11px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th {
                background: #2e7d32;
                color: white;
                padding: 10px;
                text-align: left;
                font-weight: bold;
                border: 1px solid #1b5e20;
            }
            td {
                padding: 8px;
                border: 1px solid #c8e6c9;
                vertical-align: top;
            }
            tr:nth-child(even) {
                background: #f5f5f5;
            }
            .total-row {
                background: #e8f5e9;
                font-weight: bold;
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #999;
                border-top: 1px solid #ddd;
                padding-top: 15px;
            }
            @media print {
                body {
                    margin: 1cm;
                }
                button {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <button onclick="window.print()" style="position: fixed; top: 10px; right: 10px; padding: 8px 16px; background: #2e7d32; color: white; border: none; border-radius: 5px; cursor: pointer;">🖨️ Печать</button>
        <h1>${title}</h1>
        <div class="report-date">Дата формирования: ${new Date().toLocaleString('ru-RU')}</div>
        
        <table>
            <thead>
                <tr>`;
    
    headers.forEach(h => {
        html += `<th>${h}</th>`;
    });
    
    html += `</tr></thead><tbody>`;
    
    let totals = {};
    data.forEach(row => {
        html += `<tr>`;
        headers.forEach(header => {
            let value = '';
            switch(header) {
                case 'Номер договора':
                    value = row.number || row['Номер договора'] || 'Б/Н';
                    break;
                case 'Агентство':
                    value = row.agency_name || row.name || '-';
                    break;
                case 'Дата истечения':
                    value = row.expiration_date || '-';
                    break;
                case 'Осталось дней':
                    value = row.days_left || '-';
                    break;
                case 'Сумма, Br':
                case 'Общая сумма, Br':
                    value = formatMoneyForPDF(row.total_amount || row.amount || 0);
                    if (header === 'Сумма, Br') {
                        totals.amount = (totals.amount || 0) + (parseFloat(row.total_amount) || 0);
                    }
                    break;
                case 'Email':
                    value = row.email || '-';
                    break;
                case 'Телефон':
                    value = row.phone || '-';
                    break;
                case 'Всего договоров':
                    value = row.total_contracts || row.total || 0;
                    totals.contracts = (totals.contracts || 0) + (parseInt(row.total_contracts) || 0);
                    break;
                case 'Активных':
                case 'Активных договоров':
                    value = row.active_contracts || row.active || 0;
                    totals.active = (totals.active || 0) + (parseInt(row.active_contracts) || 0);
                    break;
                case 'Месяц':
                    const months = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
                    value = months[(row.month || 1) - 1];
                    break;
                case 'Дата подписания':
                    value = row.signing_date || '-';
                    break;
                case 'Срок действия':
                    value = row.expiration_date || 'Бессрочно';
                    break;
                case 'Статус':
                    const statusText = {
                        'active': 'Активен',
                        'draft': 'Черновик',
                        'expired': 'Истек',
                        'terminated': 'Расторгнут'
                    };
                    value = statusText[row.status] || row.status;
                    break;
                default:
                    value = row[Object.keys(row)[0]] || '-';
            }
            html += `<td>${value}</td>`;
        });
        html += `</tr>`;
    });
    
    if (Object.keys(totals).length > 0) {
        html += `<tr class="total-row">`;
        if (totals.amount !== undefined) {
            html += `<td colspan="${headers.length - 1}"><strong>ИТОГО:</strong></td>`;
            html += `<td><strong>${formatMoneyForPDF(totals.amount)}</strong></td>`;
        } else {
            html += `<td colspan="${headers.length}"><strong>Всего записей: ${data.length}</strong></td>`;
        }
        html += `</tr>`;
    }
    
    html += `
            </tbody>
        </table>
        <div class="footer">
            Система управления кадровыми агентствами | HR CRM<br>
            Отчет сформирован автоматически
        </div>
    </body>
    </html>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(html);
    printWindow.document.close();
}

function formatMoneyForCSV(amount) {
    if (amount === null || amount === undefined) return '0,00';
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function formatMoneyForPDF(amount) {
    if (amount === null || amount === undefined) return '0,00 Br';
    return new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount) + ' Br';
}

function getStatusTextForCSV(status) {
    const statuses = {
        'active': 'Активен',
        'draft': 'Черновик',
        'expired': 'Истек',
        'terminated': 'Расторгнут'
    };
    return statuses[status] || status;
}

function exportToExcel(type) {
    let url = `api/export.php?type=${type}&format=csv`;
    window.open(url, '_blank');
}

function exportToCSV(type) {
    window.location.href = `api/export.php?type=${type}&format=csv`;
}

function exportToPDF(type) {
    window.open(`api/export.php?type=${type}&format=pdf`, '_blank');
}

function loadReportData() {
    try {
        if (window.expiringData === undefined) {
            fetch('api/reports.php?type=expiring&days=30').then(r => r.json()).then(data => {
                if (!data.error) window.expiringData = data;
            }).catch(e => console.error(e));
        }
        
        if (window.agencyData === undefined) {
            fetch('api/reports.php?type=by_agency').then(r => r.json()).then(data => {
                if (!data.error) window.agencyData = data;
            }).catch(e => console.error(e));
        }
        
        if (window.monthlyData === undefined) {
            fetch('api/reports.php?type=monthly&year=2026').then(r => r.json()).then(data => {
                if (!data.error) window.monthlyData = data;
            }).catch(e => console.error(e));
        }
        
        if (window.contractsData === undefined) {
            fetch('api/reports.php?type=contracts').then(r => r.json()).then(data => {
                if (!data.error) window.contractsData = data;
            }).catch(e => console.error(e));
        }
        
    } catch (error) {
        console.error('Ошибка загрузки данных для экспорта:', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadExpiringReport();
    loadAgencyReport();
    loadMonthlyReport();
    loadContractsReport();
    loadReportData();
});
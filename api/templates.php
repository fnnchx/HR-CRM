<script src="js/utils.js"></script>
<script src="js/contracts.js"></script>
<script>
    const user = checkAuth();
    
    async function loadTemplatesSelect() {
        try {
            console.log('Загрузка шаблонов...');
            const response = await fetch('api/templates.php');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const templates = await response.json();
            console.log('Получены шаблоны:', templates);
            
            const select = document.getElementById('templateId');
            if (!select) {
                console.error('Элемент templateId не найден');
                return;
            }
            
            if (templates.error) {
                console.error('Ошибка API:', templates.error);
                select.innerHTML = '<option value="">Ошибка загрузки шаблонов</option>';
                return;
            }
            
            if (templates.length === 0) {
                select.innerHTML = '<option value="">Нет доступных шаблонов</option>';
                await createDefaultTemplate();
                loadTemplatesSelect();
                return;
            }
            
            select.innerHTML = '<option value="">Выберите шаблон</option>' +
                templates.map(t => 
                    `<option value="${t.id}">${escapeHtml(t.name)}</option>`
                ).join('');
                
            console.log('Шаблоны загружены успешно');
            
        } catch (error) {
            console.error('Ошибка загрузки шаблонов:', error);
            const select = document.getElementById('templateId');
            if (select) {
                select.innerHTML = '<option value="">Ошибка загрузки шаблонов</option>';
            }
            showError('Не удалось загрузить шаблоны: ' + error.message);
        }
    }
    
    async function createDefaultTemplate() {
        try {
            const defaultTemplate = {
                name: 'Стандартный договор',
                type: 'standard',
                content: `ДОГОВОР № {{number}}
г. Минск {{date}}

Кадровое агентство «{{agency_name}}», именуемое в дальнейшем «Исполнитель», с одной стороны, и Заказчик, с другой стороны, заключили настоящий договор.

1. ПРЕДМЕТ ДОГОВОРА
1.1. Исполнитель обязуется оказать услуги по подбору персонала.

2. СТОИМОСТЬ УСЛУГ
2.1. Стоимость услуг составляет {{total_amount}} Br.

3. СРОК ДЕЙСТВИЯ
3.1. Договор действует до {{expiration_date}}.

Подписи сторон:`,
                is_active: 1
            };
            
            const response = await fetch('api/templates.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(defaultTemplate)
            });
            
            const result = await response.json();
            if (result.success) {
                console.log('Создан шаблон по умолчанию');
            }
        } catch (error) {
            console.error('Ошибка создания шаблона:', error);
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        loadAgenciesSelect();
        loadTemplatesSelect(); 
        loadServices();
        generateContractNumber();
    });
    
    document.getElementById('logoutBtn').addEventListener('click', (e) => {
        e.preventDefault();
        logout();
    });
</script>
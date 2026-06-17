async function loadProfile() {
    const user = checkAuth();
    if (!user) return;
    
    try {
        const response = await fetch(`api/users.php?id=${user.id}`);
        const data = await response.json();
        
        if (data) {
            document.getElementById('login').value = data.login || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('firstName').value = data.first_name || '';
            document.getElementById('lastName').value = data.last_name || '';
            document.getElementById('phone').value = data.phone || '';
            document.getElementById('role').value = data.role || 'user';
            document.getElementById('createdAt').value = formatDate(data.created_at);
        }
    } catch (error) {
        showError('Ошибка загрузки профиля');
    }
}

async function saveProfile() {
    const user = checkAuth();
    if (!user) return;
    
    const data = {
        id: user.id,
        login: document.getElementById('login').value,
        email: document.getElementById('email').value,
        first_name: document.getElementById('firstName').value,
        last_name: document.getElementById('lastName').value,
        phone: document.getElementById('phone').value,
        role: document.getElementById('role').value
    };
    
    try {
        const response = await fetch('api/users.php', {
            method: 'PUT',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Профиль обновлен');
            
            user.email = data.email;
            localStorage.setItem('user', JSON.stringify(user));
        } else {
            showError(result.error || 'Ошибка обновления');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}

async function changePassword() {
    const user = checkAuth();
    if (!user) return;
    
    const current = document.getElementById('currentPassword').value;
    const newPass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    
    if (!current || !newPass || !confirm) {
        showError('Заполните все поля');
        return;
    }
    
    if (newPass.length < 6) {
        showError('Новый пароль должен быть не менее 6 символов');
        return;
    }
    
    if (newPass !== confirm) {
        showError('Пароли не совпадают');
        return;
    }
    
    try {
        const response = await fetch('api/change-password.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                user_id: user.id,
                current_password: current,
                new_password: newPass
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showSuccess('Пароль изменен');
            
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        } else {
            showError(result.error || 'Ошибка смены пароля');
        }
    } catch (error) {
        showError('Ошибка подключения к серверу');
    }
}
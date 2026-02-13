/**
 * CRM Stages — Frontend Logic
 *
 * Единая точка отправки запросов к серверу.
 * DRY: общая функция sendRequest для всех действий.
 */

'use strict';

/**
 * Показать уведомление (success / error).
 */
function showNotification(message, type) {
    var el = document.getElementById('notification');
    if (!el) return;

    el.textContent = message;
    el.className = 'notification ' + type;

    clearTimeout(el._timer);
    el._timer = setTimeout(function () {
        el.className = 'notification hidden';
    }, 4000);
}

/**
 * Общая функция отправки запроса на сервер.
 *
 * @param {string}   url      URL эндпоинта
 * @param {FormData} formData Данные запроса
 */
function sendRequest(url, formData) {
    fetch(url, { method: 'POST', body: formData })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (data) {
            showNotification(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                setTimeout(function () { location.reload(); }, 800);
            }
        })
        .catch(function () {
            showNotification('Ошибка сети. Попробуйте ещё раз.', 'error');
        });
}

/**
 * Выполнить действие (кнопка без формы).
 */
function performAction(companyId, action, extraData) {
    var formData = new FormData();
    formData.append('company_id', companyId);
    formData.append('action', action);

    if (extraData) {
        Object.keys(extraData).forEach(function (key) {
            formData.append(key, extraData[key]);
        });
    }

    sendRequest('/action', formData);
}

/**
 * Отправка формы действия.
 */
function submitForm(form, companyId, action) {
    var formData = new FormData(form);
    formData.append('company_id', companyId);
    formData.append('action', action);

    sendRequest('/action', formData);
    return false; // предотвратить стандартную отправку формы
}

/**
 * Ручной переход на следующую стадию.
 */
function tryAdvance(companyId) {
    var formData = new FormData();
    formData.append('company_id', companyId);

    sendRequest('/advance', formData);
}

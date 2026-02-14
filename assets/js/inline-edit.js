/**
 * Shared Inline Editing Functions for 鋒兄 System
 * Provides consistent inline editing functionality across all pages
 */

// Global configuration
const INLINE_EDIT_CONFIG = {
    TABLE_NAME: typeof TABLE !== 'undefined' ? TABLE : 'article',
    API_ENDPOINT: 'api.php',
    MOBILE_BREAKPOINT: 768
};

/**
 * Initialize inline editing for a page
 */
function initInlineEditing() {
    // Add keyboard event listeners
    document.addEventListener('keydown', handleInlineKeyboardEvents);
    
    // Add click outside to close inline add
    document.addEventListener('click', handleInlineClickOutside);
    
    // Add input validation listeners
    addInlineValidationListeners();
}

/**
 * Handle keyboard events for inline editing
 */
function handleInlineKeyboardEvents(e) {
    // Escape key to cancel inline editing
    if (e.key === 'Escape') {
        const activeCard = document.querySelector('.inline-edit:not(.hidden)');
        if (activeCard) {
            if (activeCard.closest('#inlineAddCard')) {
                cancelInlineAdd();
            } else {
                const card = activeCard.closest('.card[data-id]');
                if (card) {
                    cancelInlineEdit(card.dataset.id);
                }
            }
        }
    }
    
    // Enter key to save (with Ctrl/Cmd)
    if ((e.key === 'Enter' && (e.ctrlKey || e.metaKey))) {
        const activeInput = document.activeElement;
        if (activeInput && activeInput.classList.contains('inline-input')) {
            const card = activeInput.closest('.card');
            if (card) {
                if (card.id === 'inlineAddCard') {
                    saveInlineAdd();
                } else if (card.dataset.id) {
                    saveInlineEdit(card.dataset.id);
                }
            }
        }
    }
    
    // Tab key to navigate between fields
    if (e.key === 'Tab') {
        handleInlineTabNavigation(e);
    }
}

/**
 * Handle tab navigation in inline editing
 */
function handleInlineTabNavigation(e) {
    const activeInput = document.activeElement;
    if (!activeInput || !activeInput.classList.contains('inline-input')) return;
    
    const inlineInputs = Array.from(activeInput.closest('.inline-edit, .inline-edit-always').querySelectorAll('.inline-input'));
    const currentIndex = inlineInputs.indexOf(activeInput);
    
    let nextIndex;
    if (e.shiftKey) {
        // Shift+Tab: go to previous field
        nextIndex = currentIndex > 0 ? currentIndex - 1 : inlineInputs.length - 1;
    } else {
        // Tab: go to next field
        nextIndex = currentIndex < inlineInputs.length - 1 ? currentIndex + 1 : 0;
    }
    
    if (inlineInputs[nextIndex]) {
        e.preventDefault();
        inlineInputs[nextIndex].focus();
        inlineInputs[nextIndex].select();
    }
}

/**
 * Handle click outside to close inline add
 */
function handleInlineClickOutside(e) {
    const addCard = document.getElementById('inlineAddCard');
    if (addCard && addCard.style.display !== 'none' && !addCard.contains(e.target)) {
        const addButton = e.target.closest('[onclick*="handleAdd"]');
        if (!addButton) {
            cancelInlineAdd();
        }
    }
}

/**
 * Add validation listeners to inline inputs
 */
function addInlineValidationListeners() {
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('inline-input')) {
            validateInlineInput(e.target);
        }
    });
    
    document.addEventListener('blur', function(e) {
        if (e.target.classList.contains('inline-input')) {
            validateInlineInput(e.target);
        }
    }, true);
}

/**
 * Validate inline input field
 */
function validateInlineInput(input) {
    // Remove previous validation classes
    input.classList.remove('error', 'success');
    
    // Check if field is required and empty
    if (input.hasAttribute('required') && !input.value.trim()) {
        input.classList.add('error');
        showInlineValidationMessage(input, '此欄位為必填');
        return false;
    }
    
    // Email validation
    if (input.type === 'email' && input.value.trim()) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(input.value.trim())) {
            input.classList.add('error');
            showInlineValidationMessage(input, '請輸入有效的電子郵件地址');
            return false;
        }
    }
    
    // URL validation
    if (input.type === 'url' && input.value.trim()) {
        try {
            new URL(input.value.trim());
        } catch (e) {
            input.classList.add('error');
            showInlineValidationMessage(input, '請輸入有效的網址');
            return false;
        }
    }
    
    // Number validation
    if (input.type === 'number' && input.value.trim()) {
        const num = parseFloat(input.value.trim());
        if (isNaN(num)) {
            input.classList.add('error');
            showInlineValidationMessage(input, '請輸入有效的數字');
            return false;
        }
        
        // Check min/max attributes
        const min = parseFloat(input.getAttribute('min'));
        const max = parseFloat(input.getAttribute('max'));
        if (!isNaN(min) && num < min) {
            input.classList.add('error');
            showInlineValidationMessage(input, `數字不能小於 ${min}`);
            return false;
        }
        if (!isNaN(max) && num > max) {
            input.classList.add('error');
            showInlineValidationMessage(input, `數字不能大於 ${max}`);
            return false;
        }
    }
    
    // Length validation
    const maxLength = input.getAttribute('maxlength');
    if (maxLength && input.value.length > parseInt(maxLength)) {
        input.classList.add('error');
        showInlineValidationMessage(input, `長度不能超過 ${maxLength} 個字元`);
        return false;
    }
    
    // If we get here, validation passed
    input.classList.add('success');
    hideInlineValidationMessage(input);
    return true;
}

/**
 * Show inline validation message
 */
function showInlineValidationMessage(input, message) {
    hideInlineValidationMessage(input); // Hide any existing message
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'inline-validation-message show';
    messageDiv.textContent = message;
    
    // Position the message below the input
    input.parentNode.style.position = 'relative';
    messageDiv.style.position = 'absolute';
    messageDiv.style.top = (input.offsetHeight + 5) + 'px';
    messageDiv.style.left = '0';
    messageDiv.style.zIndex = '1000';
    
    input.parentNode.appendChild(messageDiv);
}

/**
 * Hide inline validation message
 */
function hideInlineValidationMessage(input) {
    if (input && input.parentNode) {
        const existingMessage = input.parentNode.querySelector('.inline-validation-message');
        if (existingMessage) {
            existingMessage.remove();
        }
    }
}

/**
 * Show inline editing for new item (desktop) or modal (mobile)
 * Only define if not already defined by the page
 */
if (typeof handleAdd === 'undefined') {
    function handleAdd() {
        // Use inline editing for all screen sizes
        startInlineAdd();
    }
}

/**
 * Start inline editing for new item
 * Only define if not already defined by the page
 */
if (typeof startInlineAdd === 'undefined') {
    function startInlineAdd() {
        // Try to find card first, then row
        const card = document.getElementById('inlineAddCard');
        const row = document.getElementById('inlineAddRow');
        const element = card || row;
        
        if (!element) return;
        
        element.style.display = card ? 'block' : 'table-row';
        element.querySelectorAll('[data-field]').forEach(input => {
            input.value = '';
            input.classList.remove('error', 'success');
        });
        
        // Focus on first required field or title field
        const titleInput = element.querySelector('[data-field="title"], [data-field="name"], [required]');
        if (titleInput) {
            titleInput.focus();
            titleInput.select();
        }
    }
}

/**
 * Cancel inline add
 * Only define if not already defined by the page
 */
if (typeof cancelInlineAdd === 'undefined') {
    function cancelInlineAdd() {
        // Try to find card first, then row
        const card = document.getElementById('inlineAddCard');
        const row = document.getElementById('inlineAddRow');
        const element = card || row;
        
        if (!element) return;
        
        // Clear validation messages
        element.querySelectorAll('.inline-validation-message').forEach(msg => msg.remove());
        element.querySelectorAll('.inline-input').forEach(input => {
            input.classList.remove('error', 'success');
        });
        
        element.style.display = 'none';
    }
}

/**
 * Save inline add
 * Only define if not already defined by the page
 */
if (typeof saveInlineAdd === 'undefined') {
    function saveInlineAdd() {
        // Try to find card first, then row
        const card = document.getElementById('inlineAddCard');
        const row = document.getElementById('inlineAddRow');
        const element = card || row;
        
        if (!element) return;
        
        // Validate all fields before saving
        const inputs = element.querySelectorAll('[data-field]');
        let isValid = true;
        let firstInvalidInput = null;
        
        inputs.forEach(input => {
            if (!validateInlineInput(input)) {
                isValid = false;
                if (!firstInvalidInput) {
                    firstInvalidInput = input;
                }
            }
        });
        
        if (!isValid) {
            if (firstInvalidInput) {
                firstInvalidInput.focus();
                firstInvalidInput.select();
            }
            alert('請修正所有錯誤後再儲存');
            return;
        }
        
        // Collect data from all inline inputs
        const data = {};
        let hasError = false;
        
        element.querySelectorAll('[data-field]').forEach(input => {
            const field = input.dataset.field;
            const value = input.type === 'checkbox' ? input.checked : input.value.trim();
            data[field] = value;
            
            // Basic validation for required fields
            if (input.hasAttribute('required') && !value) {
                hasError = true;
                input.classList.add('error');
            } else {
                input.classList.remove('error');
            }
        });
        
        if (hasError) {
            alert('請填寫所有必填欄位');
            return;
        }
        
        // Show loading state
        showInlineLoading(element);
        
        // Send to API
        fetch(`${INLINE_EDIT_CONFIG.API_ENDPOINT}?action=create&table=${INLINE_EDIT_CONFIG.TABLE_NAME}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(res => {
            hideInlineLoading(element);
            if (res.success) {
                location.reload();
            } else {
                alert('儲存失敗: ' + (res.error || '未知錯誤'));
            }
        })
        .catch(error => {
            hideInlineLoading(element);
            console.error('Save error:', error);
            alert('儲存失敗，請稍後再試');
        });
    }
}

/**
 * Get card element by ID
 */
function getCardById(id) {
    return document.querySelector(`.card[data-id="${id}"], tr[data-id="${id}"]`);
}

/**
 * Start inline editing for existing item
 * Only define if not already defined by the page
 */
if (typeof startInlineEdit === 'undefined') {
    function startInlineEdit(id) {
        // Use modal on mobile if available
        if (window.matchMedia(`(max-width: ${INLINE_EDIT_CONFIG.MOBILE_BREAKPOINT}px)`).matches) {
            if (typeof editItem === 'function') {
                editItem(id);
                return;
            }
        }
        
        const card = getCardById(id);
        if (!card) return;
        
        // Hide view, show edit
        card.querySelectorAll('.inline-view').forEach(el => el.style.display = 'none');
        card.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'block');
        
        // Fill inputs with current data
        fillInlineInputs(card);
        
        // Focus on first input
        const firstInput = card.querySelector('.inline-input');
        if (firstInput) {
            firstInput.focus();
            firstInput.select();
        }
    }
}

/**
 * Cancel inline editing
 * Only define if not already defined by the page
 */
if (typeof cancelInlineEdit === 'undefined') {
    function cancelInlineEdit(id) {
        const card = getCardById(id);
        if (!card) return;
        
        // Clear validation messages
        card.querySelectorAll('.inline-validation-message').forEach(msg => msg.remove());
        card.querySelectorAll('.inline-input').forEach(input => {
            input.classList.remove('error', 'success');
        });
        
        // Hide edit, show view
        card.querySelectorAll('.inline-view').forEach(el => el.style.display = '');
        card.querySelectorAll('.inline-edit').forEach(el => el.style.display = 'none');
    }
}

/**
 * Fill inline inputs with card data
 * Only define if not already defined by the page
 */
if (typeof fillInlineInputs === 'undefined') {
    function fillInlineInputs(card) {
        const data = card.dataset;
        
        card.querySelectorAll('[data-field]').forEach(input => {
            const field = input.dataset.field;
            const value = data[field] || data[field + 'Value'] || '';
            
            if (input.type === 'checkbox') {
                input.checked = value === 'true' || value === '1';
            } else {
                input.value = value;
            }
            
            // Clear validation classes
            input.classList.remove('error', 'success');
        });
    }
}

/**
 * Save inline editing
 * Only define if not already defined by the page
 */
if (typeof saveInlineEdit === 'undefined') {
    function saveInlineEdit(id) {
        const card = getCardById(id);
        if (!card) return;
        
        // Validate all fields before saving
        const inputs = card.querySelectorAll('[data-field]');
        let isValid = true;
        let firstInvalidInput = null;
        
        inputs.forEach(input => {
            if (!validateInlineInput(input)) {
                isValid = false;
                if (!firstInvalidInput) {
                    firstInvalidInput = input;
                }
            }
        });
        
        if (!isValid) {
            if (firstInvalidInput) {
                firstInvalidInput.focus();
                firstInvalidInput.select();
            }
            alert('請修正所有錯誤後再儲存');
            return;
        }
        
        // Collect data from all inline inputs
        const data = {};
        let hasError = false;
        
        card.querySelectorAll('[data-field]').forEach(input => {
            const field = input.dataset.field;
            const value = input.type === 'checkbox' ? input.checked : input.value.trim();
            data[field] = value;
            
            // Basic validation for required fields
            if (input.hasAttribute('required') && !value) {
                hasError = true;
                input.classList.add('error');
            } else {
                input.classList.remove('error');
            }
        });
        
        if (hasError) {
            alert('請填寫所有必填欄位');
            return;
        }
        
        // Show loading state
        showInlineLoading(card);
        
        // Send to API
        fetch(`${INLINE_EDIT_CONFIG.API_ENDPOINT}?action=update&table=${INLINE_EDIT_CONFIG.TABLE_NAME}&id=${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(res => {
            hideInlineLoading(card);
            if (res.success) {
                location.reload();
            } else {
                alert('儲存失敗: ' + (res.error || '未知錯誤'));
            }
        })
        .catch(error => {
            hideInlineLoading(card);
            console.error('Save error:', error);
            alert('儲存失敗，請稍後再試');
        });
    }
}

/**
 * Validate inline form
 */
function validateInlineForm(card) {
    let isValid = true;
    let firstInvalidInput = null;
    
    card.querySelectorAll('[data-field][required]').forEach(input => {
        if (!validateInlineInput(input)) {
            isValid = false;
            if (!firstInvalidInput) {
                firstInvalidInput = input;
            }
        }
    });
    
    if (!isValid && firstInvalidInput) {
        firstInvalidInput.focus();
        firstInvalidInput.select();
    }
    
    return isValid;
}

/**
 * Show loading state
 */
function showInlineLoading(card) {
    const saveButton = card.querySelector('.btn-primary');
    if (saveButton) {
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 儲存中...';
    }
}

/**
 * Hide loading state
 */
function hideInlineLoading(card) {
    const saveButton = card.querySelector('.btn-primary');
    if (saveButton) {
        saveButton.disabled = false;
        saveButton.innerHTML = '儲存';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initInlineEditing === 'function') {
        initInlineEditing();
    }
});

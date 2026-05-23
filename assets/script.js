document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('.confirm-modal');
    const modalText = document.querySelector('.confirm-modal-message');
    let currentForm = null;

    document.querySelectorAll('.confirm-action').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            currentForm = button.closest('form');
            const message = button.datasetConfirm || 'Tem certeza que deseja continuar?';
            if (modal && modalText) {
                modalText.textContent = message;
                modal.classList.add('active');
            } else if (currentForm) {
                currentForm.submit();
            }
        });
    });

    document.querySelectorAll('.confirm-modal-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            if (modal) {
                modal.classList.remove('active');
            }
            currentForm = null;
        });
    });

    document.querySelectorAll('.confirm-modal-submit').forEach(function (button) {
        button.addEventListener('click', function () {
            if (currentForm) {
                currentForm.submit();
            }
        });
    });

    // Transaction Modal Logic
    initializeTransactionModal();
    updateMobileNav();
});

function initializeTransactionModal() {
    const transactionModal = document.getElementById('transactionModal');
    
    // Close modal on backdrop click
    if (transactionModal) {
        transactionModal.addEventListener('click', function (e) {
            if (e.target === this) {
                closeTransactionModal();
            }
        });
    }
    
    // Handle form submission
    const transactionForm = document.getElementById('transactionForm');
    if (transactionForm) {
        transactionForm.addEventListener('submit', function (e) {
            e.preventDefault();
            this.submit();
        });
    }
}

function openTransactionModal(transaction = null) {
    const modal = document.getElementById('transactionModal');
    let houseId = document.getElementById('formHouseId').value;
    
    // If houseId is not set in the form, try to get it from URL
    if (!houseId) {
        houseId = getHouseIdFromURL();
    }
    
    if (!houseId) {
        return;
    }
    
    // Reset form
    document.getElementById('formAction').value = 'create_transaction';
    document.getElementById('formTransactionId').value = '';
    document.getElementById('formCategory').value = '';
    document.getElementById('formDescription').value = '';
    document.getElementById('formAmount').value = '';
    document.getElementById('formDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('formDueDate').value = '';
    document.getElementById('formType').value = 'expense';
    document.getElementById('formRecurrenceInterval').value = 'none';
    document.getElementById('formRecurrenceCount').value = '1';
    document.getElementById('formStatus').value = 'pending';
    document.getElementById('formHouseId').value = houseId;
    document.getElementById('transactionModalTitle').textContent = 'Nova transação';
    document.getElementById('submitBtn').textContent = 'Salvar transação';
    
    // If editing a transaction
    if (transaction) {
        document.getElementById('formAction').value = 'update_transaction';
        document.getElementById('formTransactionId').value = transaction.id;
        document.getElementById('formCategory').value = transaction.category;
        document.getElementById('formDescription').value = transaction.description;
        document.getElementById('formAmount').value = transaction.amount;
        document.getElementById('formDate').value = transaction.date;
        document.getElementById('formDueDate').value = transaction.due_date || '';
        document.getElementById('formType').value = transaction.type;
        document.getElementById('formRecurrenceInterval').value = transaction.recurrence_interval;
        document.getElementById('formRecurrenceCount').value = transaction.recurrence_count;
        document.getElementById('formStatus').value = transaction.status;
        document.getElementById('transactionModalTitle').textContent = 'Editar transação';
        document.getElementById('submitBtn').textContent = 'Atualizar transação';
    }
    
    if (modal) {
        modal.classList.add('active');
    }
}

function closeTransactionModal() {
    const modal = document.getElementById('transactionModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function getHouseIdFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('house_id') || '';
}

function editTransaction(transaction) {
    // Get the current window width to determine if mobile
    const isMobile = window.innerWidth <= 640;
    
    if (isMobile) {
        // Open modal on mobile
        openTransactionModal(transaction);
    } else {
        // Navigate to edit page on desktop (will still show the form on the page)
        window.location.href = '?page=house&house_id=' + transaction.house_id + '&edit_transaction=' + transaction.id;
    }
}

function updateMobileNav() {
    const navAdd = document.getElementById('navAdd');
    const page = new URLSearchParams(window.location.search).get('page');
    
    if (navAdd) {
        // Only show add button on house page
        if (page === 'house') {
            navAdd.style.display = 'flex';
        } else {
            navAdd.style.display = 'none';
        }
    }
    
    // Update active nav items
    const navDashboard = document.getElementById('navDashboard');
    const navProfile = document.getElementById('navProfile');
    
    if (navDashboard && navProfile) {
        if (page === 'dashboard') {
            navDashboard.classList.add('active');
            navProfile.classList.remove('active');
        } else if (page === 'profile') {
            navProfile.classList.add('active');
            navDashboard.classList.remove('active');
        } else {
            navDashboard.classList.remove('active');
            navProfile.classList.remove('active');
        }
    }
}

function toggleDropdown(event) {
    event.preventDefault();
    const content = event.target.closest('.dropdown-toggle').nextElementSibling;
    if (content && content.classList.contains('dropdown-content')) {
        const isVisible = content.style.display !== 'none';
        content.style.display = isVisible ? 'none' : 'block';
    }
}
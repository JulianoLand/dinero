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
});

function toggleDropdown(event) {
    event.preventDefault();
    const content = event.target.closest('.dropdown-toggle').nextElementSibling;
    if (content && content.classList.contains('dropdown-content')) {
        const isVisible = content.style.display !== 'none';
        content.style.display = isVisible ? 'none' : 'block';
    }
}
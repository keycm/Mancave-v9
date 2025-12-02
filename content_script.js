document.addEventListener('DOMContentLoaded', () => {

    // === MODAL & CONFIRMATION ===
    const confirmModal = document.getElementById('confirmModal');
    const confirmModalTitle = document.getElementById('confirmModalTitle');
    const confirmModalMessage = document.getElementById('confirmModalMessage');
    const confirmModalButton = document.getElementById('confirmModalButton');
    const cancelModalButton = confirmModal.querySelector('.btn-cancel');
    const closeModalButtons = document.querySelectorAll('.modal-close-btn');

    let onConfirmCallback = null; // Store the action to run on confirm

    // Function to open the confirmation modal
    window.showConfirm = (title, message, confirmText, btnClass, callback) => {
        confirmModalTitle.textContent = title;
        confirmModalMessage.textContent = message;
        confirmModalButton.textContent = confirmText;
        
        confirmModalButton.className = 'btn-confirm';
        if (btnClass) {
            confirmModalButton.classList.add(btnClass);
        }
        
        onConfirmCallback = callback; 
        confirmModal.classList.add('show');
    };

    // Function to close all modals
    const closeAllModals = () => {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.classList.remove('show');
        });
        onConfirmCallback = null;
    };
    
    closeModalButtons.forEach(btn => btn.addEventListener('click', closeAllModals));
    if (cancelModalButton) {
        cancelModalButton.addEventListener('click', closeAllModals);
    }
    
    if (confirmModalButton) {
        confirmModalButton.addEventListener('click', () => {
            if (typeof onConfirmCallback === 'function') {
                onConfirmCallback(); 
            }
            closeAllModals(); 
        });
    }

    // === CONTENT (SERVICES) ===
    const servicesTableBody = document.getElementById('servicesTableBody');
    const serviceForm = document.getElementById('serviceForm');
    const serviceFormBtnText = document.getElementById('service-form-btn-text');
    const serviceIdField = document.getElementById('service-id');

    // Service form submit
    serviceForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(serviceForm);
        const id = serviceIdField.value;
        const action = id ? 'update' : 'add';
        
        try {
            const response = await fetch(`services.php?action=${action}`, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert(`Service ${action === 'add' ? 'added' : 'updated'} successfully!`);
                // Reload the page to show the new data
                window.location.reload();
            } else {
                alert(`Error: ${result.message || 'Unknown error'}`);
            }
        } catch (error) {
            console.error('Error saving service:', error);
            alert('An error occurred.');
        }
    });

    // Event delegation for services table
    servicesTableBody.addEventListener('click', (e) => {
        const button = e.target.closest('button');
        if (!button) return;

        const action = button.dataset.action;
        const tr = button.closest('tr');
        const id = tr.dataset.id;
        
        if (action === 'edit') {
            // Populate the form for editing
            serviceIdField.value = id;
            serviceForm.querySelector('#service-type').value = tr.dataset.name;
            serviceForm.querySelector('#service-desc').value = tr.dataset.description;
            serviceForm.querySelector('#service-duration').value = tr.dataset.duration;
            serviceForm.querySelector('#service-price').value = tr.dataset.price;
            serviceFormBtnText.textContent = 'Update Service';
            serviceForm.querySelector('#service-type').focus();
        } else if (action === 'delete') {
            showConfirm('Delete Service', 'Are you sure you want to delete this service?', 'Delete', 'delete', () => {
                deleteService(id);
            });
        }
    });

    async function deleteService(id) {
        const formData = new FormData();
        formData.append('id', id);

        try {
            const response = await fetch('services.php?action=delete', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                alert('Service moved to trash.');
                // Reload the page to show changes
                window.location.reload();
            } else {
                alert(`Error: ${result.message || 'Unknown error'}`);
            }
        } catch (error) {
            console.error('Error deleting service:', error);
            alert('An error occurred.');
        }
    }

});
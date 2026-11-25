export function confirmAction({trigger, title, message, label, danger = false, phrase = ''}) {
    const dialog = document.querySelector('#confirmationDialog');
    if (!dialog || dialog.open) return Promise.resolve(false);

    const originalParent = dialog.parentElement;
    const enclosingModal = trigger.closest('.modal.show');
    if (enclosingModal) enclosingModal.append(dialog);

    dialog.querySelector('#confirmation-title').textContent = title;
    dialog.querySelector('#confirmation-message').textContent = message;
    const button = dialog.querySelector('[data-confirm-action]');
    const typed = dialog.querySelector('[data-confirm-typed]');
    const input = dialog.querySelector('#confirmation-input');
    typed.hidden = typed.disabled = !phrase;
    input.value = '';
    dialog.querySelector('#confirmation-phrase').textContent = phrase;
    const updateButton = () => { button.disabled = Boolean(phrase && input.value !== phrase); };
    updateButton();
    input.addEventListener('input', updateButton);
    button.className = danger ? 'btn btn-danger' : 'btn btn-primary';
    button.textContent = label;
    dialog.returnValue = '';
    return new Promise(resolve => {
        dialog.addEventListener('close', () => {
            const confirmed = dialog.returnValue === 'confirm';
            input.removeEventListener('input', updateButton);
            if (enclosingModal) originalParent.append(dialog);
            trigger.focus();
            resolve(confirmed);
        }, {once: true});
        dialog.showModal();
    });
}

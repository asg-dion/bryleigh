document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-quantity-change]');
    if (!button) return;

    const input = button.parentElement.querySelector('input[type="number"]');
    if (!input) return;

    const min = input.min === '' ? 1 : Number(input.min);
    const max = input.max === '' ? Number.POSITIVE_INFINITY : Number(input.max);
    const current = input.value === '' ? min : Number(input.value);
    const change = Number(button.dataset.quantityChange);

    input.value = String(Math.min(max, Math.max(min, current + change)));
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
});

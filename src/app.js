(() => {
    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR');

    const resourceSearch = document.querySelector('[data-resource-search]');
    const resourceType = document.querySelector('[data-resource-type]');
    const resourceAvailability = document.querySelector('[data-resource-availability]');
    const resourceCards = [...document.querySelectorAll('[data-resource-card]')];
    const resourceEmpty = document.querySelector('[data-resource-empty]');
    const filterResources = () => {
        const query = normalize(resourceSearch?.value);
        const type = normalize(resourceType?.value);
        const availability = resourceAvailability?.value || '';
        let visible = 0;
        resourceCards.forEach((card) => {
            const matches = normalize(card.dataset.name).includes(query)
                && (!type || normalize(card.dataset.type) === type)
                && (!availability || card.dataset.busy === availability);
            card.hidden = !matches;
            if (matches) visible += 1;
        });
        if (resourceEmpty) resourceEmpty.hidden = visible > 0;
    };
    resourceSearch?.addEventListener('input', filterResources);
    resourceType?.addEventListener('change', filterResources);
    resourceAvailability?.addEventListener('change', filterResources);

    const bookingSearch = document.querySelector('[data-booking-search]');
    const bookingStatus = document.querySelector('[data-booking-status]');
    const bookingPeriod = document.querySelector('[data-booking-period]');
    const bookingRows = [...document.querySelectorAll('[data-booking-row]')];
    const bookingEmpty = document.querySelector('[data-booking-empty]');
    const filterBookings = () => {
        const query = normalize(bookingSearch?.value);
        const status = bookingStatus?.value || '';
        const period = bookingPeriod?.value || '';
        let visible = 0;
        bookingRows.forEach((row) => {
            const matches = normalize(row.dataset.search).includes(query)
                && (!status || row.dataset.status === status)
                && (!period || row.dataset.period === period);
            row.hidden = !matches;
            if (matches) visible += 1;
        });
        if (bookingEmpty) bookingEmpty.hidden = visible > 0;
    };
    bookingSearch?.addEventListener('input', filterBookings);
    bookingStatus?.addEventListener('change', filterBookings);
    bookingPeriod?.addEventListener('change', filterBookings);

    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'Confirmar esta operação?')) event.preventDefault();
        });
    });

    const reserveForm = document.querySelector('[data-reservation-form]');
    const resourceSelect = reserveForm?.querySelector('[data-summary-resource]');
    const dateInput = reserveForm?.querySelector('[data-date]');
    const startInput = reserveForm?.querySelector('[data-start]');
    const endInput = reserveForm?.querySelector('[data-end]');
    const resourceSummary = document.querySelector('[data-summary-resource-name]');
    const dateSummary = document.querySelector('[data-summary-date]');
    const timeSummary = document.querySelector('[data-summary-time]');
    const updateSummary = () => {
        if (resourceSummary && resourceSelect) {
            resourceSummary.textContent = resourceSelect.selectedOptions[0]?.textContent || 'Nenhum selecionado';
        }
        if (dateSummary && dateInput) {
            dateSummary.textContent = dateInput.value
                ? new Intl.DateTimeFormat('pt-BR').format(new Date(`${dateInput.value}T12:00:00`))
                : 'Aguardando seleção';
        }
        if (timeSummary) timeSummary.textContent = `${startInput?.value || '--:--'} até ${endInput?.value || '--:--'}`;
    };
    [resourceSelect, dateInput, startInput, endInput].forEach((input) => input?.addEventListener('input', updateSummary));
    updateSummary();

    reserveForm?.addEventListener('submit', (event) => {
        if (startInput?.value && endInput?.value && endInput.value <= startInput.value) {
            event.preventDefault();
            window.alert('O horário de término precisa ser depois do início.');
        }
    });

    document.querySelector('[data-help]')?.addEventListener('click', () => {
        window.alert('Para obter ajuda, entre em contato com o suporte da instituição.');
    });
})();

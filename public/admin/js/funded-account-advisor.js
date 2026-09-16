(() => {
    'use strict';

    const form = document.getElementById('fundedAdvisorForm');
    const drawdownType = document.getElementById('fundedDrawdownType');
    const trailingField = document.getElementById('fundedTrailingReferenceField');

    if (!form || !drawdownType || !trailingField) {
        return;
    }

    const trailingInput = trailingField.querySelector('input[name="trailing_reference_amount"]');
    const plannedEntries = form.querySelector('input[name="planned_max_entries"]');

    if (plannedEntries) {
        plannedEntries.max = '5';
        if (Number(plannedEntries.value) > 5) {
            plannedEntries.value = '5';
        }
    }

    function updateTrailingField() {
        const trailing = drawdownType.value === 'TRAILING';
        trailingField.hidden = !trailing;

        if (trailingInput) {
            trailingInput.required = trailing;
            trailingInput.disabled = !trailing;
        }
    }

    drawdownType.addEventListener('change', updateTrailingField);
    updateTrailingField();
})();

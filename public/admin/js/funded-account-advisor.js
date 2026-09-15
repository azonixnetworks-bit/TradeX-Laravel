(() => {
    'use strict';

    const form = document.getElementById('fundedAdvisorForm');
    const drawdownType = document.getElementById('fundedDrawdownType');
    const trailingField = document.getElementById('fundedTrailingReferenceField');

    if (!form || !drawdownType || !trailingField) {
        return;
    }

    const trailingInput = trailingField.querySelector('input[name="trailing_reference_amount"]');

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

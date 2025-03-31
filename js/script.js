document.addEventListener('DOMContentLoaded', function() {
    // Find the elements related to adding questions in the admin panel
    const questionTypeSelect = document.getElementById('question_type');
    const optionsGroup = document.getElementById('options-group');
    const optionsInput = document.getElementById('options');

    // Function to show/hide the options field based on selected type
    function toggleOptionsField() {
        if (!questionTypeSelect || !optionsGroup) return; // Elements not found

        const selectedType = questionTypeSelect.value;
        const typesWithOptions = ['radio', 'checkbox', 'select'];

        if (typesWithOptions.includes(selectedType)) {
            optionsGroup.style.display = 'block'; // Show the options field
            if (optionsInput) optionsInput.required = true; // Make options required
        } else {
            optionsGroup.style.display = 'none'; // Hide the options field
             if (optionsInput) {
                optionsInput.required = false; // Make options not required
                // optionsInput.value = ''; // Optionally clear the value when hidden
             }
        }
    }

    // Add event listener to the question type select dropdown
    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', toggleOptionsField);

        // Initial check in case the page loads with a type already selected (e.g., validation error)
        toggleOptionsField();
    }

    // Add other client-side JS for validation or enhancements here...
    // Example: Basic required field check (though HTML5 'required' is often sufficient)
    /*
    const surveyForm = document.querySelector('.survey-form');
    if (surveyForm) {
        surveyForm.addEventListener('submit', function(event) {
            let isValid = true;
            // Loop through required fields and check if empty
            surveyForm.querySelectorAll('[required]').forEach(input => {
                if (!input.value.trim() && input.type !== 'checkbox' && input.type !== 'radio') {
                    // Add visual indication of error
                    console.error(`Field ${input.name} is required.`);
                    isValid = false;
                }
                // Add more complex validation for radios/checkboxes if needed
            });

            if (!isValid) {
                event.preventDefault(); // Stop form submission
                alert('Please fill out all required fields.');
            }
        });
    }
    */

}); // End DOMContentLoaded
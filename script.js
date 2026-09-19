// Character Counter for Message Field
document.getElementById("message").addEventListener("input", event => {
    document.getElementById("charCounter").textContent = `${event.target.value.length}/250 characters`;
});

// Date Validation for Future Dates
const dateField = document.getElementById("date");
const today = new Date().toISOString().split("T")[0];
dateField.setAttribute("min", today);

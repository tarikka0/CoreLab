// ŞİFRE GÖSTER/GİZLE (TIKLANABİLİR)
document.querySelector('.toggle-password').addEventListener('click', function() {
    const passwordField = document.getElementById("password-field");
    if (passwordField.type === "password") {
        passwordField.type = "text";
        this.classList.replace("fa-eye", "fa-eye-slash");
    } else {
        passwordField.type = "password";
        this.classList.replace("fa-eye-slash", "fa-eye");
    }
});
function saveGrade(submissionId, element) {
    const grade = element.previousElementSibling.value;
    
    fetch('save_grade.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ submissionId, grade })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            element.style.backgroundColor = '#4CAF50';
            element.textContent = '✓ Kaydedildi';
        }
    });
}
// Not kaydetme fonksiyonu
function saveGrade(submissionId, inputElement) {
    const grade = inputElement.value;
    
    fetch('save_grade.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ submissionId, grade })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            inputElement.style.border = "2px solid #4CAF50";
            setTimeout(() => {
                inputElement.style.border = "1px solid #ddd";
            }, 2000);
        }
    });
}

// Geri bildirim ekleme
function addFeedback(submissionId) {
    const feedback = prompt("Geri bildirim yazın:");
    if (feedback) {
        fetch('save_feedback.php', {
            method: 'POST',
            body: JSON.stringify({ submissionId, feedback })
        });
    }
}
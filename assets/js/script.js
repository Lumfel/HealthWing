// Error display utility
function showError(message) {
    let errorDiv = document.getElementById('modalError');
    if (!errorDiv) {
        errorDiv = document.createElement('div');
        errorDiv.id = 'modalError';
        errorDiv.style.color = 'red';
        errorDiv.style.margin = '10px 0';
        errorDiv.style.textAlign = 'center';
        submitCode.parentNode.insertBefore(errorDiv, submitCode);
    }
    errorDiv.textContent = message;
    setTimeout(() =>{ errorDiv.textContent = ''; }, 3000);
}

// Handle Owner Sign Up
const ownerForm = document.getElementById('signupOwnerForm');
if (ownerForm) {
    ownerForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const data = {
            first_name: document.getElementById('firstname').value.trim(),
            last_name: document.getElementById('lastname').value.trim(),
            email: document.getElementById('email').value.trim(),
            username: document.getElementById('username').value.trim(),
            password: document.getElementById('password').value,
            confirmPassword: document.getElementById('confirmPassword').value,
            role: 'owner',
            age: document.getElementById('age').value.trim(),
            address: document.getElementById('address').value.trim(),
            contact: document.getElementById('contact').value.trim(),
        };
        if (data.password !== data.confirmPassword) {
            alert('Passwords do not match.');
            return;
        }
        fetch('signup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response =>{
            if (response.status === 'success') {
                alert('Account created successfully!');
                ownerForm.reset();
                window.location.href = 'login.php';
            } else if (response.status === 'exists') {
                alert('Username or email already exists.');
            } else {
                alert('Error: '+ response.message);
            }
        })
        .catch(() => alert('An error occurred during sign-up.'));
    });
}

// Handle Veterinarian Sign Up
const vetForm = document.getElementById('signupVetForm');
if (vetForm) {
    vetForm.addEventListener('submit', function(event) {
        event.preventDefault();
        const data = {
            first_name: document.getElementById('firstname').value.trim(),
            last_name: document.getElementById('lastname').value.trim(),
            email: document.getElementById('email').value.trim(),
            username: document.getElementById('username').value.trim(),
            password: document.getElementById('password').value,
            confirmPassword: document.getElementById('confirmPassword').value,
            role: 'veterinarian',
            age: document.getElementById('age') ? document.getElementById('age').value.trim() : '',
            address: document.getElementById('address') ? document.getElementById('address').value.trim() : '',
            contact: document.getElementById('contact') ? document.getElementById('contact').value.trim() : '',
            specialties: document.getElementById('specialties') ? document.getElementById('specialties').value.trim() : ''
        };
        if (data.password !== data.confirmPassword) {
            alert('Passwords do not match.');
            return;
        }
        fetch('signup.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response =>{
            if (response.status === 'success') {
                alert('Your veterinarian account request has been submitted. An admin needs to approve it before you can sign in — you\'ll be notified once that happens.');
                vetForm.reset();
                window.location.href = 'login.php';
            } else if (response.status === 'exists') {
                alert('Username or email already exists.');
            } else {
                alert('Error: '+ response.message);
            }
        })
        .catch(() => alert('An error occurred during sign-up.'));
    });
}
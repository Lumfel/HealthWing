document.getElementById("role").addEventListener("change", function() {
    const role = this.value;
    const specialtiesField = document.getElementById("specialties");
    const specialtiesLabel = document.getElementById("specialtiesLabel");

    if (role === "veterinarian") {
        specialtiesField.style.display = "block";
        specialtiesLabel.style.display = "block";
    } else {
        specialtiesField.style.display = "none";
        specialtiesLabel.style.display = "none";
    }
});

document.getElementById("signupForm").addEventListener("submit", function(event) {
    event.preventDefault();

    const data = {
        first_name: document.getElementById("firstname").value.trim(),
        last_name: document.getElementById("lastname").value.trim(),
        email: document.getElementById("email").value.trim(),
        username: document.getElementById("username").value.trim(),
        password: document.getElementById("password").value,
        confirmPassword: document.getElementById("confirmPassword").value,
        role: document.getElementById("role").value,
        age: document.getElementById("age").value.trim(),
        address: document.getElementById("address").value.trim(),
        contact: document.getElementById("contact").value.trim(),
        specialties: document.getElementById("specialties").value.trim()
    };

    // Password match validation
    if (data.password !== data.confirmPassword) {
        alert("Passwords do not match.");
        return;
    }

    // Role selection validation
    if (!data.role) {
        alert("Please select a role.");
        return;
    }

    // Contact number validation (example 10-digit number)
    const contactRegex = /^[0-9]{10}$/;
    if (!contactRegex.test(data.contact)) {
        alert("Please enter a valid 10-digit contact number.");
        return;
    }

    // Fetch request to send data to PHP
    fetch("signup.php", {
        method: "POST",
        headers: { "Content-Type": "application/json"},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(response =>{
        if (response.status === "success") {
            alert("Account created successfully!");
            document.getElementById("signupForm").reset();  // Reset form fields
            window.location.href = "Sign in html.html";
        } else if (response.status === "exists") {
            alert("Username or email already exists.");
        } else {
            alert("Something went wrong: "+ response.message);
        }
    })
    .catch(error =>{
        console.error("Error:", error);
        alert("An error occurred during sign-up.");
    });
});

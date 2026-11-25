/**
 * HealthWing / VetSystem — Persistent AES-256-GCM Login & Cloudflare Turnstile Handler (JS.js)
 */
document.addEventListener("DOMContentLoaded", function () {
    document.cookie = "password=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;";

    try {
        const savedUsername = localStorage.getItem("hw_saved_username");
        if (savedUsername) {
            const usernameElement = document.getElementById("username");
            if (usernameElement && !usernameElement.value) {
                usernameElement.value = savedUsername;
            }
        }
        const remCheckbox = document.getElementById("rememberMe");
        if (remCheckbox) {
            remCheckbox.checked = true;
        }
    } catch (e) {}

    const loginForm = document.getElementById("loginForm");
    if (loginForm) {
        loginForm.addEventListener("submit", function (event) {
            event.preventDefault();

            const username = document.getElementById("username")?.value.trim();
            const password = document.getElementById("password")?.value;
            const remEl = document.getElementById("rememberMe");
            const rememberMe = remEl ? Boolean(remEl.checked) : true;
            const turnstileInput = loginForm.querySelector('[name="cf-turnstile-response"]');
            const cfTurnstileResponse = turnstileInput ? turnstileInput.value : '';

            if (!username || !password) {
                alert("Please fill in both username and password.");
                return;
            }

            try {
                if (rememberMe) {
                    localStorage.setItem("hw_saved_username", username);
                }
            } catch (e) {}

            fetch("log.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    username,
                    password,
                    remember_me: rememberMe,
                    cf_turnstile_response: cfTurnstileResponse
                })
            })
                .then((res) => res.json())
                .then((response) => {
                    if (response.status === "success") {
                        try {
                            if (response.user_cache_token) {
                                localStorage.setItem("hw_user_cache_gcm", response.user_cache_token);
                            }
                        } catch (e) {}

                        if (response.role === "head_vet") {
                            window.location.replace("admin/ADMIN_VET_1.php");
                        } else if (response.role === "vet_staff" || response.role === "Veterinarian") {
                            window.location.replace("vetindex.php");
                        } else {
                            window.location.replace("index.php");
                        }
                    } else {
                        alert(response.message || "Invalid credentials. Please try again.");
                        if (window.turnstile && typeof window.turnstile.reset === "function") {
                            window.turnstile.reset();
                        }
                    }
                })
                .catch(() => {
                    alert("Could not reach the server. Please check your connection.");
                });
        });
    }
});

function handleLogout() {
    try {
        localStorage.removeItem("hw_user_cache_gcm");
        sessionStorage.removeItem("hw_user_cache_gcm");
        sessionStorage.removeItem("hw_pets_cache");
    } catch (e) {}
    fetch("logout.php", { method: "POST", credentials: "same-origin" })
        .then(() => {
            window.location.replace("login.php");
        })
        .catch(() => {
            window.location.replace("login.php");
        });
}

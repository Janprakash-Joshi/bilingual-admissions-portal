let selectedRole = "";

document.querySelectorAll(".role-card").forEach(card => {
    card.addEventListener("click", () => {
        selectedRole = card.dataset.role;

        // PASS ROLE TO FORM
        document.getElementById("roleInput").value = selectedRole;

        document.getElementById("formTitle").innerText =
            window.portalText[selectedRole + "_login"];

        document.getElementById("roleSelect").classList.add("hidden");
        document.getElementById("loginForm").classList.remove("hidden");

        // Show create account link for student & agent only
        const createLink = document.getElementById("create");

        if (selectedRole === "student") {
            createLink.classList.remove("hidden");
            createLink.innerText =
                window.portalText.create_student_account;

            createLink.href = "pages/registration.html?role=student";
        }
        else if (selectedRole === "agent") {
            createLink.classList.remove("hidden");
            createLink.innerText =
                window.portalText.create_agent_account;

            createLink.href = "pages/registration";
        }
        else {
            // Admin has no registration
            createLink.classList.add("hidden");
        }
    });
});

// Back button
document.getElementById("backBtn").addEventListener("click", () => {
    selectedRole = "";

    document.getElementById("roleInput").value = "";

    document.getElementById("loginForm").classList.add("hidden");
    document.getElementById("roleSelect").classList.remove("hidden");

    document.getElementById("create").classList.add("hidden");
});
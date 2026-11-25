function fetchUserData() {
    const words = window.portalText;

    const popupStyle = {
        customClass: {
            popup: 'password-popup',
            confirmButton: 'password-confirm',
            cancelButton: 'password-cancel'
        },
        buttonsStyling: false
    };

    Swal.fire({
        ...popupStyle,

        title: words.forgot_title,

        input: 'email',
        inputLabel: words.forgot_email_label,
        inputPlaceholder: 'example@gmail.com',

        showCancelButton: true,
        confirmButtonText: words.forgot_send_link,
        cancelButtonText: words.forgot_cancel,

        inputValidator: (value) => {
            if (!value) {
                return words.forgot_email_required;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return words.forgot_email_invalid;
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const email = result.value.trim();

            Swal.fire({
                ...popupStyle,

                title: words.forgot_processing,
                text: words.forgot_sending,

                allowOutsideClick: false,

                didOpen: () => Swal.showLoading()
            });

            fetch('forgotPassword', {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },

                body: new URLSearchParams({ email })
            })
            .then(response => response.json())
            .then(data => {
                Swal.fire({
                    ...popupStyle,

                    title: words.forgot_check_email,

                    html: `
                        <p class="reset-message">
                            ${words.forgot_email_message}
                        </p>

                        <div class="reset-email-box">
                            <strong>${words.forgot_email_caption}</strong>
                            <span data-reset-email></span>
                        </div>

                        <p class="reset-email-hint">
                            ${words.forgot_check_spam}
                        </p>
                    `,

                    icon: 'success',
                    confirmButtonText: words.forgot_ok,

                    didOpen: () => {
                        // Display the entered email as text, not HTML.
                        Swal.getHtmlContainer()
                            .querySelector('[data-reset-email]')
                            .textContent = email;
                    }
                });
            })
            .catch(() => {
                Swal.fire({
                    ...popupStyle,

                    title: words.forgot_error,
                    text: words.forgot_connection_error,

                    icon: 'error',
                    confirmButtonText: words.forgot_ok
                });
            });
        }
    });
}
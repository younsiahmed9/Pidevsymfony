document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.querySelector('.search');

    if (!searchInput) {
        return;
    }

    searchInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
        }
    });
});
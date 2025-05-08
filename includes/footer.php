    </div> <!-- Close container -->
    
    <footer class="bg-dark text-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-heart"></i> Coffin Ordering System</h5>
                    <p class="mb-0">Providing dignified services with care and respect.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/collado/public/coffin_catalog.php" class="text-light">Browse Catalog</a></li>
                        <li><a href="/collado/public/track_order.php" class="text-light">Track Order</a></li>
                        <li><a href="/collado/public/contact.php" class="text-light">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-md-4 text-md-end">
                    <h5>Contact Info</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone"></i> (123) 456-7890</li>
                        <li><i class="fas fa-envelope"></i> info@coffinordering.com</li>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Main St, City</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?= date('Y') ?> Coffin Ordering System. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="/collado/public/privacy.php" class="text-light me-3">Privacy Policy</a>
                    <a href="/collado/public/terms.php" class="text-light">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
        // Enable tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Enable dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl)
        });

        // Add active class to current page in navigation
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname;
            document.querySelectorAll('.nav-link').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>

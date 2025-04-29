<aside id="sidebar">
    <div class="logo-container mx-auto"> 
                <img src="includes/logo.png" alt="Logo" class="logo">
    </div>
    <div class="d-flex">
        <button class="toggle-btn" type="button">
            <i class='bx bx-grid-alt'></i>
        </button>
        <div class="sidebar-logo">
            <a href="#">Financials</a>
        </div>
    </div>

    <!-- LIST OF MODULES -->
    <ul class="sidebar-nav">
        <li class="sidebar-item">
            <a href="dashboard.php" class="sidebar-link">
                <i class='bx bx-user' ></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="viewsched" class="sidebar-link" data-bs-toggle="collapse" data-bs-target="#scheduleAccordion" aria-expanded="false">
                <i class='bx bx-calendar' ></i>
                <span class="text-wrap">Disbursement</span>
            </a>

            <div class="collapse" id="scheduleAccordion">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 1</a></li>
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 2</a></li>
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 3</a></li>
                </ul>
            </div>
        </li>

        <li class="sidebar-item">
            <a href="viewsched" class="sidebar-link" data-bs-toggle="collapse" data-bs-target="#scheduleAccordion1" aria-expanded="false">
                <i class='bx bx-calendar' ></i>
                <span class="text-wrap">Budget Management</span>
            </a>

            <div class="collapse" id="scheduleAccordion1">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item border-0"><a href="budget_dashboard.php" class="list-group-item-text list-hover">Dashboard</a></li>
                    <li class="list-group-item border-0"><a href="budget_allocation.php" class="list-group-item-text list-hover">Allocation</a></li>
                    <li class="list-group-item border-0"><a href="budget_history.php" class="list-group-item-text list-hover">History</a></li>
                    <li class="list-group-item border-0"><a href="budget_report.php" class="list-group-item-text list-hover">Report</a></li>
                </ul>
            </div>
        </li>

        <li class="sidebar-item">
            <a href="viewsched" class="sidebar-link" data-bs-toggle="collapse" data-bs-target="#scheduleAccordion2" aria-expanded="false">
                <i class='bx bx-calendar' ></i>
                <span class="text-wrap">Collection</span>
            </a>

            <div class="collapse" id="scheduleAccordion2">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 1</a></li>
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 2</a></li>
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 3</a></li>
                </ul>
            </div>
        </li>

        <li class="sidebar-item">
            <a href="viewsched" class="sidebar-link" data-bs-toggle="collapse" data-bs-target="#scheduleAccordion3" aria-expanded="false">
                <i class='bx bx-calendar' ></i>
                <span class="text-wrap">Accounts Receivable / Accounts Payable</span>
            </a>

            <div class="collapse" id="scheduleAccordion3">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 1</a></li>
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 2</a></li>
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 3</a></li>
                </ul>
            </div>
        </li>

        <li class="sidebar-item">
            <a href="viewsched" class="sidebar-link" data-bs-toggle="collapse" data-bs-target="#scheduleAccordion4" aria-expanded="false">
                <i class='bx bx-calendar' ></i>
                <span class="text-wrap">General Ledger</span>
            </a>

            <div class="collapse" id="scheduleAccordion4">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 1</a></li>
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 2</a></li>
                    <li class="list-group-item border-0"><a href="#" class="list-group-item-text list-hover">Module Content # 3</a></li>
                </ul>
            </div>
        </li>

        
    </ul>

    <!-- LOGOUT SECTION  -->
    <div class="sidebar-footer">
        <a href="#" class="sidebar-link" data-bs-toggle="modal" data-bs-target="#exampleModal">
            <i class='bx bx-log-out' ></i>    
            <span>Logout</span>
        </a>
    </div>

</aside>





<script>
    const hamBurger = document.querySelector(".toggle-btn");
    const sidebar = document.querySelector("#sidebar");
    const dropdowns = document.querySelectorAll('.collapse');

    hamBurger.addEventListener("click", function () {
        sidebar.classList.toggle("expand");
        
        // If sidebar is being collapsed, close all dropdowns
        if (!sidebar.classList.contains('expand')) {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('show');
            });
            
            // Remove active class from dropdown triggers
            document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(trigger => {
                trigger.classList.remove('active');
                trigger.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Add active class to sidebar items based on current URL
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        const sidebarLinks = document.querySelectorAll('.list-group-item-text, .sidebar-link');
        
        sidebarLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href) {
                // Get the last part of both paths for comparison
                const currentPage = currentPath.split('/').pop();
                const linkPage = href.split('/').pop();
                
                if (currentPage === linkPage) {
                    // Add active class to the link
                    link.classList.add('active');
                    
                    // If it's a submenu item, expand its parent accordion
                    if (link.classList.contains('list-group-item-text')) {
                        const accordionParent = link.closest('.collapse');
                        if (accordionParent) {
                            accordionParent.classList.add('show');
                            const parentButton = document.querySelector(`[data-bs-target="#${accordionParent.id}"]`);
                            if (parentButton) {
                                parentButton.classList.add('active');
                            }
                        }
                    }
                }
            }
        });
    });

    // Expand sidebar when clicking on sidebar items if sidebar is collapsed
    const sidebarItems = document.querySelectorAll('.sidebar-link, .list-group-item-text');
    sidebarItems.forEach(item => {
        item.addEventListener('click', function() {
            if (!sidebar.classList.contains('expand')) {
                sidebar.classList.add('expand');
            }
        });
    });
</script>

<style>
    /* Styles for active items */
    .sidebar-link.active {
        border-left: 3px solid #3b7ddd;
    }
    
    .list-group-item-text.active {
        border-left: 3px solid #3b7ddd;
        padding-left: 10px;
    }
</style>
<?php 
include 'includes/header.php'; 

// Restrict access to administrators only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>

<style>
    /* Custom Admin Styles */
    .admin-sidebar .list-group-item {
        border: none;
        padding: 15px 20px;
        color: #555;
        font-weight: 500;
        transition: all 0.3s;
    }
    .admin-sidebar .list-group-item:hover {
        background-color: #e9ecef;
        color: var(--agri-green);
        border-left: 4px solid var(--agri-green);
    }
    .admin-sidebar .list-group-item.active {
        background-color: var(--agri-light);
        color: var(--agri-green);
        border-left: 4px solid var(--agri-green);
        font-weight: bold;
    }
    .admin-stat-card {
        border-radius: 10px;
        transition: transform 0.2s;
    }
    .admin-stat-card:hover {
        transform: translateY(-5px);
    }
    .system-status-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
    }
</style>

<div class="container-fluid bg-light py-4" style="min-height: 80vh;">
    <div class="container-fluid px-4">
        
        <!-- Admin Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
            <h2 class="fw-bold m-0"><i class="fa-solid fa-server text-dark me-2"></i>System Command Center</h2>
            <div class="text-end">
                <span class="text-muted small d-block">Server Time: <?php echo date('Y-m-d H:i'); ?></span>
                <span class="badge bg-success"><span class="system-status-indicator bg-white me-1"></span> All Systems Operational</span>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- Left Admin Navigation Sidebar -->
            <div class="col-lg-2 col-md-3">
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-dark text-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-bars-staggered me-2"></i>Menu</h6>
                    </div>
                    <div class="list-group list-group-flush admin-sidebar">
                        <a href="#" class="list-group-item active"><i class="fa-solid fa-gauge me-2"></i> Overview</a>
                        <a href="#" class="list-group-item"><i class="fa-solid fa-users me-2"></i> User Management</a>
                        <a href="#" class="list-group-item"><i class="fa-solid fa-microchip me-2"></i> IoT Devices</a>
                        <a href="#" class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-user-check me-2"></i> Approvals</span>
                            <span class="badge bg-danger rounded-pill">12</span>
                        </a>
                        <a href="#" class="list-group-item"><i class="fa-solid fa-robot me-2"></i> AI API Logs</a>
                        <a href="#" class="list-group-item"><i class="fa-solid fa-envelope me-2"></i> SMS Gateway</a>
                    </div>
                </div>
            </div>

            <!-- Main Admin Content Area -->
            <div class="col-lg-10 col-md-9">
                
                <!-- Key Metrics Row -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card admin-stat-card border-0 shadow-sm bg-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 text-uppercase small fw-bold">Total Farmers</p>
                                    <h3 class="fw-bold mb-0 text-dark">1,245</h3>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
                                    <i class="fa-solid fa-tractor fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card admin-stat-card border-0 shadow-sm bg-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 text-uppercase small fw-bold">Active SACCOs</p>
                                    <h3 class="fw-bold mb-0 text-dark">42</h3>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                                    <i class="fa-solid fa-building-columns fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card admin-stat-card border-0 shadow-sm bg-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 text-uppercase small fw-bold">Active IoT Nodes</p>
                                    <h3 class="fw-bold mb-0 text-dark">890</h3>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-circle text-info">
                                    <i class="fa-solid fa-wifi fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="card admin-stat-card border-0 shadow-sm bg-white p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 text-uppercase small fw-bold">API Calls (Today)</p>
                                    <h3 class="fw-bold mb-0 text-dark">3,402</h3>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning">
                                    <i class="fa-solid fa-cloud-arrow-up fa-lg"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Action Tables -->
                <div class="row g-4">
                    
                    <!-- Pending Approvals Module -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom-0">
                                <h5 class="mb-0 fw-bold">Action Required: Pending Registrations</h5>
                                <button class="btn btn-sm btn-outline-secondary">View All</button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-muted small text-uppercase">
                                            <tr>
                                                <th>User Name</th>
                                                <th>Role</th>
                                                <th>Phone Number</th>
                                                <th>Registered At</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-secondary bg-opacity-10 rounded-circle p-2 me-2"><i class="fa-solid fa-user text-secondary"></i></div>
                                                        <span class="fw-bold">John Kamau</span>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-success bg-opacity-10 text-success border border-success">Farmer</span></td>
                                                <td>+254711223344</td>
                                                <td><?php echo date('Y-m-d', strtotime('-1 days')); ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-success me-1" title="Approve"><i class="fa-solid fa-check"></i></button>
                                                    <button class="btn btn-sm btn-outline-danger" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-secondary bg-opacity-10 rounded-circle p-2 me-2"><i class="fa-solid fa-user text-secondary"></i></div>
                                                        <span class="fw-bold">Sarah Ochieng</span>
                                                    </div>
                                                </td>
                                                <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary">Loan Officer</span></td>
                                                <td>+254799887766</td>
                                                <td><?php echo date('Y-m-d'); ?></td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-success me-1" title="Approve"><i class="fa-solid fa-check"></i></button>
                                                    <button class="btn btn-sm btn-outline-danger" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Health & Logs Module -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white py-3 border-bottom-0">
                                <h5 class="mb-0 fw-bold">System Health Logs</h5>
                            </div>
                            <div class="card-body pt-0">
                                <ul class="list-group list-group-flush small">
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold text-success"><i class="fa-solid fa-check-circle me-1"></i> SMS Gateway</div>
                                            Africa's Talking API connected.
                                        </div>
                                        <span class="text-muted">Just now</span>
                                    </li>
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold text-success"><i class="fa-solid fa-check-circle me-1"></i> Gemini AI</div>
                                            Prediction model active.
                                        </div>
                                        <span class="text-muted">10m ago</span>
                                    </li>
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i> Node Timeout</div>
                                            Device MAC: A1:B2... went offline.
                                        </div>
                                        <span class="text-muted">1h ago</span>
                                    </li>
                                </ul>
                                <div class="d-grid mt-3">
                                    <button class="btn btn-light btn-sm text-primary fw-bold">Download Full Logs</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
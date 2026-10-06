<?php 
include 'includes/header.php'; 
if($_SESSION['role'] !== 'loan_officer') header("Location: login.php");
?>

<div class="container-fluid bg-light py-4">
    <div class="container">
        <h2 class="fw-bold mb-4"><i class="fa-solid fa-building-columns text-primary me-2"></i>Lender Portal</h2>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form class="d-flex">
                    <input class="form-control me-2" type="search" placeholder="Search farmer by ID, Name, or Phone">
                    <button class="btn btn-primary px-4" type="submit">Search</button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold">Recent Loan Applicants</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Farmer Name</th>
                                <th>AgroCreditScore</th>
                                <th>IoT Record</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Nelson Nyambega</td>
                                <td><span class="badge bg-success fs-6">88 (Gold)</span></td>
                                <td>95% Consistency</td>
                                <td><button class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-file-pdf me-1"></i>Report</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
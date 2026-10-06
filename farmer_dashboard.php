<?php 
include 'includes/header.php'; 
if($_SESSION['role'] !== 'farmer') header("Location: login.php");
?>

<div class="container-fluid bg-light py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0"><i class="fa-solid fa-tractor text-success me-2"></i>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
            <span class="badge bg-success p-2">Status: Active Season</span>
        </div>

        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card card-dashboard h-100 border-0 p-3">
                    <div class="card-body text-center">
                        <h5 class="text-muted text-uppercase fw-bold mb-3">AgroCreditScore</h5>
                        <div class="display-3 fw-bold text-success mb-2">82</div>
                        <span class="badge text-dark py-2 px-3 rounded-pill" style="background-color: var(--gold-tier);">Silver Tier</span>
                        <p class="small text-muted mt-3">Computed via Random Forest classification of your seasonal IoT data.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="card card-dashboard h-100 border-0 p-3">
                    <div class="card-body">
                        <h5 class="text-muted text-uppercase fw-bold mb-4">Latest Sensor Readings</h5>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span><i class="fa-solid fa-droplet text-primary me-2"></i>Soil Moisture</span><span class="fw-bold">45%</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span><i class="fa-solid fa-temperature-half text-danger me-2"></i>Temperature</span><span class="fw-bold">24°C</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span><i class="fa-solid fa-cloud text-info me-2"></i>Humidity</span><span class="fw-bold">60%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-12">
                <div class="card card-dashboard h-100 border-0 p-3" style="border-left-color: #0dcaf0;">
                    <div class="card-body">
                        <h5 class="text-muted text-uppercase fw-bold mb-3"><i class="fa-solid fa-robot text-info me-2"></i>AI Advisory</h5>
                        <div class="alert alert-info border-0 shadow-sm">
                            <strong>Crop Status: Healthy.</strong> Soil moisture is dropping slightly. Consider light irrigation tomorrow.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
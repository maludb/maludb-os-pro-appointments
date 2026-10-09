<?php
require_once '../../helpers/auth.php';
check_auth();
?>
            <!-- Row starts -->
            <div class="row gx-4">
              <div class="col-sm-12">
                <!-- Card starts -->
                <div class="card">
                  <div class="card-header">
                    <h5 class="card-title">Expenses Summary</h5>
                  </div>
                  <div class="card-body">

                    <!-- Row starts -->
                    <div class="row gx-4">
                      <div class="col-sm-4">

                        <!-- Graph starts -->
                        <div class="graph-body-xl">
                          <div id="income"></div>
                        </div>
                        <!-- Graph ends -->

                        <!-- Stats starts -->
                        <div class="text-center mb-5">
                          <h2 class="m-0">580</h2>
                          <span class="badge bg-secondary-subtle text-dark small mb-2">
                            <i class="feather-alert-circle me-1 text-warning"></i>3 higher income
                          </span>
                          <div class=""><span class="badge bg-dark me-1">+26% Compared to last week</span></div>
                        </div>
                        <!-- Stats ends -->

                      </div>
                      <div class="col-sm-4">

                        <!-- Graph starts -->
                        <div class="graph-body-xl">
                          <div id="expenses"></div>
                        </div>
                        <!-- Graph ends -->

                        <!-- Stats starts -->
                        <div class="text-center mb-5">
                          <h2 class="m-0">680</h2>
                          <span class="badge bg-secondary-subtle text-dark small mb-2">
                            <i class="feather-alert-circle me-1 text-warning"></i>6 higher expenses
                          </span>
                          <div class=""><span class="badge bg-dark me-1">+33% Compared to last week</span></div>
                        </div>
                        <!-- Stats ends -->

                      </div>
                      <div class="col-sm-4">

                        <!-- Graph starts -->
                        <div class="graph-body-xl">
                          <div id="revenue"></div>
                        </div>
                        <!-- Graph ends -->

                        <!-- Stats starts -->
                        <div class="text-center mb-5">
                          <h2 class="m-0">930</h2>
                          <span class="badge bg-secondary-subtle text-dark small mb-2">
                            <i class="feather-alert-circle me-1 text-warning"></i>9 higher revenue
                          </span>
                          <div class=""><span class="badge bg-danger me-1">+49% compared to last week</span></div>
                        </div>
                        <!-- Stats ends -->

                      </div>
                    </div>
                    <!-- Row ends -->

                    <!-- Table starts -->
                    <div class="table-bg">
                      <div class="table-responsive">
                        <table id="customButtons" class="table truncate">
                          <thead>
                            <tr>
                              <th>Category</th>
                              <th>Jan</th>
                              <th>Feb</th>
                              <th>Mar</th>
                              <th>Apr</th>
                              <th>May</th>
                              <th>Jun</th>
                              <th>Jul</th>
                              <th>Aug</th>
                              <th>Sep</th>
                              <th>Oct</th>
                              <th>Nov</th>
                              <th>Dec</th>
                              <th>Year</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td><span class="badge bg-primary">Salary</span></td>
                              <td>$19</td>
                              <td>$0</td>
                              <td>$55</td>
                              <td>$0</td>
                              <td>$30</td>
                              <td>$0</td>
                              <td>$99</td>
                              <td>$0</td>
                              <td>$50</td>
                              <td>$0</td>
                              <td>$29</td>
                              <td>$77</td>
                              <td>$3400</td>
                            </tr>
                            <tr>
                              <td><span class="badge bg-primary">Food</span></td>
                              <td>$10</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$71</td>
                              <td>$0</td>
                              <td>0$69</td>
                              <td>$0</td>
                              <td>$84</td>
                              <td>$0</td>
                              <td>$76</td>
                              <td>$0</td>
                              <td>$55</td>
                              <td>$1800</td>
                            </tr>
                            <tr>
                              <td><span class="badge bg-success">Travel</span></td>
                              <td>$0</td>
                              <td>$30</td>
                              <td>$0</td>
                              <td>$76</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$61</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$87</td>
                              <td>$0</td>
                              <td>$99</td>
                              <td>$1700</td>
                            </tr>
                            <tr>
                              <td><span class="badge bg-danger">Internet</span></td>
                              <td>$0</td>
                              <td>$87</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$22</td>
                              <td>$29</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$76</td>
                              <td>$92</td>
                              <td>$0</td>
                              <td>$66</td>
                              <td>$400</td>
                            </tr>
                            <tr>
                              <td><span class="badge bg-primary">Medical</span></td>
                              <td>$0</td>
                              <td>$39</td>
                              <td>$0</td>
                              <td>$20</td>
                              <td>$0</td>
                              <td>$76</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$10</td>
                              <td>$0</td>
                              <td>$20</td>
                              <td>$0</td>
                              <td>$1900</td>
                            </tr>
                            <tr>
                              <td><span class="badge bg-primary">Rent</span></td>
                              <td>$51</td>
                              <td>$0</td>
                              <td>$30</td>
                              <td>$40</td>
                              <td>$0</td>
                              <td>$76</td>
                              <td>$0</td>
                              <td>$98</td>
                              <td>$0</td>
                              <td>$29</td>
                              <td>$0</td>
                              <td>$33</td>
                              <td>$1200</td>
                            </tr>
                            <tr>
                              <td><span class="badge bg-primary">Insurance</span></td>
                              <td>$0</td>
                              <td>$10</td>
                              <td>$0</td>
                              <td>$20</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$50</td>
                              <td>$0</td>
                              <td>$0</td>
                              <td>$37</td>
                              <td>$0</td>
                              <td>$1300</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <!-- Table ends -->

                  </div>
                </div>
                <!-- Card ends -->

              </div>
            </div>
            <!-- Row ends -->

          </div>

<!-- Page-specific JavaScript -->
<script>
document.addEventListener('htmx:afterSwap', function(evt) {
  if(evt.detail.target.id === 'page-content') {
    // Load DataTables if not already loaded
    if (typeof $.fn.DataTable === 'undefined') {
      loadScript('assets/vendor/datatables/dataTables.min.js', function() {
        loadScript('assets/vendor/datatables/custom/custom-datatables.js', function() {
          // Load DataTable Button extensions
          loadScript('assets/vendor/datatables/buttons/dataTables.buttons.min.js', function() {
            loadScript('assets/vendor/datatables/buttons/jszip.min.js', function() {
              loadScript('assets/vendor/datatables/buttons/pdfmake.min.js', function() {
                loadScript('assets/vendor/datatables/buttons/vfs_fonts.js', function() {
                  loadScript('assets/vendor/datatables/buttons/buttons.html5.min.js', function() {
                    loadScript('assets/vendor/datatables/buttons/buttons.print.min.js', function() {
                      loadScript('assets/vendor/datatables/buttons/buttons.colVis.min.js');
                    });
                  });
                });
              });
            });
          });
        });
      });
    }
  }
});

function loadScript(src, callback) {
  var script = document.createElement('script');
  script.src = src;
  if (callback) {
    script.onload = callback;
  }
  document.body.appendChild(script);
}
</script>

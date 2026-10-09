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
                    <h5 class="card-title">Subscribers</h5>
                  </div>
                  <div class="card-body">

                    <!-- Row starts -->
                    <div class="row gx-4">
                      <div class="col-sm-12 col-12">

                        <!-- Stats starts -->
                        <div class="d-flex flex-wrap gap-3">
                          <div class="d-flex align-items-center mb-5">
                            <div class="icon-box lg bg-danger rounded-circle">
                              <i class="feather-users text-white fs-1"></i>
                            </div>
                            <div class="ms-3">
                              <h2 class="mb-1">6.9</h2>
                              <h6 class="mb-2">Billion People</h6>
                              <div class="badge bg-dark me-1 d-flex">+38% high than last week</div>
                            </div>
                          </div>
                          <div class="d-flex align-items-center mb-5">
                            <div class="icon-box lg bg-danger rounded-circle">
                              <i class="feather-globe text-white fs-1"></i>
                            </div>
                            <div class="ms-3">
                              <h2 class="mb-1">90+</h2>
                              <h6 class="mb-2">Countries</h6>
                              <div class="badge bg-dark me-1 d-flex">+66% high than last week</div>
                            </div>
                          </div>
                          <div class="d-flex align-items-center mb-5">
                            <div class="icon-box lg bg-danger rounded-circle">
                              <i class="feather-clock text-white fs-1"></i>
                            </div>
                            <div class="ms-3">
                              <h2 class="mb-1">2</h2>
                              <h6 class="mb-2">Billion Hours</h6>
                              <div class="badge bg-dark me-1 d-flex">+89% high than last week</div>
                            </div>
                          </div>
                        </div>
                        <!-- Stats ends -->

                      </div>
                    </div>
                    <!-- Row ends -->

                    <!-- Row starts -->
                    <div class="row gx-4">
                      <div class="col-sm-12 col-12">

                        <!-- Card starts -->
                        <div class="card">
                          <div class="card-body">
                            <div id="subscribersData"></div>
                          </div>
                        </div>
                        <!-- Card ends -->

                      </div>
                    </div>
                    <!-- Row ends -->

                    <!-- Table starts -->
                    <div class="table-bg">
                      <div class="table-responsive">
                        <table id="customButtons" class="table truncate">
                          <thead>
                            <tr>
                              <th>#</th>
                              <th>Type</th>
                              <th>Customer</th>
                              <th>Location</th>
                              <th>Billing Date</th>
                              <th>Status</th>
                              <th>Subscription Date</th>
                              <th>Amount</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td>1</td>
                              <td>Gold</td>
                              <td>Hearty Pancake</td>
                              <td>United States</td>
                              <td>2023/04/25</td>
                              <td><span class="badge bg-primary">Active</span></td>
                              <td>2023/04/25</td>
                              <td>$250.00</td>
                            </tr>
                            <tr>
                              <td>2</td>
                              <td>Silver</td>
                              <td>Serge Baldwin</td>
                              <td>India</td>
                              <td>2023/09/18</td>
                              <td><span class="badge bg-primary">Active</span></td>
                              <td>2023/15/12</td>
                              <td>$150.00</td>
                            </tr>
                            <tr>
                              <td>3</td>
                              <td>Diamond</td>
                              <td>Zenaida Frank</td>
                              <td>Srikanka</td>
                              <td>2023/05/30</td>
                              <td><span class="badge bg-primary">Active</span></td>
                              <td>2023/10/15</td>
                              <td>$750.00</td>
                            </tr>
                            <tr>
                              <td>4</td>
                              <td>Gold</td>
                              <td>Zorita Serrano</td>
                              <td>Germany</td>
                              <td>2023/03/12</td>
                              <td><span class="badge bg-danger">Inactive</span></td>
                              <td>2023/04/19</td>
                              <td>$250.00</td>
                            </tr>
                            <tr>
                              <td>5</td>
                              <td>Silver</td>
                              <td>Jennifer Acosta</td>
                              <td>Brazil</td>
                              <td>2023/10/15</td>
                              <td><span class="badge bg-primary">Active</span></td>
                              <td>2023/03/22</td>
                              <td>$350.00</td>
                            </tr>
                            <tr>
                              <td>6</td>
                              <td>Silver</td>
                              <td>Cara Stevens</td>
                              <td>France</td>
                              <td>2023/02/14</td>
                              <td><span class="badge bg-primary">Active</span></td>
                              <td>2023/06/10</td>
                              <td>$150.00</td>
                            </tr>
                            <tr>
                              <td>7</td>
                              <td>Diamond</td>
                              <td>Hermione Butler</td>
                              <td>Russia</td>
                              <td>2023/07/18</td>
                              <td><span class="badge bg-primary">Active</span></td>
                              <td>2023/09/13</td>
                              <td>$750.00</td>
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

<?php
require_once '../../helpers/auth.php';
check_auth();
?>
            <!-- Row starts -->
            <div class="row gx-4">
              <div class="col-xl-3 col-sm-6 col-12">
                <div class="card mb-4">
                  <div class="card-body">
                    <div class="d-flex mb-2">
                      <div class="me-3">
                        <i class="feather-aperture fs-1 text-danger"></i>
                      </div>
                      <div class="d-flex flex-column">
                        <h2 class="mb-1 lh-1">200</h2>
                        <p class="m-0 opacity-50">Tickets</p>
                      </div>
                    </div>
                    <div class="m-0">
                      <div class="progress thin mb-3">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 75%" aria-valuenow="75"
                          aria-valuemin="0" aria-valuemax="100"></div>
                      </div>
                      <span class="badge bg-danger me-1">75% completed.</span>
                      <div class="mt-2">
                        <div class="small">Last updated on <span class="opacity-50">Jan 10, 5:30:59 AM</span></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-xl-3 col-sm-6 col-12">
                <div class="card mb-4">
                  <div class="card-body">
                    <div class="d-flex mb-2">
                      <div class="me-3">
                        <i class="feather-lock fs-1 text-danger"></i>
                      </div>
                      <div class="d-flex flex-column">
                        <h2 class="mb-1 lh-1">300</h2>
                        <p class="m-0 opacity-50">In Progress</p>
                      </div>
                    </div>
                    <div class="m-0">
                      <div class="progress thin mb-3">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 75%" aria-valuenow="75"
                          aria-valuemin="0" aria-valuemax="100"></div>
                      </div>
                      <span class="badge bg-danger me-1">70% completed.</span>
                      <div class="mt-2">
                        <div class="small">Last updated on <span class="opacity-50">Jan 14, 3:33:49 AM</span></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-xl-3 col-sm-6 col-12">
                <div class="card mb-4">
                  <div class="card-body">
                    <div class="d-flex mb-2">
                      <div class="me-3">
                        <i class="feather-clock-history fs-1 text-danger"></i>
                      </div>
                      <div class="d-flex flex-column">
                        <h2 class="mb-1 lh-1">200</h2>
                        <p class="m-0 opacity-50">On Hold</p>
                      </div>
                    </div>
                    <div class="m-0">
                      <div class="progress thin mb-3">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 75%" aria-valuenow="75"
                          aria-valuemin="0" aria-valuemax="100"></div>
                      </div>
                      <span class="badge bg-danger me-1">65% completed.</span>
                      <div class="mt-2">
                        <div class="small">Last updated on <span class="opacity-50">Jan 16, 8:20:39 AM</span></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-xl-3 col-sm-6 col-12">
                <div class="card mb-4 card-bg">
                  <div class="card-body text-white">
                    <div class="d-flex mb-2">
                      <div class="me-3">
                        <i class="feather-check-circle fs-1 text-white"></i>
                      </div>
                      <div class="d-flex flex-column">
                        <h2 class="mb-1 lh-1">400</h2>
                        <p class="m-0 opacity-50">Completed</p>
                      </div>
                    </div>
                    <div class="m-0">
                      <div class="progress thin mb-3">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 75%" aria-valuenow="75"
                          aria-valuemin="0" aria-valuemax="100"></div>
                      </div>
                      <span class="badge bg-danger me-1">90% completed.</span>
                      <div class="mt-2">
                        <div class="small">Last updated on <span class="opacity-50">Jan 18, 8:45:45 AM</span></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <!-- Row ends -->

            <!-- Row starts -->
            <div class="row gx-4">
              <div class="col-sm-12">
                <!-- Card starts -->
                <div class="card">
                  <div class="card-header">
                    <h5 class="card-title">Support Summary</h5>
                  </div>
                  <div class="card-body">

                    <!-- Row starts -->
                    <div class="row gx-4">
                      <div class="col-xl-6 col-sm-12">
                        <!-- Card starts -->
                        <div class="card border mb-4">
                          <div class="card-header">
                            <h5 class="card-title">Tickets</h5>
                          </div>
                          <div class="card-body">
                            <div class="graph-body">
                              <div id="tickets"></div>
                            </div>
                          </div>
                        </div>
                        <!-- Card ends -->
                      </div>
                      <div class="col-xl-6 col-sm-12">
                        <!-- Card starts -->
                        <div class="card border mb-4">
                          <div class="card-header">
                            <h5 class="card-title">Calls</h5>
                          </div>
                          <div class="card-body">
                            <div class="graph-body">
                              <div id="calls"></div>
                            </div>
                          </div>
                        </div>
                        <!-- Card ends -->
                      </div>
                    </div>
                    <!-- Row ends -->

                    <!-- Table starts -->
                    <div class="table-bg">
                      <div class="table-responsive">
                        <table id="basicExample" class="table truncate align-middle">
                          <thead>
                            <tr>
                              <th>#</th>
                              <th>Subject</th>
                              <th>Status</th>
                              <th>Tags</th>
                              <th>Created Date</th>
                              <th>Last Reply</th>
                              <th>Priority</th>
                              <th>Department</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td>1</td>
                              <td>Alignment UI issue fix</td>
                              <td><span class="badge bg-primary">In Progress</span></td>
                              <td>
                                <span class="badge border border-primary text-primary">Bug</span>
                                <span class="badge border border-primary text-primary">Design</span>
                              </td>
                              <td>2023/04/25</td>
                              <td>2 mins ago</td>
                              <td><span class="badge bg-danger">High</span></td>
                              <td>Sales</td>
                            </tr>
                            <tr>
                              <td>2</td>
                              <td>Responsive Design Issues Fix</td>
                              <td><span class="badge bg-dark">Not Started</span></td>
                              <td>
                                <span class="badge border border-primary text-primary">Sales</span>
                                <span class="badge border border-primary text-primary">Testing</span>
                              </td>
                              <td>2023/02/12</td>
                              <td>7 mins ago</td>
                              <td><span class="badge bg-primary">Medium</span></td>
                              <td>Support</td>
                            </tr>
                            <tr>
                              <td>3</td>
                              <td>Unit Testing</td>
                              <td><span class="badge bg-primary">Feedback</span></td>
                              <td>
                                <span class="badge border border-primary text-primary">Fix</span>
                                <span class="badge border border-primary text-primary">Sales</span>
                              </td>
                              <td>2023/03/16</td>
                              <td>12 mins ago</td>
                              <td><span class="badge bg-primary">Low</span></td>
                              <td>Development</td>
                            </tr>
                            <tr>
                              <td>4</td>
                              <td>Validations</td>
                              <td><span class="badge bg-primary">In Progress</span></td>
                              <td>
                                <span class="badge border border-primary text-primary">Bug</span>
                                <span class="badge border border-dark text-dark">Development</span>
                              </td>
                              <td>2023/04/25</td>
                              <td>45 mins ago</td>
                              <td><span class="badge bg-danger">High</span></td>
                              <td>Sales</td>
                            </tr>
                            <tr>
                              <td>5</td>
                              <td>Testing and UI Issues Fix</td>
                              <td><span class="badge bg-primary">Testing</span></td>
                              <td>
                                <span class="badge border border-primary text-primary">Validation</span>
                                <span class="badge border border-primary text-primary">Fix</span>
                              </td>
                              <td>2023/02/12</td>
                              <td>58 mins ago</td>
                              <td><span class="badge bg-dark">Low</span></td>
                              <td>Support</td>
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
        loadScript('assets/vendor/datatables/dataTables.bootstrap.min.js', function() {
          loadScript('assets/vendor/datatables/custom/custom-datatables.js');
        });
      });
    }
    
    // Load Apex Charts if not already loaded
    if (typeof ApexCharts === 'undefined') {
      loadScript('assets/vendor/apex/apexcharts.min.js', function() {
        loadScript('assets/vendor/apex/custom/support/tickets.js');
        loadScript('assets/vendor/apex/custom/support/calls.js');
      });
    } else {
      loadScript('assets/vendor/apex/custom/support/tickets.js');
      loadScript('assets/vendor/apex/custom/support/calls.js');
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

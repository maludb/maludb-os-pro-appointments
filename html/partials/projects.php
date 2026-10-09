<?php
require_once '../../helpers/auth.php';
check_auth();
?>
            <div class="row gx-4">
              <div class="col-sm-12">
                <!-- Card starts -->
                <div class="card">
                  <div class="card-header">
                    <h5 class="card-title">Projects Summary</h5>
                  </div>
                  <div class="card-body">

                    <!-- Row starts -->
                    <div class="row gx-4">
                      <div class="col-sm-12 col-12">

                        <div class="d-flex flex-wrap gap-3">
                          <div class="d-flex align-items-center mb-2">
                            <img src="assets/images/flags/1x1/us.svg" class="img-4x rounded-circle border"
                              alt="United States">
                            <div class="ms-3">
                              <h2 class="mb-1">200M</h2>
                              <h6 class="mb-2">United States</h6>
                              <span class="badge bg-secondary-subtle text-dark mb-3">
                                <i class="feather-alert-circle me-1 text-danger"></i>3 new projects
                              </span>
                              <div class="badge bg-primary-subtle text-primary me-1 d-flex">+38% high than last week
                              </div>
                            </div>
                          </div>
                          <div class="d-flex align-items-center mb-2">
                            <img src="assets/images/flags/1x1/au.svg" class="img-4x rounded-circle border"
                              alt="Australia">
                            <div class="ms-3">
                              <h2 class="mb-1">300M</h2>
                              <h6 class="mb-2">Australia</h6>
                              <span class="badge bg-secondary-subtle text-dark mb-3">
                                <i class="feather-alert-circle me-1 text-danger"></i>6 new projects
                              </span>
                              <div class="badge bg-primary-subtle text-primary me-1 d-flex">+66% high than last week
                              </div>
                            </div>
                          </div>
                          <div class="d-flex align-items-center mb-2">
                            <img src="assets/images/flags/1x1/id.svg" class="img-4x rounded-circle border"
                              alt="Indonesia">
                            <div class="ms-3">
                              <h2 class="mb-1">600M</h2>
                              <h6 class="mb-2">Indonesia</h6>
                              <span class="badge bg-secondary-subtle text-dark mb-3">
                                <i class="feather-alert-circle me-1 text-danger"></i>9 new projects
                              </span>
                              <div class="badge bg-danger-subtle text-danger me-1 d-flex">+89% high than last week</div>
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
                        <div class="card mb-4">
                          <div class="card-body">
                            <div class="graph-body-xl">
                              <div id="projects"></div>
                            </div>
                          </div>
                        </div>
                        <!-- Card ends -->

                      </div>
                      <div class="col-sm-12">

                        <!-- Table starts -->
                        <div class="table-bg">
                          <div class="table-responsive">
                            <table id="customButtons" class="table truncate">
                              <thead>
                                <tr>
                                  <th>#</th>
                                  <th>Project Name</th>
                                  <th>Company</th>
                                  <th>Status</th>
                                  <th>Tags</th>
                                  <th>Team</th>
                                  <th>Start Date</th>
                                  <th>End Date</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr>
                                  <td>1</td>
                                  <td>Website Design</td>
                                  <td>Hearty Inc</td>
                                  <td><span class="badge bg-primary">In Progress</span></td>
                                  <td>
                                    <span class="badge border border-primary text-primary">Design</span>
                                    <span class="badge border border-primary text-primary">High Budget</span>
                                  </td>
                                  <td>7 Members</td>
                                  <td>2023/04/15</td>
                                  <td>2023/04/25</td>
                                </tr>
                                <tr>
                                  <td>2</td>
                                  <td>App Design</td>
                                  <td>Gill Co</td>
                                  <td><span class="badge bg-primary">Not Started</span></td>
                                  <td>
                                    <span class="badge border border-primary text-primary">UX</span>
                                    <span class="badge border border-dark text-dark">High Budget</span>
                                  </td>
                                  <td>9 Members</td>
                                  <td>2023/02/18</td>
                                  <td>2023/03/17</td>
                                </tr>
                                <tr>
                                  <td>3</td>
                                  <td>UI/UX Design</td>
                                  <td>Star Windy Co</td>
                                  <td><span class="badge bg-primary">On Hold</span></td>
                                  <td>
                                    <span class="badge border border-primary text-primary">Figma</span>
                                    <span class="badge border border-dark text-dark">Low Budget</span>
                                  </td>
                                  <td>4 Members</td>
                                  <td>2023/03/19</td>
                                  <td>2023/05/29</td>
                                </tr>
                                <tr>
                                  <td>4</td>
                                  <td>Frontend Development</td>
                                  <td>Ready Continental</td>
                                  <td><span class="badge bg-success">Finished</span></td>
                                  <td>
                                    <span class="badge border border-primary text-primary">Design</span>
                                    <span class="badge border border-primary text-primary">Top Brand</span>
                                  </td>
                                  <td>24 Members</td>
                                  <td>2023/01/22</td>
                                  <td>2023/06/20</td>
                                </tr>
                                <tr>
                                  <td>5</td>
                                  <td>UX Design</td>
                                  <td>Trendy Scissor</td>
                                  <td><span class="badge bg-danger">Started</span></td>
                                  <td>
                                    <span class="badge border border-primary text-primary">Development</span>
                                    <span class="badge border border-primary text-primary">Low Budget</span>
                                  </td>
                                  <td>11 Members</td>
                                  <td>2023/02/11</td>
                                  <td>2023/04/28</td>
                                </tr>
                                <tr>
                                  <td>6</td>
                                  <td>App Design</td>
                                  <td>The Fresh Breakfast</td>
                                  <td><span class="badge bg-dark">Cancelled</span></td>
                                  <td>
                                    <span class="badge border border-primary text-primary">UX</span>
                                    <span class="badge border border-primary text-primary">Low Budget</span>
                                  </td>
                                  <td>2 Members</td>
                                  <td>2023/02/11</td>
                                  <td>2023/04/28</td>
                                </tr>
                                <tr>
                                  <td>7</td>
                                  <td>Web Development</td>
                                  <td>Gadget Man</td>
                                  <td><span class="badge bg-primary">On Hold</span></td>
                                  <td>
                                    <span class="badge border border-primary text-primary">UI/UX</span>
                                    <span class="badge border border-primary text-primary">High Budget</span>
                                  </td>
                                  <td>18 Members</td>
                                  <td>2023/01/15</td>
                                  <td>2023/05/10</td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                        </div>
                        <!-- Table ends -->

                      </div>
                    </div>
                    <!-- Row ends -->

                  </div>
                </div>
                <!-- Card ends -->

              </div>
            </div>
            <!-- Row ends -->


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

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
        <h5 class="card-title">Customers Summary</h5>
      </div>
      <div class="card-body">

        <!-- Row starts -->
        <div class="row gx-4">
          <div class="col-sm-12 col-12">

            <div class="d-flex flex-wrap gap-3">
              <div class="position-relative">
                <div class="d-flex align-items-center mb-2">
                  <img src="assets/images/flags/1x1/us.svg" class="img-4x rounded-circle"
                    alt="United States">
                  <div class="ms-3">
                    <h2 class="mb-1">200M</h2>
                    <h6 class="mb-2">United States</h6>
                    <span class="badge bg-primary-subtle text-primary me-1">+33% high than last week</span>
                  </div>
                </div>
              </div>
              <div class="position-relative">
                <div class="d-flex align-items-center mb-2">
                  <img src="assets/images/flags/1x1/br.svg" class="img-4x rounded-circle" alt="Brazil">
                  <div class="ms-3">
                    <h2 class="mb-1">300M</h2>
                    <h6 class="mb-2">Brazil</h6>
                    <span class="badge bg-primary-subtle text-primary me-1">+28% high than last week</span>
                  </div>
                </div>
              </div>
              <div class="d-flex align-items-center mb-2">
                <img src="assets/images/flags/1x1/in.svg" class="img-4x rounded-circle" alt="India">
                <div class="ms-3">
                  <h2 class="mb-1">800M</h2>
                  <h6 class="mb-2">India</h6>
                  <span class="badge bg-danger-subtle text-danger me-1">+48% high than last week</span>
                </div>
              </div>
            </div>

          </div>
        </div>
        <!-- Row ends -->

        <!-- Graph starts -->
        <div class="map-body-xxl mt-5 position-relative">
          <div class="card-loader">
            <div class="spinner-border text-warning"></div>
          </div>
          <div class="customers">
            <div class="map">
              <span>Alternative content for the map</span>
            </div>
            <div class="areaLegend">
              <span>Alternative content for the legend</span>
            </div>
            <div class="plotLegend">
              <span>Alternative content for the legend</span>
            </div>
          </div>
        </div>
        <!-- Graph ends -->

        <!-- Table starts -->
        <div class="mt-3 table-bg">
          <div class="table-responsive">
            <table id="customButtons" class="table truncate">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Company</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Status</th>
                  <th>Tags</th>
                  <th>Date Created</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>1</td>
                  <td>Tiger Nixon</td>
                  <td>Hearty Pancake</td>
                  <td>company@testing.com</td>
                  <td>000-989-992-1</td>
                  <td><span class="badge bg-primary">Active</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Retailer</span>
                    <span class="badge border border-primary text-primary">High Budget</span>
                  </td>
                  <td>2023/04/25</td>
                </tr>
                <tr>
                  <td>2</td>
                  <td>Serge Baldwin</td>
                  <td>Hellow World Kids</td>
                  <td>company@testing.com</td>
                  <td>887-332-090-2</td>
                  <td><span class="badge bg-dark">Inctive</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">High Budget</span>
                  </td>
                  <td>2023/04/09</td>
                </tr>
                <tr>
                  <td>3</td>
                  <td>Zenaida Frank</td>
                  <td>Gourmet Sandwich</td>
                  <td>company@testing.com</td>
                  <td>222-333-222-0</td>
                  <td><span class="badge bg-primary">Idle</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Retailer</span>
                    <span class="badge border border-dark text-dark">Low Budget</span>
                  </td>
                  <td>2023/01/04</td>
                </tr>
                <tr>
                  <td>4</td>
                  <td>Zorita Serrano</td>
                  <td>Ready Continental</td>
                  <td>company@testing.com</td>
                  <td>565-676-889-0</td>
                  <td><span class="badge bg-primary">Inctive</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Wholesaler</span>
                    <span class="badge border border-primary text-primary">Low Budget</span>
                  </td>
                  <td>2023/06/01</td>
                </tr>
                <tr>
                  <td>5</td>
                  <td>Jennifer Acosta</td>
                  <td>Trendy Scissor</td>
                  <td>company@testing.com</td>
                  <td>222-312-222-9</td>
                  <td><span class="badge bg-primary">New</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Retailer</span>
                    <span class="badge border border-primary text-primary">High Budget</span>
                  </td>
                  <td>2023/02/01</td>
                </tr>
                <tr>
                  <td>6</td>
                  <td>Cara Stevens</td>
                  <td>The Fresh Breakfast</td>
                  <td>company@testing.com</td>
                  <td>772-009-989-1</td>
                  <td><span class="badge bg-primary">Active</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Wholesaler</span>
                    <span class="badge border border-primary text-primary">High Budget</span>
                  </td>
                  <td>2023/12/06</td>
                </tr>
                <tr>
                  <td>7</td>
                  <td>Hermione Butler</td>
                  <td>Gadget Man</td>
                  <td>company@testing.com</td>
                  <td>223-332-434-2</td>
                  <td><span class="badge bg-primary">Active</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Wholesaler</span>
                  </td>
                  <td>2023/03/21</td>
                </tr>
                <tr>
                  <td>8</td>
                  <td>Lael Greer</td>
                  <td>Urban Gallery</td>
                  <td>company@testing.com</td>
                  <td>999-000-989-0</td>
                  <td><span class="badge bg-primary">New</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Wholesaler</span>
                    <span class="badge border border-success text-success">High Budget</span>
                  </td>
                  <td>2023/02/27</td>
                </tr>
                <tr>
                  <td>9</td>
                  <td>Jonas Alexander</td>
                  <td>The Spice Route</td>
                  <td>company@testing.com</td>
                  <td>554-444-999-3</td>
                  <td><span class="badge bg-primary">New</span></td>
                  <td>
                    <span class="badge border border-secondary text-dark">Low Budget</span>
                  </td>
                  <td>2023/07/14</td>
                </tr>
                <tr>
                  <td>10</td>
                  <td>Shad Decker</td>
                  <td>Death By Milkshake</td>
                  <td>company@testing.com</td>
                  <td>332-332-332-1</td>
                  <td><span class="badge bg-primary">Active</span></td>
                  <td>
                    <span class="badge border border-danger text-danger">Retailer</span>
                    <span class="badge border border-success text-success">High Budget</span>
                  </td>
                  <td>2023/11/13</td>
                </tr>
                <tr>
                  <td>11</td>
                  <td>Michael Bruce</td>
                  <td>Coal Kings</td>
                  <td>company@testing.com</td>
                  <td>232-223-322-5</td>
                  <td><span class="badge bg-primary">Idle</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Wholesaler</span>
                  </td>
                  <td>2023/06/27</td>
                </tr>
                <tr>
                  <td>12</td>
                  <td>Donna Snider</td>
                  <td>Customer Support</td>
                  <td>company@testing.com</td>
                  <td>776-665-999-0</td>
                  <td><span class="badge bg-primary">Active</span></td>
                  <td>
                    <span class="badge border border-danger text-danger">Retailer</span>
                    <span class="badge border border-success text-success">High Budget</span>
                  </td>
                  <td>2023/01/25</td>
                </tr>
                <tr>
                  <td>13</td>
                  <td>Gaja Ryola</td>
                  <td>The First Step</td>
                  <td>company@testing.com</td>
                  <td>112-222-887-7</td>
                  <td><span class="badge bg-dark">Active</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Wholesaler</span>
                    <span class="badge border border-success text-success">High Budget</span>
                  </td>
                  <td>2023/08/22</td>
                </tr>
                <tr>
                  <td>14</td>
                  <td>Vlamir Philks</td>
                  <td>Easy Wings LLC</td>
                  <td>company@testing.com</td>
                  <td>667-887-998-5</td>
                  <td><span class="badge bg-success">Active</span></td>
                  <td>
                    <span class="badge border border-primary text-primary">Wholesaler</span>
                  </td>
                  <td>2023/12/18</td>
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

<!-- Page-specific JavaScript -->
<script>
document.addEventListener('htmx:afterSwap', function(evt) {
  if(evt.detail.target.id === 'page-content') {
    // Load DataTables scripts if not already loaded
    function loadDataTablesScripts() {
      // First load the core DataTables
      loadScript('assets/vendor/datatables/dataTables.min.js', function() {
        loadScript('assets/vendor/datatables/dataTables.bootstrap.min.js', function() {
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
      });
    }

    // Load NoUiSlider
    loadScript('assets/vendor/nouislider/js/nouislider.js');

    // Load Mapael scripts
    loadScript('assets/js/raphael.min.js', function() {
      loadScript('assets/vendor/mapael/jquery.mapael.min.js', function() {
        loadScript('assets/vendor/mapael/maps/france_departments.min.js', function() {
          loadScript('assets/vendor/mapael/maps/world_countries.min.js', function() {
            loadScript('assets/vendor/mapael/maps/usa_states.min.js', function() {
              loadScript('assets/vendor/mapael/custom/customers.js');
            });
          });
        });
      });
    });

    // Check if DataTables is already loaded
    if (typeof $.fn.DataTable === 'undefined') {
      loadDataTablesScripts();
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
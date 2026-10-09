<?php
require_once '../../helpers/auth.php';
check_auth();
?>
<!-- Row starts -->
<div class="row gx-4">
  <div class="col-xl col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="mb-3">New</h6>
        <h2 class="mb-2 d-flex align-items-center justify-content-between">
          <i class="feather-box fs-3 lh-1 bg-primary p-3 rounded-circle text-white"></i>
          <span>500</span>
        </h2>
        <p class="m-0 small">
          Higher than last month<span class="float-end badge bg-primary-subtle text-primary">24% <i
              class="feather-arrow-up-right"></i></span>
        </p>
      </div>
    </div>
  </div>
  <div class="col-xl col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="mb-3">Delivered</h6>
        <h2 class="mb-2 d-flex align-items-center justify-content-between">
          <i class="feather-bar-chart-2 fs-3 lh-1 bg-primary p-3 rounded-circle text-white"></i>
          <span>900</span>
        </h2>
        <p class="m-0 small">
          Higher than last month<span class="float-end badge bg-primary-subtle text-primary">45% <i
              class="feather-arrow-up-right"></i></span>
        </p>
      </div>
    </div>
  </div>
  <div class="col-xl col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">
        <h6 class="mb-3">Pending</h6>
        <h2 class="mb-2 d-flex align-items-center justify-content-between">
          <i class="feather-clipboard fs-3 lh-1 bg-primary p-3 rounded-circle text-white"></i>
          <span>900</span>
        </h2>
        <p class="m-0 small">
          Higher than last month<span class="float-end badge bg-primary-subtle text-primary">32% <i
              class="feather-arrow-up-right"></i></span>
        </p>
      </div>
    </div>
  </div>
  <div class="col-xl col-sm-6 col-12">
    <div class="card mb-4 card-bg">
      <div class="card-body text-white">
        <h6 class="mb-3">Cancelled</h6>
        <h2 class="mb-2 d-flex align-items-center justify-content-between">
          <i class="feather-credit-card2 fs-3 lh-1 bg-white p-3 rounded-circle text-danger"></i>
          <span>200</span>
        </h2>
        <p class="m-0 small">
          Higher than last month<span class="float-end badge bg-light-subtle text-danger">28% <i
              class="feather-arrow-down-right"></i></span>
        </p>
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
        <h5 class="card-title">Orders Summary</h5>
      </div>
      <div class="card-body">

        <!-- Row starts -->
        <div class="row gx-4">
          <div class="col-sm-6">

            <!-- Card starts -->
            <div class="card border mb-4">
              <div class="card-header">
                <h5 class="card-title">Orders by Type</h5>
              </div>
              <div class="card-body">
                <div class="graph-body auto-align-graph">
                  <div id="type"></div>
                </div>
              </div>
            </div>
            <!-- Card ends -->

          </div>
          <div class="col-sm-6">

            <!-- Card starts -->
            <div class="card border mb-4">
              <div class="card-header">
                <h5 class="card-title">Orders by Value</h5>
              </div>
              <div class="card-body">
                <div class="graph-body auto-align-graph">
                  <div id="value"></div>
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
                      <th>Order Type</th>
                      <th>Company</th>
                      <th>Email</th>
                      <th>Phone</th>
                      <th>Value</th>
                      <th>Start Date</th>
                      <th>End Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>1</td>
                      <td>Fixed-price contract</td>
                      <td>Hearty Pancake</td>
                      <td>company@testing.com</td>
                      <td>000-989-992-1</td>
                      <td>$32,000</td>
                      <td>2023/09/18</td>
                      <td>2023/12/20</td>
                    </tr>
                    <tr>
                      <td>2</td>
                      <td>Cost-reimbursement contract</td>
                      <td>Hellow World Kids</td>
                      <td>company@testing.com</td>
                      <td>887-332-090-2</td>
                      <td>$25,000</td>
                      <td>2023/02/09</td>
                      <td>2023/04/09</td>
                    </tr>
                    <tr>
                      <td>3</td>
                      <td>Cost-plus contract</td>
                      <td>Gourmet Sandwich</td>
                      <td>company@testing.com</td>
                      <td>222-333-222-0</td>
                      <td>$25,000</td>
                      <td>2023/08/28</td>
                      <td>2023/12/23</td>
                    </tr>
                    <tr>
                      <td>4</td>
                      <td>Time and materials contract</td>
                      <td>Ready Continental</td>
                      <td>company@testing.com</td>
                      <td>565-676-889-0</td>
                      <td>$33,000</td>
                      <td>2023/06/29</td>
                      <td>2023/09/29</td>
                    </tr>
                    <tr>
                      <td>5</td>
                      <td>Unit price contract</td>
                      <td>Trendy Scissor</td>
                      <td>company@testing.com</td>
                      <td>222-312-222-9</td>
                      <td>$54,000</td>
                      <td>2023/05/22</td>
                      <td>2023/08/22</td>
                    </tr>
                    <tr>
                      <td>6</td>
                      <td>Bilateral contract</td>
                      <td>The Fresh Breakfast</td>
                      <td>company@testing.com</td>
                      <td>772-009-989-1</td>
                      <td>$43,000</td>
                      <td>2023/04/10</td>
                      <td>2023/06/10</td>
                    </tr>
                    <tr>
                      <td>7</td>
                      <td>Unilateral contract</td>
                      <td>Gadget Man</td>
                      <td>company@testing.com</td>
                      <td>223-332-434-2</td>
                      <td>$38,000</td>
                      <td>2023/07/15</td>
                      <td>2023/09/21</td>
                    </tr>
                    <tr>
                      <td>8</td>
                      <td>Implied contract</td>
                      <td>Urban Gallery</td>
                      <td>company@testing.com</td>
                      <td>999-000-989-0</td>
                      <td>$44,000</td>
                      <td>2023/08/12</td>
                      <td>2023/09/12</td>
                    </tr>
                    <tr>
                      <td>9</td>
                      <td>Express contract</td>
                      <td>The Spice Route</td>
                      <td>company@testing.com</td>
                      <td>554-444-999-3</td>
                      <td>$87,000</td>
                      <td>2023/06/10</td>
                      <td>2023/09/10</td>
                    </tr>
                    <tr>
                      <td>10</td>
                      <td>Simple contract</td>
                      <td>Death By Milkshake</td>
                      <td>company@testing.com</td>
                      <td>332-332-332-1</td>
                      <td>$74,000</td>
                      <td>2023/02/10</td>
                      <td>2023/05/15</td>
                    </tr>
                    <tr>
                      <td>11</td>
                      <td>Unconscionable contract</td>
                      <td>Coal Kings</td>
                      <td>company@testing.com</td>
                      <td>232-223-322-5</td>
                      <td>$12,000</td>
                      <td>2023/04/13</td>
                      <td>2023/06/22</td>
                    </tr>
                    <tr>
                      <td>12</td>
                      <td>Adhesion contract</td>
                      <td>Customer Support</td>
                      <td>company@testing.com</td>
                      <td>776-665-999-0</td>
                      <td>$30,000</td>
                      <td>2023/08/18</td>
                      <td>2023/09/25</td>
                    </tr>
                    <tr>
                      <td>13</td>
                      <td>Aleatory contract</td>
                      <td>The First Step</td>
                      <td>company@testing.com</td>
                      <td>112-222-887-7</td>
                      <td>$62,000</td>
                      <td>2023/03/10</td>
                      <td>2023/05/16</td>
                    </tr>
                    <tr>
                      <td>14</td>
                      <td>Long Term contract</td>
                      <td>Easy Wings LLC</td>
                      <td>company@testing.com</td>
                      <td>667-887-998-5</td>
                      <td>$55,000</td>
                      <td>2023/04/22</td>
                      <td>2023/10/22</td>
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
    // Load Apex Charts scripts if not already loaded
    if (typeof ApexCharts === 'undefined') {
      loadScript('assets/vendor/apex/apexcharts.min.js', function() {
        // Load order-specific chart scripts after ApexCharts is loaded
        loadScript('assets/vendor/apex/custom/orders/type.js');
        loadScript('assets/vendor/apex/custom/orders/value.js');
      });
    } else {
      // ApexCharts already loaded, just load the chart scripts
      loadScript('assets/vendor/apex/custom/orders/type.js');
      loadScript('assets/vendor/apex/custom/orders/value.js');
    }

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
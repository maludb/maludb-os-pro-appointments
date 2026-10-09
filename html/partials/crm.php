<?php
require_once '../../helpers/auth.php';
check_auth();
?>
<!-- Row starts -->
<div class="row gx-4">
  <div class="col-xl-3 col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">

        <div class="m-0">
          <div class="fw-semibold mb-3">Total Orders</div>
          <div class="position-relative">
            <h2 class="m-0">690</h2>
            <span class="badge bg-primary-subtle text-primary small mb-1">
              <i class="feather-alert-circle me-1 text-danger"></i>3 pending orders
            </span>
            <div class=""><span class="badge bg-primary-subtle text-primary me-1">+28%</span>Compared to
              last week</div>
            <i class="feather-box-seam display-2 text-light position-absolute end-0 top-0 me-2"></i>
          </div>
          <div class="mt-3">
            <div class="small">Last updated on <span class="opacity-50">Jan 10, 6:30:59 AM</span></div>
          </div>
        </div>

      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">

        <div class="m-0">
          <div class="fw-semibold mb-3">Total Sales</div>
          <div class="position-relative">
            <h2 class="m-0">$600</h2>
            <span class="badge bg-primary-subtle text-primary small mb-1">
              <i class="feather-alert-circle me-1 text-danger"></i>4 new sales
            </span>
            <div class=""><span class="badge bg-primary-subtle text-primary me-1">+28%</span>Compared to
              last week</div>
            <i class="feather-bar-chart-2 display-2 text-light position-absolute end-0 top-0 me-2"></i>
          </div>
          <div class="mt-3">
            <div class="small">Last updated on <span class="opacity-50">Jan 12, 8:20:30 AM</span></div>
          </div>
        </div>

      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="m-0">
          <div class="fw-semibold mb-3">Total Profit</div>
          <div class="position-relative">
            <h2 class="m-0">$800</h2>
            <span class="badge bg-primary-subtle text-primary small mb-1">
              <i class="feather-alert-circle me-1 text-danger"></i>5 new orders
            </span>
            <div class=""><span class="badge bg-primary-subtle text-primary me-1">+36%</span>Compared to
              last week</div>
            <i class="feather-clipboard display-2 text-light position-absolute end-0 top-0 me-2"></i>
          </div>
          <div class="mt-3">
            <div class="small">Last updated on <span class="opacity-50">Jan 14, 9:45:35 AM</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6 col-12">
    <div class="card mb-4 card-bg">
      <div class="card-body">
        <div class="m-0 text-white">
          <div class="fw-semibold mb-3">Total Revenue</div>
          <div class="position-relative">
            <h2 class="m-0">$900</h2>
            <span class="badge bg-light-subtle text-danger small mb-1">
              <i class="feather-alert-circle me-1 text-danger"></i>7 new outlets
            </span>
            <div class=""><span class="badge bg-danger me-1">+36%</span>Compared to last week</div>
            <i class="feather-credit-card2 display-2 opacity-25 position-absolute end-0 top-0 me-2"></i>
          </div>
          <div class="mt-3">
            <div class="small">Last updated on <span class="opacity-50">Jan 18, 9:29:59 AM</span></div>
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
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Customers</h5>
      </div>
      <div class="card-body">

        <!-- Row starts -->
        <div class="row gx-4">
          <div class="col-sm-12 col-12">

            <div class="d-flex gap-3">
              <div class="position-relative">
                <h2 class="m-0">200</h2>
                <span class="badge bg-secondary-subtle text-dark small mb-2">
                  <i class="feather-alert-circle me-1 text-danger"></i>3 new customers
                </span>
                <div class=""><span class="badge bg-danger-subtle text-danger me-1">+33%</span>Compared to
                  last week</div>
              </div>
              <div class="position-relative">
                <h2 class="m-0">300</h2>
                <span class="badge bg-secondary-subtle text-dark small mb-2">
                  <i class="feather-alert-circle me-1 text-danger"></i>6 customers online
                </span>
                <div class=""><span class="badge bg-danger-subtle text-danger me-1">+26%</span>Compared to
                  last week</div>
              </div>
              <div class="position-relative">
                <h2 class="m-0">600</h2>
                <span class="badge bg-secondary-subtle text-dark small mb-2">
                  <i class="feather-alert-circle me-1 text-danger"></i>8 active customers
                </span>
                <div class=""><span class="badge bg-danger-subtle text-danger me-1">+22%</span>Compared to
                  last week</div>
              </div>
              <div class="position-relative">
                <h2 class="m-0">400</h2>
                <span class="badge bg-secondary-subtle text-dark small mb-2">
                  <i class="feather-alert-circle me-1 text-danger"></i>3 inactive customers
                </span>
                <div class=""><span class="badge bg-danger me-1">+32%</span>Compared to last week</div>
              </div>
            </div>

          </div>
          <div class="col-sm-12 col-12">
            <div class="graph-body">
              <div id="invoices"></div>
            </div>
          </div>
        </div>
      </div>
      <!-- Row ends -->
    </div>
  </div>
</div>
<!-- Row ends -->

<!-- Row starts -->
<div class="row gx-4">
  <div class="col-xl-4 col-sm-6">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Contracts</h5>
      </div>
      <div class="card-body">
        <div class="scroll350">
          <ul class="user-messages">
            <li>
              <div class="customer bg-danger-subtle text-danger">MK</div>
              <div class="delivery-details">
                <span class="badge bg-danger-subtle text-danger">Expired</span>
                <h5>Marie Kieffer</h5>
                <p>Thanks for choosing Apple product, further if you have any questions please contact sales
                  team.</p>
              </div>
            </li>
            <li>
              <div class="customer bg-danger-subtle text-danger">ES</div>
              <div class="delivery-details">
                <span class="badge bg-danger-subtle text-danger">Live</span>
                <h5>Ewelina Sikora</h5>
                <p>Boost your sales by 50% with the easiest and proven marketing tool for customer enggement
                  &amp; motivation.</p>
              </div>
            </li>
            <li>
              <div class="customer bg-danger-subtle text-danger">TN</div>
              <div class="delivery-details">
                <span class="badge bg-danger-subtle text-danger">Expiring Soon</span>
                <h5>Teboho Ncube</h5>
                <p>Use an exclusive promo code HKYMM50 and get 50% off on your first order in the new year.
                </p>
              </div>
            </li>
            <li>
              <div class="customer bg-danger-subtle text-danger">CJ</div>
              <div class="delivery-details">
                <span class="badge bg-danger-subtle text-danger">Live</span>
                <h5>Carla Jackson</h5>
                <p>Befor inviting the administrator, you must create a role that can be assigned to them.
                </p>
              </div>
            </li>
            <li>
              <div class="customer bg-primary-subtle text-primary">JK</div>
              <div class="delivery-details">
                <span class="badge bg-primary-subtle text-primary">Expiring Soon</span>
                <h5>Julie Kemp</h5>
                <p>Your security subscription has expired. Please renew the subscription.</p>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-4 col-sm-6">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Payments</h5>
      </div>
      <div class="card-body">
        <div class="scroll350">
          <div class="my-4">
            <div class="d-flex align-items-start">
              <img src="assets/images/user3.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Joan Paul</h5>
                <p class="mb-1">3 day ago</p>
                <p class="mb-1 small text-dark">Unpaid invoice ref. #26788</p>
                <span class="badge bg-danger-subtle text-danger">Unpaid</span>
              </div>
            </div>
            <div class="d-flex align-items-start">
              <img src="assets/images/user4.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Vincenzo Lyons</h5>
                <p class="mb-1">3 hours ago</p>
                <p class="mb-1 small text-dark">Paid invoice ref. #23457</p>
                <span class="badge bg-danger-subtle text-danger">Paid</span>
              </div>
            </div>
            <div class="d-flex align-items-start">
              <img src="assets/images/user5.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Clarence Wyatt</h5>
                <p class="mb-1">7 hours ago</p>
                <p class="mb-1 small text-dark">Paid invoice ref. #23459</p>
                <span class="badge bg-danger-subtle text-danger">Partially Paid</span>
              </div>
            </div>
            <div class="d-flex align-items-start">
              <img src="assets/images/user1.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Keenan Vega</h5>
                <p class="mb-1">One week ago</p>
                <p class="mb-1 small text-dark">Paid invoice ref. #34546</p>
                <span class="badge bg-danger-subtle text-danger">Paid</span>
              </div>
            </div>
            <div class="d-flex align-items-start">
              <img src="assets/images/user2.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="m-0">
                <h5 class="mb-1">Noe Carey</h5>
                <p class="mb-1">1 day ago</p>
                <p class="mb-1 small text-dark">Unpaid invoice ref. #23473</p>
                <span class="badge bg-primary-subtle text-primary">Unpaid</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-4 col-sm-12">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Project Activity</h5>
      </div>
      <div class="card-body">
        <div class="scroll350">
          <div class="activity-feed">
            <div class="feed-item">
              <span class="feed-date pb-1" data-bs-toggle="tooltip" data-bs-title="Today 05:32:35">An
                Hour Ago</span>
              <div class="mb-1">
                <a href="#" class="text-primary">Janie Mcdonald</a> - Task marked as complete.
              </div>
              <div class="mb-1">Project Name - <a href="#" class="text-danger">Vibe Templates</a></div>
              <div class="text-dark">Admin Dashboards <i class="feather-arrow-up-right"></i> </div>
            </div>
            <div class="feed-item">
              <span class="feed-date pb-1" data-bs-toggle="tooltip" data-bs-title="Today 05:32:35">An
                Hour Ago</span>
              <div class="mb-1">
                <a href="#" class="text-primary">Janie Mcdonald</a> - Task marked as complete.
              </div>
              <div class="mb-1">Project Name - <a href="#" class="text-danger">Vibe Templates</a></div>
              <div class="text-dark">Admin Dashboards <i class="feather-arrow-up-right"></i> </div>
            </div>
            <div class="feed-item">
              <span class="feed-date pb-1" data-bs-toggle="tooltip" data-bs-title="Today 05:32:35">An
                Hour Ago</span>
              <div class="mb-1">
                <a href="#" class="text-primary">Janie Mcdonald</a> - Task marked as complete.
              </div>
              <div class="mb-1">Project Name - <a href="#" class="text-danger">Vibe Templates</a></div>
              <div class="text-dark">Admin Dashboards <i class="feather-arrow-up-right"></i> </div>
            </div>
            <div class="feed-item">
              <span class="feed-date pb-1" data-bs-toggle="tooltip" data-bs-title="Today 05:32:35">An
                Hour Ago</span>
              <div class="mb-1">
                <a href="#" class="text-primary">Janie Mcdonald</a> - Task marked as complete.
              </div>
              <div class="mb-1">Project Name - <a href="#" class="text-danger">Vibe Templates</a></div>
              <div class="text-dark">Admin Dashboards <i class="feather-arrow-up-right"></i> </div>
            </div>
            <div class="feed-item">
              <span class="feed-date pb-1" data-bs-toggle="tooltip" data-bs-title="Today 05:32:35">An
                Hour Ago</span>
              <div class="mb-1">
                <a href="#" class="text-primary">Janie Mcdonald</a> - Task marked as complete.
              </div>
              <div class="mb-1">Project Name - <a href="#" class="text-danger">Vibe Templates</a></div>
              <div class="text-dark">Admin Dashboards <i class="feather-arrow-up-right"></i> </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-4 col-sm-6">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Deals</h5>
      </div>
      <div class="card-body">
        <div class="graph-body-lg">
          <div id="deals"></div>
        </div>
        <div class="my-3 text-center">
          <h1>3850</h1>
          <h5 class="mb-2">
            Monthly Deals Growth
          </h5>
          <p class="m-0">
            Measure how fast you're growing monthly recurring deals.
          </p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-4 col-sm-6">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Leads</h5>
      </div>
      <div class="card-body">
        <div class="graph-body-lg auto-align-graph">
          <div id="leads"></div>
        </div>
        <div class="my-3 text-center">
          <h1>2500</h1>
          <h5 class="mb-2">
            Monthly Leads Growth
          </h5>
          <p class="m-0">
            Measure how fast you're growing monthly recurring deals.
          </p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-4 col-sm-12">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Tickets</h5>
      </div>
      <div class="card-body">
        <div class="graph-body-lg auto-align-graph">
          <div id="tickets"></div>
        </div>
        <div class="my-3 text-center">
          <h1>800</h1>
          <h5 class="mb-2">
            Monthly Tickets Growth
          </h5>
          <p class="m-0">
            Measure how fast you're growing monthly recurring deals.
          </p>
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
        <h5 class="card-title">Invoices</h5>
      </div>
      <div class="card-body">
        <!-- Row starts -->
        <div class="row gx-4">
          <div class="col-xl-3 col-sm-3">
            <h5 class="mb-4 fw-bold">Overview</h5>

            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>2 Drafts</span>
                <span class="text-primary fw-bold">2%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-primary" role="progressbar" style="width: 2%" aria-valuenow="2"
                  aria-valuemin="2" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>4 Not Sent</span>
                <span class="text-primary fw-bold">40%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-primary" role="progressbar" style="width: 40%"
                  aria-valuenow="40" aria-valuemin="40" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>3 Unpaid</span>
                <span class="text-dark fw-bold">30%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-dark" role="progressbar" style="width: 30%" aria-valuenow="30"
                  aria-valuemin="30" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>2 Overdue</span>
                <span class="text-danger fw-bold">20%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-danger" role="progressbar" style="width: 20%" aria-valuenow="20"
                  aria-valuemin="20" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>5 Paid</span>
                <span class="text-success fw-bold">50%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-success" role="progressbar" style="width: 50%"
                  aria-valuenow="50" aria-valuemin="50" aria-valuemax="100"></div>
              </div>
            </div>

          </div>
          <div class="col-xl-3 col-sm-3">
            <h5 class="mb-4 fw-bold">Estimates</h5>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>4 Drafts</span>
                <span class="text-primary fw-bold">4%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-primary" role="progressbar" style="width: 4%" aria-valuenow="4"
                  aria-valuemin="4" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>2 Not Sent</span>
                <span class="text-primary fw-bold">20%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-primary" role="progressbar" style="width: 20%"
                  aria-valuenow="20" aria-valuemin="20" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>4 Unpaid</span>
                <span class="text-dark fw-bold">40%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-dark" role="progressbar" style="width: 40%" aria-valuenow="40"
                  aria-valuemin="40" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>4 Overdue</span>
                <span class="text-danger fw-bold">40%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-danger" role="progressbar" style="width: 40%" aria-valuenow="40"
                  aria-valuemin="40" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>2 Paid</span>
                <span class="text-success fw-bold">20%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-success" role="progressbar" style="width: 20%"
                  aria-valuemin="20" aria-valuemax="100"></div>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-sm-3">
            <h5 class="mb-4 fw-bold">Proposals</h5>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>0 Drafts</span>
                <span class="text-primary fw-bold">0%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-primary" role="progressbar" style="width: 0%" aria-valuenow="0"
                  aria-valuemin="0" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>3 Not Sent</span>
                <span class="text-primary fw-bold">30%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-primary" role="progressbar" style="width: 30%"
                  aria-valuenow="30" aria-valuemin="30" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>5 Unpaid</span>
                <span class="text-dark fw-bold">50%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-dark" role="progressbar" style="width: 50%" aria-valuenow="50"
                  aria-valuemin="50" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>3 Overdue</span>
                <span class="text-danger fw-bold">30%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-danger" role="progressbar" style="width: 30%" aria-valuenow="30"
                  aria-valuemin="30" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>7 Paid</span>
                <span class="text-success fw-bold">70%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-success" role="progressbar" style="width: 70%"
                  aria-valuenow="70" aria-valuemin="70" aria-valuemax="100"></div>
              </div>
            </div>
          </div>
          <div class="col-xl-3 col-sm-3">
            <h5 class="mb-4 fw-bold">Leads</h5>

            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>3 Drafts</span>
                <span class="text-primary fw-bold">3%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-primary" role="progressbar" style="width: 3%" aria-valuenow="3"
                  aria-valuemin="3" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>5 Not Sent</span>
                <span class="text-primary fw-bold">50%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-primary" role="progressbar" style="width: 50%"
                  aria-valuenow="50" aria-valuemin="50" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>7 Unpaid</span>
                <span class="text-dark fw-bold">70%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-dark" role="progressbar" style="width: 70%" aria-valuenow="70"
                  aria-valuemin="70" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>2 Overdue</span>
                <span class="text-danger fw-bold">20%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-danger" role="progressbar" style="width: 20%" aria-valuenow="20"
                  aria-valuemin="20" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="mb-4">
              <div class="d-flex justify-content-between mb-2">
                <span>3 Paid</span>
                <span class="text-success fw-bold">30%</span>
              </div>
              <div class="progress small">
                <div class="progress-bar bg-success" role="progressbar" style="width: 30%"
                  aria-valuenow="30" aria-valuemin="30" aria-valuemax="100"></div>
              </div>
            </div>

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
      var apexScript = document.createElement('script');
      apexScript.src = 'assets/vendor/apex/apexcharts.min.js';
      apexScript.onload = function() {
        // Load CRM specific chart scripts after ApexCharts is loaded
        loadScript('assets/vendor/apex/custom/crm/invoices.js');
        loadScript('assets/vendor/apex/custom/crm/deals.js');
        loadScript('assets/vendor/apex/custom/crm/tickets.js');
        loadScript('assets/vendor/apex/custom/crm/leads.js');
      };
      document.body.appendChild(apexScript);
    } else {
      // ApexCharts already loaded, just load the chart scripts
      loadScript('assets/vendor/apex/custom/crm/invoices.js');
      loadScript('assets/vendor/apex/custom/crm/deals.js');
      loadScript('assets/vendor/apex/custom/crm/tickets.js');
      loadScript('assets/vendor/apex/custom/crm/leads.js');
    }
  }
});

function loadScript(src) {
  var script = document.createElement('script');
  script.src = src;
  document.body.appendChild(script);
}
</script>

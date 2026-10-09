<?php
require_once '../../helpers/auth.php';
check_auth();
?>
<!-- Stats BG starts -->
<div class="mx-n4 mb-4 p-4 bg-primary">

  <div class="d-flex align-items-center flex-row">
    <img src="assets/images/user.png" class="img-5x rounded-circle" alt="Seven Admin Template" />
    <div class="ms-3 text-white">
      <h5 class="mb-1">Clarence Wyatt</h5>
      <h6 class="m-0 fw-light">UX Designer</h6>
    </div>
    <div class="ms-auto">
      <button class="btn btn-danger">Follow</button>
      <button class="btn btn-success ms-1">Message</button>
    </div>
  </div>

</div>
<!-- Stats BG ends -->

<!-- Row starts -->
<div class="row gx-4">
  <div class="col-xl-3 col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex mb-2">
          <div class="icon-box md bg-primary rounded-5 me-3">
            <i class="feather-box fs-4 text-white"></i>
          </div>
          <div class="d-flex flex-column">
            <h2 class="m-0 lh-1">8000</h2>
            <p class="m-0 opacity-50">Likes</p>
          </div>
        </div>
        <div class="m-0">
          <div class="progress thin mb-2">
            <div class="progress-bar bg-primary" role="progressbar" style="width: 75%" aria-valuenow="75"
              aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <p class="m-0 small fw-light opacity-75">Higher than last week.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex mb-2">
          <div class="icon-box md bg-primary rounded-5 me-3">
            <i class="feather-box fs-4 text-white"></i>
          </div>
          <div class="d-flex flex-column">
            <h2 class="m-0 lh-1">6000</h2>
            <p class="m-0 opacity-50">Shares</p>
          </div>
        </div>
        <div class="m-0">
          <div class="progress thin mb-2">
            <div class="progress-bar bg-primary" role="progressbar" style="width: 75%" aria-valuenow="75"
              aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <p class="m-0 small fw-light opacity-75">Higher than last week.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6 col-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex mb-2">
          <div class="icon-box md bg-primary rounded-5 me-3">
            <i class="feather-box fs-4 text-white"></i>
          </div>
          <div class="d-flex flex-column">
            <h2 class="m-0 lh-1">3000</h2>
            <p class="m-0 opacity-50">Tweets</p>
          </div>
        </div>
        <div class="m-0">
          <div class="progress thin mb-2">
            <div class="progress-bar bg-primary" role="progressbar" style="width: 75%" aria-valuenow="75"
              aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <p class="m-0 small fw-light opacity-75">Higher than last week.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6 col-12">
    <div class="card mb-4 card-bg">
      <div class="card-body text-white">
        <div class="d-flex mb-2">
          <div class="icon-box md bg-white rounded-5 me-3">
            <i class="feather-box fs-4 text-black"></i>
          </div>
          <div class="d-flex flex-column">
            <h2 class="m-0 lh-1">2000</h2>
            <p class="m-0 opacity-50">Blog</p>
          </div>
        </div>
        <div class="m-0">
          <div class="progress thin mb-2">
            <div class="progress-bar bg-white" role="progressbar" style="width: 75%" aria-valuenow="75"
              aria-valuemin="0" aria-valuemax="100"></div>
          </div>
          <p class="m-0 small fw-light opacity-75">Higher than last week.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Row ends -->

<!-- Row starts -->
<div class="row gx-4">
  <div class="col-xl-4 col-sm-6">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Projects</h5>
      </div>
      <div class="card-body">
        <div class="scroll300">
          <ul class="my-4 p-0">
            <li class="activity-list d-flex">
              <div class="activity-time pt-2 pe-3 me-3">
                <p class="date m-0">10:30 am</p>
                <span class="badge bg-primary">75%</span>
              </div>
              <div class="d-flex flex-column py-2">
                <h5 class="mb-1">Vibe Templates</h5>
                <p class="m-0 small">by Paula York</p>
              </div>
            </li>
            <li class="activity-list d-flex">
              <div class="activity-time pt-2 pe-3 me-3">
                <p class="date m-0">11:30 am</p>
                <span class="badge bg-primary">50%</span>
              </div>
              <div class="d-flex flex-column py-2">
                <h5 class="mb-1">Mobile App</h5>
                <p class="m-0 small">by PDarrell Dixon</p>
              </div>
            </li>
            <li class="activity-list d-flex">
              <div class="activity-time pt-2 pe-3 me-3">
                <p class="date m-0">12:50 pm</p>
                <span class="badge bg-primary">90%</span>
              </div>
              <div class="d-flex flex-column py-2">
                <h5 class="mb-1">UI Kit</h5>
                <p class="m-0 small">by Olen Hill</p>
              </div>
            </li>
            <li class="activity-list d-flex">
              <div class="activity-time pt-2 pe-3 me-3">
                <p class="date m-0">02:30 pm</p>
                <span class="badge bg-primary">50%</span>
              </div>
              <div class="d-flex flex-column py-2">
                <h5 class="mb-1">Invoice Design</h5>
                <p class="m-0 small">by Callie Hayes</p>
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
        <h5 class="card-title">Activity</h5>
      </div>
      <div class="card-body">
        <div class="scroll300">
          <div class="my-4">
            <div class="d-flex">
              <img src="assets/images/user3.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Joan Paul</h5>
                <p class="mb-1">3 day ago</p>
                <p class="mb-1 small">Paid invoice ref. #26788</p>
                <span class="badge bg-primary">Sent</span>
              </div>
            </div>
            <div class="d-flex">
              <img src="assets/images/user4.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Vincenzo Lyons</h5>
                <p class="mb-1">3 hours ago</p>
                <p class="mb-1 small">Sent invoice ref. #23457</p>
                <span class="badge bg-primary">Sent</span>
              </div>
            </div>
            <div class="d-flex">
              <img src="assets/images/user1.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Keenan Vega</h5>
                <p class="mb-1">One week ago</p>
                <p class="mb-1 small">Paid invoice ref. #34546</p>
                <span class="badge bg-primary">Invoice</span>
              </div>
            </div>
            <div class="d-flex">
              <img src="assets/images/user5.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Clarence Wyatt</h5>
                <p class="mb-1">7 hours ago</p>
                <p class="mb-1 small">Paid invoice ref. #23459</p>
                <span class="badge bg-primary">Payments</span>
              </div>
            </div>
            <div class="d-flex">
              <img src="assets/images/user3.png" class="img-4x me-3 rounded-circle"
                alt="Seven Admin Template" />
              <div class="mb-4">
                <h5 class="mb-1">Noe Carey</h5>
                <p class="mb-1">1 day ago</p>
                <p class="mb-1 small">Paid invoice ref. #23473</p>
                <span class="badge bg-primary">Paid</span>
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
        <h5 class="card-title">Earnings</h5>
      </div>
      <div class="card-body">
        <div id="income" class="auto-align-graph"></div>
        <div class="text-center">
          <h2>
            $75K
            <i class="feather-arrow-up-right text-primary ms-2"></i>
          </h2>
          <p class="text-truncate">18% higher than last month.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Row ends -->

<!-- Row starts -->
<div class="row gx-4">
  <div class="col-xl-8 col-sm-12">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Blog</h5>
      </div>
      <div class="card-body">

        <div class="bg-white p-3 mb-3 rounded-3">
          <div class="d-flex mb-3">
            <img src="assets/images/user3.png" class="rounded-circle me-3 img-4x" alt="Admin Dashboards">
            <div class="flex-grow-1">
              <p class="float-end badge bg-dark">7 hrs ago</p>
              <h5 class="mb-1">Matt Hooper</h5>
              <p class="mb-3 small text-dark">Today 2:45pm</p>
              <p>
                A dashboard, in website administration, is typically the index page of the control panel for
                a website's content management system. Vibe Templates Admin Dashboards are fully
                responsive built on Bootstrap 5 framework.
              </p>
              <div class="row gx-4">
                <div class="col-12">
                  <h5 class="fw-bold">Best Admin Dashboards</h5>
                </div>
                <div class="col-12">
                  <img src="assets/images/products/banner.jpg" alt="Vibe Templates"
                    class="img-fluid rounded">
                </div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-outline-primary mt-2">
                  <i class="feather-heart"></i> Likes - 300
                </button>
                <button class="btn btn-outline-primary mt-2">
                  <i class="feather-message-square"></i> Comments - 500
                </button>
                <button class="btn btn-outline-primary mt-2">
                  <i class="feather-share-2"></i> Shares - 200
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-white p-3 mb-3 rounded-3">
          <div class="d-flex mb-3">
            <img src="assets/images/user5.png" class="rounded-circle me-3 img-4x" alt="Admin Dashboards">
            <div class="flex-grow-1">
              <p class="float-end badge bg-dark">15 hrs ago</p>
              <h5 class="mb-1">Vicki Ayala</h5>
              <p class="mb-3 small text-dark">Today 3:30pm</p>
              <p>
                A dashboard, in website administration, is typically the index page of the control panel for
                a website's content management system. Vibe Templates Admin Dashboards are fully
                responsive built on Bootstrap 5 framework.
              </p>
              <div class="row gx-4">
                <div class="col-12">
                  <h5 class="fw-bold">Best Admin Dashboards</h5>
                </div>
                <div class="col-4">
                  <img src="assets/images/products/banner2.jpg" alt="Vibe Templates"
                    class="img-fluid rounded">
                </div>
                <div class="col-4">
                  <img src="assets/images/products/banner.jpg" alt="Vibe Templates"
                    class="img-fluid rounded">
                </div>
                <div class="col-4">
                  <img src="assets/images/products/banner3.jpg" alt="Vibe Templates"
                    class="img-fluid rounded">
                </div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-outline-primary mt-2">
                  <i class="feather-heart"></i> Likes - 300
                </button>
                <button class="btn btn-outline-primary mt-2">
                  <i class="feather-message-square"></i> Comments - 500
                </button>
                <button class="btn btn-outline-primary mt-2">
                  <i class="feather-share-2"></i> Shares - 200
                </button>
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
        <h5 class="card-title">Skills</h5>
      </div>
      <div class="card-body">
        <div class="d-inline-flex gap-2 flex-wrap">
          <span class="badge bg-primary">HTML</span>
          <span class="badge bg-primary">Javascript</span>
          <span class="badge bg-primary">React</span>
          <span class="badge bg-primary">Scss</span>
          <span class="badge bg-primary">Angular</span>
          <span class="badge bg-primary">CSS</span>
        </div>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title">Bookmarks</h5>
      </div>
      <div class="card-body">
        <ul class="list-group">
          <li class="list-group-item">
            <a href="https://www.bootstrap.gallery/" class="text-info">
              <i class="feather-zap"></i> Best Bootstrap Dashboards
            </a>
          </li>
          <li class="list-group-item">
            <a href="https://www.bootstrap.gallery/" class="text-info">
              <i class="feather-zap"></i> Best Bootstrap Themes
            </a>
          </li>
          <li class="list-group-item">
            <a href="https://www.bootstrap.gallery/" class="text-info">
              <i class="feather-zap"></i> Quality Bootstrap Themes
            </a>
          </li>
          <li class="list-group-item">
            <a href="https://www.bootstrap.gallery/" class="text-info">
              <i class="feather-zap"></i> Best Bootstrap 5 Admin Templates
            </a>
          </li>
          <li class="list-group-item">
            <a href="https://www.bootstrap.gallery/" class="text-info">
              <i class="feather-zap"></i> Premium Bootstrap Dashboards
            </a>
          </li>
          <li class="list-group-item">
            <a href="https://www.bootstrap.gallery/" class="text-info">
              <i class="feather-zap"></i> Quality Bootstrap Dashboards
            </a>
          </li>
          <li class="list-group-item">
            <a href="https://www.bootstrap.gallery/" class="text-info">
              <i class="feather-zap"></i> Free Bootstrap Dashboards
            </a>
          </li>
          <li class="list-group-item">
            <a href="https://www.bootstrap.gallery/" class="text-info">
              <i class="feather-zap"></i> Free Admin Dashboards
            </a>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
<!-- Row ends -->

<script>
document.addEventListener('htmx:afterSwap', function(evt) {
  if(evt.detail.target.id === 'page-content') {
    // Load and initialize the income chart for profile page
    if (typeof ApexCharts !== 'undefined') {
      // Income chart initialization (from assets/vendor/apex/custom/profile/income.js)
      var options = {
        series: [
          {
            name: "Income",
            data: [30, 40, 35, 50, 49, 60, 70, 91, 125]
          }
        ],
        chart: {
          height: 300,
          type: 'line',
          toolbar: {
            show: false
          }
        },
        stroke: {
          width: 5,
          curve: 'smooth'
        },
        xaxis: {
          categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep']
        },
        yaxis: {
          show: false
        },
        colors: ['#0d6efd'],
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'light',
            type: "vertical",
            shadeIntensity: 0.5,
            gradientToColors: ['#0d6efd'],
            inverseColors: false,
            opacityFrom: 1,
            opacityTo: 0.3,
            stops: [0, 100]
          }
        }
      };

      var chart = new ApexCharts(document.querySelector("#income"), options);
      chart.render();
    }
  }
});
</script>

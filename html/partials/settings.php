<?php
require_once '../../helpers/auth.php';
require_once '../../helpers/csrf.php';
require_once '../../config/database.php';

check_auth();
$user = get_user();
$csrf_token = generate_csrf_token();

// Handle form submission
$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid security token. Please try again.';
    } else {
        try {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            // Validate inputs
            if (empty($firstName) || empty($lastName) || empty($email)) {
                $errorMessage = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = 'Please enter a valid email address.';
            } else {
                // Check if email is already used by another user
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
                $stmt->execute([$email, $user['id']]);

                if ($stmt->fetch()) {
                    $errorMessage = 'This email is already in use.';
                } else {
                    // Update user
                    $stmt = $db->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE id = ?');

                    if ($stmt->execute([$firstName, $lastName, $email, $user['id']])) {
                        $successMessage = 'Profile updated successfully!';
                        // Update user session
                        $_SESSION['user'] = [
                            'id' => $user['id'],
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'email' => $email,
                            'username' => $user['username'],
                            'role' => $user['role']
                        ];
                        $user = $_SESSION['user'];
                    } else {
                        $errorMessage = 'Failed to update profile. Please try again.';
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            $errorMessage = 'An error occurred. Please try again.';
        }
    }
}
?>
<!-- Row starts -->
<div class="row gx-4">
  <div class="col-xl-12">

    <div class="card">
      <div class="card-body">

        <!-- Custom tabs start -->
        <div class="custom-tabs-container2">

          <!-- Nav tabs start -->
          <ul class="nav nav-tabs" id="customTab2" role="tablist">
            <li class="nav-item" role="presentation">
              <a class="nav-link active" id="tab-oneA" data-bs-toggle="tab" href="#oneA" role="tab"
                aria-controls="oneA" aria-selected="true">General</a>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link" id="tab-twoA" data-bs-toggle="tab" href="#twoA" role="tab"
                aria-controls="twoA" aria-selected="false">Settings</a>
            </li>
          </ul>
          <!-- Nav tabs end -->

          <!-- Tab content start -->
          <div class="tab-content">
            <div class="tab-pane fade show active" id="oneA" role="tabpanel">

              <!-- Messages -->
              <div id="settings-messages">
                <?php if ($successMessage): ?>
                  <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    <i class="feather-check-circle-fill me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                  <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <i class="feather-alert-triangle-fill me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Row starts -->
              <form method="POST" id="settings-form">
                <div class="row gx-4 justify-content-between">
                  <div class="col-sm-8 col-12">
                    <div class="card border mb-4">
                      <div class="card-header">
                        <h5 class="card-title">Personal Details</h5>
                      </div>
                      <div class="card-body">

                        <?php echo csrf_field(); ?>

                        <!-- Row starts -->
                        <div class="row gx-4">
                          <div class="col-sm col-12">

                            <!-- Form field start -->
                            <div class="mb-4">
                              <label for="firstName" class="form-label">First Name</label>
                              <input type="text"
                                     class="form-control"
                                     id="firstName"
                                     name="first_name"
                                     placeholder="First Name"
                                     value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                     required />
                            </div>
                            <!-- Form field end -->

                            <!-- Form field start -->
                            <div class="mb-4">
                              <label for="lastName" class="form-label">Last Name</label>
                              <input type="text"
                                     class="form-control"
                                     id="lastName"
                                     name="last_name"
                                     placeholder="Last Name"
                                     value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                     required />
                            </div>
                            <!-- Form field end -->

                          </div>
                          <div class="col-sm col-12">

                            <!-- Form field start -->
                            <div class="mb-4">
                              <label for="emailId" class="form-label">Email</label>
                              <input type="email"
                                     class="form-control"
                                     id="emailId"
                                     name="email"
                                     placeholder="Email Address"
                                     value="<?php echo htmlspecialchars($user['email']); ?>"
                                     required />
                            </div>
                            <!-- Form field end -->

                            <!-- Form field start -->
                            <div class="mb-4">
                              <label for="username" class="form-label">Username</label>
                              <input type="text"
                                     class="form-control"
                                     id="username"
                                     placeholder="Username"
                                     value="<?php echo htmlspecialchars($user['username']); ?>"
                                     disabled />
                              <small class="text-muted">Username cannot be changed</small>
                            </div>
                            <!-- Form field end -->

                          </div>
                        </div>
                        <!-- Row ends -->

                      </div>
                    </div>
                  </div>
                  <div class="col-sm-4 col-12">
                    <div class="card border mb-4">
                      <div class="card-header">
                        <h5 class="card-title">Account Info</h5>
                      </div>
                      <div class="card-body">
                        <div class="row gx-4">
                          <div class="col-12">

                            <!-- Form field start -->
                            <div class="mb-4">
                              <label class="form-label">Role</label>
                              <p class="form-control-plaintext">
                                <span class="badge bg-primary"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                              </p>
                            </div>
                            <!-- Form field end -->

                            <!-- Form field start -->
                            <div class="mb-4">
                              <label class="form-label">Member Since</label>
                              <p class="form-control-plaintext">
                                <?php echo date('F j, Y', strtotime($user['created_at'] ?? now())); ?>
                              </p>
                            </div>
                            <!-- Form field end -->

                            <!-- Form field start -->
                            <div>
                              <a href="#"
                                 hx-get="/partials/reset-password.php"
                                 hx-target="#page-content"
                                 hx-swap="innerHTML"
                                 class="btn btn-sm btn-outline-primary w-100">
                                <i class="feather-shield-lock me-2"></i>Change Password
                              </a>
                            </div>
                            <!-- Form field end -->

                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Row ends -->

                <!-- Buttons start -->
                <div class="d-flex gap-2 justify-content-end">
                  <button type="reset" class="btn btn-outline-dark">
                    <i class="feather-rotate-cw me-1"></i>Reset
                  </button>
                  <button type="submit" class="btn btn-primary">
                    <i class="feather-check-circle me-1"></i>Update
                  </button>
                </div>
                <!-- Buttons end -->
              </form>

            </div>
            <div class="tab-pane fade" id="twoA" role="tabpanel">

              <!-- Row starts -->
              <div class="row gx-4">
                <div class="col-sm-6 col-12">

                  <!-- List group start -->
                  <ul class="list-group mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      Show desktop notifications
                      <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="switchOne" />
                      </div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      Show email notifications
                      <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="switchTwo"
                          checked />
                      </div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      Show chat notifications
                      <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="switchThree" />
                      </div>
                    </li>
                  </ul>
                  <!-- List group end -->

                </div>
                <div class="col-sm-6 col-12">

                  <!-- List group start -->
                  <ul class="list-group mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      Show purchase history
                      <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="switchFour" />
                      </div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      Show orders
                      <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="switchFive" />
                      </div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      Show alerts
                      <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="switchSix" />
                      </div>
                    </li>
                  </ul>
                  <!-- List group end -->

                </div>
              </div>
              <!-- Row ends -->

            </div>
          </div>
          <!-- Tab content end -->

        </div>
        <!-- Custom tabs end -->

      </div>
    </div>

  </div>
</div>
<!-- Row ends -->

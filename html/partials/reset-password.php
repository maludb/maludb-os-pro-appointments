<?php
require_once '../../helpers/auth.php';
require_once '../../helpers/csrf.php';
require_once '../../helpers/validation.php';
require_once '../../config/database.php';
require_once '../../models/User.php';

check_auth();
$user = get_user();
$csrf_token = generate_csrf_token();

// Handle form submission
$errorMessage = null;
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errorMessage = 'Invalid security token. Please refresh and try again.';
    }
    // Validate inputs
    elseif (empty($_POST['current_password']) || empty($_POST['new_password']) || empty($_POST['confirm_password'])) {
        $errorMessage = 'Please fill in all password fields.';
    }
    // Check if new passwords match
    elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
        $errorMessage = 'New passwords do not match.';
    }
    // Validate password strength
    else {
        $validation = validate_password($_POST['new_password']);
        if (!$validation['valid']) {
            $errorMessage = $validation['message'];
        } else {
            // Verify current password
            $userModel = new User();
            $userWithPassword = $userModel->findByEmail($user['email']);

            if (!$userWithPassword || !password_verify($_POST['current_password'], $userWithPassword['password'])) {
                $errorMessage = 'Current password is incorrect.';
            } else {
                // Update password
                $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');

                if ($stmt->execute([$hashedPassword, $user['id']])) {
                    $successMessage = 'Password reset successfully!';
                    // If HTMX request, output just the message and return
                    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
                        echo '<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">';
                        echo '<i class="feather-check-circle-fill me-2"></i>' . htmlspecialchars($successMessage);
                        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                        echo '</div>';
                        exit;
                    } else {
                        // For regular requests, redirect back to settings
                        header('Location: /app.php?page=settings');
                        exit;
                    }
                } else {
                    $errorMessage = 'Failed to update password. Please try again.';
                }
            }
        }
    }

    // If HTMX request with error, output just the error message
    if (isset($_SERVER['HTTP_HX_REQUEST']) && $errorMessage) {
        echo '<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">';
        echo '<i class="feather-alert-triangle-fill me-2"></i>' . htmlspecialchars($errorMessage);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        exit;
    }
}
?>
<!-- Auth wrapper starts -->
<div class="auth-wrapper">

  <!-- Form starts -->
  <form hx-post="/partials/reset-password.php" hx-target="#reset-password-messages" hx-swap="innerHTML">

    <!-- Authbox starts -->
    <div class="auth-box">

      <!-- Logo starts -->
      <!-- Logo ends -->

      <!-- Page Header -->
      <div class="m-4" id="reset-password-header">
        <div class="card bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
          <div class="card-body">
            <h4 class="mb-1 text-dark">Reset Password</h4>
            <p class="mb-0 text-dark">Update your account password</p>
          </div>
        </div>
      </div>

      <div id="reset-password-messages">
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

      <?php echo csrf_field(); ?>

      <div class="m-4">
        <label class="form-label" for="currentPwd">Current password <span class="text-danger">*</span></label>
        <div class="input-group ">
          <input type="password" id="currentPwd" name="current_password" class="form-control" placeholder="Enter current password" required>
          <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPwd')">
            <i class="feather-eye"></i>
          </button>
        </div>
      </div>

      <div class="m-4">
        <label class="form-label" for="newPwd">New password <span class="text-danger">*</span></label>
        <div class="input-group ">
          <input type="password" id="newPwd" name="new_password" class="form-control" placeholder="Enter new password" required>
          <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPwd')">
            <i class="feather-eye"></i>
          </button>
        </div>
        <div class="form-text">
          Your password must be 8-20 characters long and contain uppercase, lowercase, and numbers.
        </div>
      </div>

      <div class="m-4">
        <label class="form-label" for="confNewPwd">Confirm new password <span class="text-danger">*</span></label>
        <div class="input-group ">
          <input type="password" id="confNewPwd" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
          <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confNewPwd')">
            <i class="feather-eye-slash"></i>
          </button>
        </div>
      </div>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </div>

    </div>
    <!-- Authbox ends -->

  </form>
  <!-- Form ends -->

</div>
<!-- Auth wrapper ends -->

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

document.addEventListener('htmx:afterSwap', function(evt) {
  if(evt.detail.target.id === 'page-content') {
    // Add login-bg class to body for reset password page
    document.body.classList.add('login-bg');

    // Clean up function to remove the class when navigating away
    function cleanup() {
      document.body.classList.remove('login-bg');
      document.removeEventListener('htmx:beforeSwap', cleanup);
    }

    // Listen for the next page swap to clean up
    document.addEventListener('htmx:beforeSwap', cleanup);
  }
});
</script>

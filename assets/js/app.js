document.addEventListener('DOMContentLoaded', function() {
    const roleOptions = document.querySelectorAll('.role-option');
    const selectedRoleInput = document.getElementById('selectedRole');
    const userRoleName = document.getElementById('userRoleName');
    const userRoleDesc = document.getElementById('userRoleDesc');
    const loginBtn = document.getElementById('loginBtn');

    // Set default faculty information
    updateUserInfo('faculty');

    // Add click event to role options
    roleOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            roleOptions.forEach(opt => opt.classList.remove('active'));
            
            // Add active class to clicked option
            this.classList.add('active');
            
            // Get selected role
            const role = this.getAttribute('data-role');
            
            // Update hidden input
            selectedRoleInput.value = role;
            
            // Update user info based on role
            updateUserInfo(role);
        });
    });

    // Login button click handler
    loginBtn.addEventListener('click', function() {
        const role = selectedRoleInput.value;
        
        // Show loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting...';
        this.disabled = true;
        
        // Redirect after a short delay to show loading
        setTimeout(() => {
            if (role === 'faculty') {
                window.location.href = 'faculty/login.php';
            } else {
                window.location.href = 'student/login.php';
            }
        }, 1500);
    });

    function updateUserInfo(role) {
        if (role === 'faculty') {
            userRoleName.textContent = 'Faculty Dashboard';
            userRoleDesc.textContent = 'Access to course management and analytics';
            document.querySelector('.avatar').style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
        } else {
            userRoleName.textContent = 'Student Dashboard';
            userRoleDesc.textContent = 'Access to courses and learning materials';
            document.querySelector('.avatar').style.background = 'linear-gradient(135deg, #28a745, #20c997)';
        }
    }
});
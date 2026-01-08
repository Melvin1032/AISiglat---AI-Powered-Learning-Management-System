<div class="header">
    <h1 class="page-title">Learning Dashboard</h1>
    <div class="user-info">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?></div>
        <span><?php echo $_SESSION['username']; ?></span>
    </div>
</div>
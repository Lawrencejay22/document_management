<?php
session_start();
require_once '../PHP/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login%20form/login.php");
    exit();
}

// Check if user is Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    // Not an admin, redirect back to dashboard
    header("Location: document-track.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle actions (Delete user, Delete document, Change role)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete_user' && isset($_POST['target_id'])) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role != 'Admin'"); // Prevent deleting other admins
                $stmt->execute(['id' => $_POST['target_id']]);
                $success = "User deleted successfully.";
            } catch (PDOException $e) {
                $error = "Failed to delete user: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'change_role' && isset($_POST['target_id']) && isset($_POST['new_role'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
                $stmt->execute(['role' => $_POST['new_role'], 'id' => $_POST['target_id']]);
                $success = "User role updated successfully.";
            } catch (PDOException $e) {
                $error = "Failed to update role: " . $e->getMessage();
            }
        } elseif ($_POST['action'] === 'delete_document' && isset($_POST['target_id'])) {
            try {
                // Fetch file path to delete the physical file
                $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = :id");
                $stmt->execute(['id' => $_POST['target_id']]);
                $doc = $stmt->fetch();
                
                if ($doc) {
                    $filePath = '../uploads/' . $doc['file_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $stmt = $pdo->prepare("DELETE FROM documents WHERE id = :id");
                    $stmt->execute(['id' => $_POST['target_id']]);
                    $success = "Document deleted successfully.";
                }
            } catch (PDOException $e) {
                $error = "Failed to delete document: " . $e->getMessage();
            }
        }
    }
}

// Fetch stats
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $adminUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
    $publicUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Public'")->fetchColumn();
    $deptUsers = $totalUsers - $adminUsers - $publicUsers;
    
    $totalDocs = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
    $publicDocs = $pdo->query("SELECT COUNT(*) FROM documents WHERE category = 'Public'")->fetchColumn();
    $privateDocs = $pdo->query("SELECT COUNT(*) FROM documents WHERE category = 'Private'")->fetchColumn();
    $deptDocs = $pdo->query("SELECT COUNT(d.id) FROM documents d JOIN users u ON d.uploaded_by = u.id WHERE u.role NOT IN ('Admin', 'Public')")->fetchColumn();
    
    $totalMessages = $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
} catch (PDOException $e) {
    $totalUsers = $adminUsers = $publicUsers = $deptUsers = 0;
    $totalDocs = $publicDocs = $privateDocs = $deptDocs = 0;
    $totalMessages = 0;
}

// Fetch all users
try {
    $users = $pdo->query("SELECT id, username, email, role, created_at, profile_picture FROM users ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Fetch all documents
try {
    $documents = $pdo->query("SELECT d.id, d.title, d.category, d.upload_date, d.file_size, u.username as uploader FROM documents d JOIN users u ON d.uploaded_by = u.id ORDER BY d.upload_date DESC")->fetchAll();
} catch (PDOException $e) {
    $documents = [];
}

// Function to convert size string (e.g., "2.4 MB") to bytes
function parseSizeToBytes($sizeStr) {
    $sizeStr = trim($sizeStr);
    if (!$sizeStr) return 0;
    $unit = strtoupper(preg_replace('/[^A-Z]/', '', $sizeStr));
    $value = (float) preg_replace('/[^0-9.]/', '', $sizeStr);
    switch ($unit) {
        case 'KB': return $value * 1024;
        case 'MB': return $value * 1048576;
        case 'GB': return $value * 1073741824;
        default: return $value;
    }
}

// Calculate Data for Charts
$totalUsedBytes = 0;
$catCounts = ['Public' => 0, 'Private' => 0, 'Internal' => 0];
$userCounts = [];

foreach ($documents as $doc) {
    $bytes = parseSizeToBytes($doc['file_size']);
    $totalUsedBytes += $bytes;
    
    // Categorize
    if ($doc['category'] === 'Public') $catCounts['Public']++;
    elseif ($doc['category'] === 'Private') $catCounts['Private']++;
    else $catCounts['Internal']++;
    
    // User counts
    $uploader = $doc['uploader'];
    if (!isset($userCounts[$uploader])) {
        $userCounts[$uploader] = 0;
    }
    $userCounts[$uploader]++;
}

$totalDiskCapacity = 500 * 1073741824; // 500 GB Fake Capacity
$freeSpaceBytes = $totalDiskCapacity - $totalUsedBytes;

function formatBytesToGB($bytes) {
    return number_format($bytes / 1073741824, 2);
}
function formatBytesToMB($bytes) {
    return number_format($bytes / 1048576, 2);
}

$usedGB = formatBytesToGB($totalUsedBytes);
$freeGB = formatBytesToGB($freeSpaceBytes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="document-track.css?v=<?= time() ?>">
    <title>Admin Dashboard - DocHub</title>
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            display: flex;
            height: 90px;
            background: #ffffff;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .stat-icon {
            width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #ffffff;
        }
        .stat-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 15px;
            text-align: center;
        }
        .stat-info p {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .stat-info h3 {
            font-size: 26px;
            color: #333;
            font-weight: 300;
        }
        /* Colors from the image */
        .bg-cyan { background-color: #00c0ef; }
        .bg-light-blue { background-color: #3c8dbc; }
        .bg-green { background-color: #00a65a; }
        .bg-red { background-color: #f56954; }
        .bg-purple { background-color: #605ca8; }
        .bg-pink { background-color: #e83e8c; }
        .bg-orange { background-color: #f39c12; }
        .bg-dark-orange { background-color: #e67e22; }
        
        .admin-section {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .admin-section h2 {
            margin-bottom: 20px;
            font-size: 18px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        .admin-table th, .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .admin-table th {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .admin-table td {
            color: #e2e8f0;
            font-size: 14px;
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.4);
        }
        
        .role-select {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 5px 10px;
            border-radius: 4px;
            outline: none;
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .chart-header {
            font-size: 16px;
            color: #fff;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        .chart-body {
            flex: 1;
            position: relative;
            min-height: 200px;
        }
        .chart-legend {
            margin-top: 15px;
            font-size: 13px;
        }
        .legend-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            color: #e2e8f0;
        }
        .legend-color {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="background-circles">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
    </div>
    
    <div class="app-container">
        <!-- ============= sidebar ============= -->
        <aside class="sidebar">
            <div class="logo-div">
                <i class="fa-solid fa-folder-open logo-icon"></i>
                <h2>Docker-Up</h2>
            </div>

            <div class="sidebar-section">
                <p class="section-title">MAIN MENU</p>
                <nav>
                    <ul>
                        <li>
                            <a href="document-track.php"><i class="fa-solid fa-table-columns" style="color: var(--theme-color); margin-right: 8px;"></i> Dashboard</a>
                        </li>
                        <li class="active">
                            <a href="admin_dashboard.php"><i class="fa-solid fa-shield-halved" style="color: #ef4444; margin-right: 8px;"></i> Admin Dashboard</a>
                        </li>
                        <li>
                            <a href="profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- ============= main content ============= -->
        <main class="main-content" style="padding: 30px;">
            <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <div>
                    <h1 style="color: #fff; font-size: 24px;">Admin Dashboard</h1>
                    <p style="color: #94a3b8; margin-top: 5px;">Manage users, documents, and system settings.</p>
                </div>
                <div class="user-profile" style="cursor: pointer;" onclick="window.location.href='profile.php'">
                    <div class="profile-avatar" style="overflow: hidden;">
                        <?php if (!empty($_SESSION['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($_SESSION['profile_picture']) ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <span class="user-role" style="color: #ef4444; font-weight: bold;"><i class="fa-solid fa-shield"></i> <?= htmlspecialchars($_SESSION['role']) ?></span>
                    </div>
                    <a href="../PHP/logout.php" class="btn-logout" title="Logout" onclick="event.stopPropagation();"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
                </div>
            </header>

            <?php if ($error): ?>
                <div style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-container">
                <!-- Row 1 -->
                <div class="stat-card">
                    <div class="stat-icon bg-cyan"><i class="fa-solid fa-file-lines"></i></div>
                    <div class="stat-info">
                        <p>Total Docs</p>
                        <h3><?= $totalDocs ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-light-blue"><i class="fa-solid fa-earth-americas"></i></div>
                    <div class="stat-info">
                        <p>Public Docs</p>
                        <h3><?= $publicDocs ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-cyan"><i class="fa-solid fa-lock"></i></div>
                    <div class="stat-info">
                        <p>Private Docs</p>
                        <h3><?= $privateDocs ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-light-blue"><i class="fa-solid fa-building"></i></div>
                    <div class="stat-info">
                        <p>Dept Docs</p>
                        <h3><?= $deptDocs ?></h3>
                    </div>
                </div>
                
                <!-- Row 2 -->
                <div class="stat-card">
                    <div class="stat-icon bg-green"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-info">
                        <p>Total Users</p>
                        <h3><?= $totalUsers ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-red"><i class="fa-solid fa-shield"></i></div>
                    <div class="stat-info">
                        <p>Admin Users</p>
                        <h3><?= $adminUsers ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-green"><i class="fa-solid fa-users-viewfinder"></i></div>
                    <div class="stat-info">
                        <p>Public Users</p>
                        <h3><?= $publicUsers ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-purple"><i class="fa-solid fa-user-tie"></i></div>
                    <div class="stat-info">
                        <p>Dept Users</p>
                        <h3><?= $deptUsers ?></h3>
                    </div>
                </div>
                
                <!-- Row 3 -->
                <div class="stat-card">
                    <div class="stat-icon bg-pink"><i class="fa-solid fa-comments"></i></div>
                    <div class="stat-info">
                        <p>Messages</p>
                        <h3><?= $totalMessages ?></h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-light-blue"><i class="fa-solid fa-folder-open"></i></div>
                    <div class="stat-info">
                        <p>Categories</p>
                        <h3>6</h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-orange"><i class="fa-solid fa-server"></i></div>
                    <div class="stat-info">
                        <p>Server Status</p>
                        <h3 style="font-size: 18px; margin-top: 5px;">Online</h3>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon bg-dark-orange"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <div class="stat-info">
                        <p>System Logs</p>
                        <h3>0</h3>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-container">
                <!-- Disk Space Chart -->
                <div class="chart-card">
                    <div class="chart-header">Total Disk Space Usage</div>
                    <div class="chart-body">
                        <canvas id="diskChart"></canvas>
                    </div>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <span><span class="legend-color" style="background: #10b981;"></span>Free Space</span>
                            <span><?= $freeGB ?> GB</span>
                        </div>
                        <div class="legend-item">
                            <span><span class="legend-color" style="background: #ef4444;"></span>Used Space</span>
                            <span><?= formatBytesToMB($totalUsedBytes) ?> MB</span>
                        </div>
                    </div>
                </div>

                <!-- File Categories Chart -->
                <div class="chart-card">
                    <div class="chart-header">File Categories</div>
                    <div class="chart-body">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <span><span class="legend-color" style="background: #3b82f6;"></span>Public Documents</span>
                            <span><?= $catCounts['Public'] ?></span>
                        </div>
                        <div class="legend-item">
                            <span><span class="legend-color" style="background: #a855f7;"></span>Private Documents</span>
                            <span><?= $catCounts['Private'] ?></span>
                        </div>
                        <div class="legend-item">
                            <span><span class="legend-color" style="background: #f59e0b;"></span>Internal Documents</span>
                            <span><?= $catCounts['Internal'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Docs by Users Chart -->
                <div class="chart-card">
                    <div class="chart-header">No. of Docs by Users</div>
                    <div class="chart-body">
                        <canvas id="userChart"></canvas>
                    </div>
                    <div class="chart-legend">
                        <?php 
                        $colors = ['#f43f5e', '#8b5cf6', '#14b8a6', '#facc15', '#3b82f6'];
                        $i = 0;
                        arsort($userCounts);
                        foreach(array_slice($userCounts, 0, 5) as $user => $count): 
                            $c = $colors[$i % count($colors)];
                        ?>
                        <div class="legend-item">
                            <span><span class="legend-color" style="background: <?= $c ?>;"></span><?= htmlspecialchars($user) ?></span>
                            <span><?= $count ?> docs</span>
                        </div>
                        <?php $i++; endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- User Management -->
            <section class="admin-section">
                <h2><i class="fa-solid fa-users-gear" style="color: var(--theme-color);"></i> User Management</h2>
                <div style="overflow-x: auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Joined Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; overflow: hidden; font-size: 12px; font-weight: bold;">
                                            <?php if (!empty($u['profile_picture'])): ?>
                                                <img src="<?= htmlspecialchars($u['profile_picture']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <?= strtoupper(substr($u['username'], 0, 2)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <?= htmlspecialchars($u['username']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                        <span style="color: #ef4444; font-weight: bold;">Admin (You)</span>
                                    <?php else: ?>
                                        <form method="POST" style="display: flex; gap: 5px;">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                            <select name="new_role" class="role-select" onchange="this.form.submit()">
                                                <option value="Public" <?= $u['role'] === 'Public' ? 'selected' : '' ?>>Public</option>
                                                <option value="Private" <?= $u['role'] === 'Private' ? 'selected' : '' ?>>Private</option>
                                                <option value="Faculty Only" <?= $u['role'] === 'Faculty Only' ? 'selected' : '' ?>>Faculty Only</option>
                                                <option value="HR Only" <?= $u['role'] === 'HR Only' ? 'selected' : '' ?>>HR Only</option>
                                                <option value="Registrar Only" <?= $u['role'] === 'Registrar Only' ? 'selected' : '' ?>>Registrar Only</option>
                                                <option value="Finance Only" <?= $u['role'] === 'Finance Only' ? 'selected' : '' ?>>Finance Only</option>
                                                <option value="Admin" <?= $u['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <?php if ($u['id'] != $_SESSION['user_id'] && $u['role'] != 'Admin'): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user? This will also delete all their documents and messages.');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn-danger"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Document Management -->
            <section class="admin-section">
                <h2><i class="fa-solid fa-file-shield" style="color: #10b981;"></i> Document Management</h2>
                <div style="overflow-x: auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Document Title</th>
                                <th>Category</th>
                                <th>Uploader</th>
                                <th>Upload Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $d): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['title']) ?></strong></td>
                                <td><span style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; font-size: 12px;"><?= htmlspecialchars($d['category']) ?></span></td>
                                <td><?= htmlspecialchars($d['uploader']) ?></td>
                                <td><?= date('M d, Y H:i', strtotime($d['upload_date'])) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this document?');">
                                        <input type="hidden" name="action" value="delete_document">
                                        <input type="hidden" name="target_id" value="<?= $d['id'] ?>">
                                        <button type="submit" class="btn-danger"><i class="fa-solid fa-trash"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">No documents found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

    <script>
        // Default Chart.js settings for dark theme
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = 'Inter, sans-serif';

        // Custom plugin to draw text in the center of the doughnut chart
        const centerTextPlugin = {
            id: 'centerText',
            beforeDraw: function(chart) {
                if (chart.config.options.elements && chart.config.options.elements.center) {
                    var ctx = chart.ctx;
                    var centerConfig = chart.config.options.elements.center;
                    var text = centerConfig.text;
                    var color = centerConfig.color || '#fff';
                    ctx.save();
                    ctx.font = "bold 20px Inter, sans-serif";
                    ctx.fillStyle = color;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    var centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                    var centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                    ctx.fillText(text, centerX, centerY);
                    ctx.restore();
                }
            }
        };
        Chart.register(centerTextPlugin);

        <?php 
            // Calculate percentages
            $usedPercent = $totalDiskCapacity > 0 ? round(($totalUsedBytes / $totalDiskCapacity) * 100, 1) : 0;
            $totalDocCount = array_sum($catCounts);
            $publicPercent = $totalDocCount > 0 ? round(($catCounts['Public'] / $totalDocCount) * 100, 1) : 0;
            $topUserPercent = 0;
            if (count($userCounts) > 0) {
                $topUserCount = array_values($userCounts)[0];
                $topUserPercent = $totalDocCount > 0 ? round(($topUserCount / $totalDocCount) * 100, 1) : 0;
            }
        ?>

        // 1. Disk Space Chart
        const diskCtx = document.getElementById('diskChart').getContext('2d');
        new Chart(diskCtx, {
            type: 'doughnut',
            data: {
                labels: ['Free Space', 'Used Space'],
                datasets: [{
                    data: [<?= $freeSpaceBytes ?>, <?= $totalUsedBytes ?: 1 ?>], // +1 fallback to render empty
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#1e293b'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false }
                },
                elements: {
                    center: {
                        text: '<?= $usedPercent ?>%',
                        color: '#f8fafc'
                    }
                }
            }
        });

        // 2. Categories Chart
        const catCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: ['Public', 'Private', 'Internal'],
                datasets: [{
                    data: [<?= $catCounts['Public'] ?>, <?= $catCounts['Private'] ?>, <?= $catCounts['Internal'] ?>],
                    backgroundColor: ['#3b82f6', '#a855f7', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#1e293b'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false }
                },
                elements: {
                    center: {
                        text: '<?= $publicPercent ?>%',
                        color: '#f8fafc'
                    }
                }
            }
        });

        // 3. User Chart
        const userCtx = document.getElementById('userChart').getContext('2d');
        new Chart(userCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys(array_slice($userCounts, 0, 5))) ?>,
                datasets: [{
                    data: <?= json_encode(array_values(array_slice($userCounts, 0, 5))) ?>,
                    backgroundColor: ['#f43f5e', '#8b5cf6', '#14b8a6', '#facc15', '#3b82f6'],
                    borderWidth: 2,
                    borderColor: '#1e293b'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { display: false }
                },
                elements: {
                    center: {
                        text: '<?= $topUserPercent ?>%',
                        color: '#f8fafc'
                    }
                }
            }
        });
    </script>
</body>
</html>

<?php
// Show errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'dbname' => 'contacts_db'
];

// Create connection
try {
    $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['dbname']);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Initialize variables
$message = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Add contact
if (isset($_POST['add'])) {
    try {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $company = trim($_POST['company']);
        $category = trim($_POST['category']);

        if (empty($name) || empty($phone) || empty($email)) {
            throw new Exception("Name, phone, and email are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        $stmt = $conn->prepare("INSERT INTO contacts (name, phone, email, address, company, category) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssssss", $name, $phone, $email, $address, $company, $category);

        if ($stmt->execute()) {
            $message = "Contact added successfully.";
        } else {
            throw new Exception("Error adding contact: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Delete contact
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        if ($id <= 0) {
            throw new Exception("Invalid contact ID.");
        }

        $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $message = "Contact deleted successfully.";
        } else {
            throw new Exception("Error deleting contact: " . $stmt->error);
        }
        $stmt->close();
        header("Location: index.php?message=" . urlencode($message));
        exit();
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Update contact
if (isset($_POST['update'])) {
    try {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $company = trim($_POST['company']);
        $category = trim($_POST['category']);

        if ($id <= 0) {
            throw new Exception("Invalid contact ID.");
        }

        if (empty($name) || empty($phone) || empty($email)) {
            throw new Exception("Name, phone, and email are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        $stmt = $conn->prepare("UPDATE contacts SET name = ?, phone = ?, email = ?, address = ?, company = ?, category = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssssssi", $name, $phone, $email, $address, $company, $category, $id);

        if ($stmt->execute()) {
            $message = "Contact updated successfully.";
        } else {
            throw new Exception("Error updating contact: " . $stmt->error);
        }
        $stmt->close();
        header("Location: index.php?message=" . urlencode($message));
        exit();
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Check for redirect message
if (isset($_GET['message'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
}

// Fetch contacts with search functionality
$contacts = [];
try {
    $query = "SELECT * FROM contacts";
    if (!empty($search)) {
        $query .= " WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? OR company LIKE ? OR category LIKE ?";
    }
    $query .= " ORDER BY name";

    $stmt = $conn->prepare($query);
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bind_param("sssss", $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $contacts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    $message = "Error fetching contacts: " . $e->getMessage();
}

// Get contact for editing
$editedContact = ['id' => '', 'name' => '', 'phone' => '', 'email' => '', 'address' => '', 'company' => '', 'category' => ''];
if (isset($_GET['edit'])) {
    try {
        $id = (int)$_GET['edit'];
        if ($id <= 0) {
            throw new Exception("Invalid contact ID.");
        }

        $stmt = $conn->prepare("SELECT * FROM contacts WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $editedContact = $result->fetch_assoc();
        } else {
            throw new Exception("Contact not found.");
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get statistics
$totalContacts = count($contacts);
$categories = [];
try {
    $result = $conn->query("SELECT category, COUNT(*) as count FROM contacts WHERE category != '' GROUP BY category ORDER BY count DESC");
    if ($result) {
        $categories = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
} catch (Exception $e) {
    // Handle error silently for statistics
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --danger-color: #f72585;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-color: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f5f7fb;
            color: var(--dark-color);
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            transform: rotate(30deg);
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
            font-weight: 700;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            padding: 20px;
            background: white;
            margin: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: var(--light-color);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .search-section {
            padding: 20px;
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .search-box {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            font-size: 16px;
            transition: var(--transition);
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 18px;
        }

        .content {
            padding: 30px;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: var(--box-shadow);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
        }

        .btn-secondary {
            background: white;
            color: var(--gray-color);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        .contacts-table {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-body {
            max-height: 600px;
            overflow-y: auto;
        }

        .contact-row {
            display: grid;
            grid-template-columns: 2fr 1.5fr 2fr 1.5fr 1fr 1fr 120px;
            gap: 15px;
            padding: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            align-items: center;
            transition: var(--transition);
        }

        .contact-row:last-child {
            border-bottom: none;
        }

        .contact-row:hover {
            background: rgba(67, 97, 238, 0.05);
        }

        .contact-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            flex-shrink: 0;
        }

        .contact-details h4 {
            margin: 0;
            color: var(--dark-color);
            font-size: 16px;
            font-weight: 600;
        }

        .contact-meta {
            font-size: 12px;
            color: var(--gray-color);
            margin-top: 2px;
        }

        .category-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 50px;
            box-shadow: none;
        }

        .btn-edit {
            background: var(--success-color);
            color: white;
        }

        .btn-edit:hover {
            background: #3aa8d8;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: var(--danger-color);
            color: white;
        }

        .btn-delete:hover {
            background: #e5177e;
            transform: translateY(-2px);
        }

        .notification {
            padding: 15px 20px;
            margin: 20px;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-align: center;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: var(--box-shadow);
        }

        .notification.success {
            background: linear-gradient(135deg, var(--success-color), #3aa8d8);
            color: white;
        }

        .notification.error {
            background: linear-gradient(135deg, var(--danger-color), #e5177e);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-color);
        }

        .empty-state i {
            font-size: 4rem;
            color: rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--dark-color);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateY(0);
                opacity: 1;
            }

            to {
                transform: translateY(-20px);
                opacity: 0;
            }
        }

        @media (max-width: 992px) {
            .contact-row {
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: auto auto;
                row-gap: 15px;
            }

            .contact-info {
                grid-column: 1 / span 3;
            }

            .action-buttons {
                grid-column: 1 / span 3;
                justify-content: flex-end;
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 10px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .contact-row {
                grid-template-columns: 1fr;
                padding: 15px;
                gap: 10px;
            }

            .contact-row>div {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .contact-row>div::before {
                content: attr(data-label);
                font-weight: bold;
                color: var(--gray-color);
                margin-right: 15px;
                flex: 1;
            }

            .contact-row>div>* {
                flex: 2;
                text-align: right;
            }

            .contact-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .contact-info::before {
                content: none;
            }

            .action-buttons {
                justify-content: center;
                margin-top: 10px;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-address-book"></i> Contact Manager</h1>
            <p>Manage your contacts efficiently with this powerful tool</p>
        </div>

        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?php echo $totalContacts; ?></div>
                <div class="stat-label">Total Contacts</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count($categories); ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo empty($search) ? $totalContacts : count($contacts); ?></div>
                <div class="stat-label">Displayed</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo date('Y'); ?></div>
                <div class="stat-label">Current Year</div>
            </div>
        </div>

        <div class="search-section">
            <form method="get" class="search-box">
                <input type="text" name="search" class="search-input"
                    placeholder="Search contacts by name, phone, email, company, or category..."
                    value="<?php echo htmlspecialchars($search); ?>">
                <i class="fas fa-search search-icon"></i>
            </form>
        </div>

        <?php if (!empty($message)): ?>
            <div class="notification <?php echo strpos($message, 'Error') === 0 ? 'error' : 'success'; ?>">
                <i class="fas <?php echo strpos($message, 'Error') === 0 ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="content">
            <div class="form-container">
                <form method="post" action="">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editedContact['id']); ?>">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" id="name" name="name" class="form-control"
                                placeholder="Enter full name"
                                value="<?php echo htmlspecialchars($editedContact['name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone Number *</label>
                            <input type="text" id="phone" name="phone" class="form-control"
                                placeholder="Enter phone number"
                                value="<?php echo htmlspecialchars($editedContact['phone']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control"
                                placeholder="Enter email address"
                                value="<?php echo htmlspecialchars($editedContact['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="company"><i class="fas fa-building"></i> Company</label>
                            <input type="text" id="company" name="company" class="form-control"
                                placeholder="Enter company name"
                                value="<?php echo htmlspecialchars($editedContact['company']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="category"><i class="fas fa-tags"></i> Category</label>
                            <input type="text" id="category" name="category" class="form-control"
                                placeholder="e.g., Family, Work, Friends"
                                value="<?php echo htmlspecialchars($editedContact['category']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" id="address" name="address" class="form-control"
                                placeholder="Enter address"
                                value="<?php echo htmlspecialchars($editedContact['address']); ?>">
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php if (!empty($editedContact['id'])): ?>
                            <button type="submit" name="update" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Contact
                            </button>
                        <?php else: ?>
                            <button type="submit" name="add" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Contact
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="contacts-table">
                <div class="table-header">
                    <div>
                        <i class="fas fa-list"></i> Contact List
                    </div>
                    <?php if (!empty($search)): ?>
                        <div style="font-weight: normal;">
                            Search results for: "<?php echo htmlspecialchars($search); ?>"
                            <a href="index.php" style="color: white; margin-left: 10px;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-body">
                    <?php if (!empty($contacts)): ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="contact-row">
                                <div class="contact-info" data-label="Contact:">
                                    <div class="avatar">
                                        <?php echo strtoupper(substr($contact['name'], 0, 1)); ?>
                                    </div>
                                    <div class="contact-details">
                                        <h4><?php echo htmlspecialchars($contact['name']); ?></h4>
                                        <?php if (!empty($contact['company'])): ?>
                                            <div class="contact-meta">
                                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($contact['company']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div data-label="Phone:">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($contact['phone']); ?>
                                </div>

                                <div data-label="Email:">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($contact['email']); ?>
                                </div>

                                <div data-label="Address:">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo !empty($contact['address']) ? htmlspecialchars($contact['address']) : '-'; ?>
                                </div>

                                <div data-label="Category:">
                                    <?php if (!empty($contact['category'])): ?>
                                        <span class="category-badge"><?php echo htmlspecialchars($contact['category']); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--gray-color);">-</span>
                                    <?php endif; ?>
                                </div>

                                <div class="action-buttons">
                                    <a href="?edit=<?php echo $contact['id']; ?>" class="btn btn-edit btn-sm" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $contact['id']; ?>"
                                        class="btn btn-delete btn-sm"
                                        onclick="return confirm('Are you sure you want to delete this contact?')" title="Delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-address-book"></i>
                            <h3>No contacts found</h3>
                            <p><?php echo !empty($search) ? 'Try adjusting your search terms.' : 'Start by adding your first contact above.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide notifications after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.querySelector('.notification');
            if (notification) {
                setTimeout(() => {
                    notification.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(() => notification.remove(), 300);
                }, 5000);
            }
        });

        // Focus on search input when pressing Ctrl+K
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-input').focus();
            }
        });

        // Smooth scroll for form submission
        if (window.location.hash === '#form') {
            document.querySelector('.form-container').scrollIntoView({
                behavior: 'smooth'
            });
        }
    </script>
</body>

</html>

<?php
$conn->close();
?>
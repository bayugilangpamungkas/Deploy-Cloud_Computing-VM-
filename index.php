<?php
// Show errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$servername = "localhost";
$username = "contact-book";
$password = "C0nt4ct-B00k";
$dbname = "contacts_db";

// Local dev
$username = "root";
$password = "root";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add contact
if(isset($_POST['add'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    $sql = "INSERT INTO contacts (name, phone, email) VALUES ('$name', '$phone', '$email')";

    if ($conn->query($sql) === TRUE) {
        $message = "Contact added successfully.";
    } else {
        $message = "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Delete contact
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM contacts WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        $message = "Contact deleted successfully.";
    } else {
        $message = "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Update contact
if(isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    $sql = "UPDATE contacts SET name='$name', phone='$phone', email='$email' WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        $message = "Contact updated successfully.";
    } else {
        $message = "Error updating contact: " . $conn->error;
    }
}

// Fetch contacts
$sql = "SELECT * FROM contacts";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Contact Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: aqua;
        }
        .container {
            max-width: 80%;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .reset-button {
            background-color: #e6b759;
            color: white;
            padding: 8px 20px 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        h2, h4 {
            text-align: center;
        }
        form {
            margin-bottom: 20px;
        }
        input[type="text"], input[type="email"] {
            width: 98%;
            padding: 10px 0 10px 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        tr:hover {
            background-color: #f2f2f2;
        }
        .notification {
            background-color: #4CAF50;
            color: white;
            text-align: center;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>PHP ContactBook</h2>
    <h4 style="font-style: italic;">Simple contact management app written in PHP</h4>
    <?php if(isset($message)): ?>
        <div class="notification"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if(isset($_GET['edit'])) { ?>
        <?php
            $sql = "SELECT * FROM contacts WHERE id=".$_GET['edit'];
            $records = $conn->query($sql);
            $editedContact = $records->fetch_assoc();
        ?>
    <?php } else { ?>
        <?php $editedContact = ['name' => '', 'phone' => '', 'email' => '']; ?>
    <?php } ?>
    <form method="post" action="">
        <input type="hidden" name="id" value="<?php if(isset($_GET['edit'])) echo $_GET['edit']; ?>">
        <label for="name">Name:</label><br>
        <input type="text" id="name" name="name" placeholder="Contact's full name" value="<?php if(isset($_GET['edit'])) echo $editedContact['name']; ?>" required><br>
        <label for="phone">Phone:</label><br>
        <input type="text" id="phone" name="phone" placeholder="Contact's phone number" value="<?php if(isset($_GET['edit'])) echo $editedContact['phone']; ?>" required><br>
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" placeholder="Contact's email address" value="<?php if(isset($_GET['edit'])) echo $editedContact['email']; ?>" required><br><br>
        <a class="reset-button" href="index.php">Reset</a>
        <?php if(isset($_GET['edit'])): ?>
            <input type="submit" name="update" value="Update Contact">
        <?php else: ?>
            <input type="submit" name="add" value="Add Contact">
        <?php endif; ?>
    </form>
    <table>
        <tr>
            <th>Name</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["name"]. "</td>";
                echo "<td>" . $row["phone"]. "</td>";
                echo "<td>" . $row["email"]. "</td>";
                echo "<td><a href='?edit=".$row["id"]."'>Edit</a> | <a href='?delete=".$row["id"]."'>Delete</a></td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No contacts found.</td></tr>";
        }
        ?>
    </table>
</div>

</body>
</html>

<?php
$conn->close();
?>

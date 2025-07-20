<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "testbase";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $reviewId = $_POST['review_id'];
        $stmt = $pdo->prepare("UPDATE product_review SET status = 'approved' WHERE review_id = ?");
        $stmt->execute([$reviewId]);
        echo json_encode(['success' => true, 'message' => 'Review approved successfully']);
        exit;
    }
    
    if ($action === 'delete') {
        $reviewId = $_POST['review_id'];
        $stmt = $pdo->prepare("DELETE FROM product_review WHERE review_id = ?");
        $stmt->execute([$reviewId]);
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
        exit;
    }
    
    if ($action === 'reply') {
        $reviewId = $_POST['review_id'];
        $replyContent = $_POST['reply_content'];
        $stmt = $pdo->prepare("UPDATE product_review SET admin_reply = ? WHERE review_id = ?");
        $stmt->execute([$replyContent, $reviewId]);
        echo json_encode(['success' => true, 'message' => 'Reply added successfully']);
        exit;
    }
    
    if ($action === 'get_reviews') {
        $status = $_POST['status'] ?? 'all';
        $query = "SELECT * FROM product_review";
        if ($status !== 'all') {
            $query .= " WHERE status = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
        }
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($reviews);
        exit;
    }
}

// Add status and admin_reply columns if they don't exist
try {
    $pdo->exec("ALTER TABLE product_review ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
} catch(PDOException $e) {
    // Column already exists
}

try {
    $pdo->exec("ALTER TABLE product_review ADD COLUMN admin_reply TEXT DEFAULT NULL");
} catch(PDOException $e) {
    // Column already exists
}

try {
    $pdo->exec("ALTER TABLE product_review ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
} catch(PDOException $e) {
    // Column already exists
}

// Get all reviews for initial load
$stmt = $pdo->prepare("SELECT * FROM product_review ORDER BY created_at DESC");
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-size: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            font-size: 22px;
            opacity: 0.9;
        }

        .controls {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            background: #e9ecef;
            color: #495057;
            font-size: 20px;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .reviews-container {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .review-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 15px;
            margin-bottom: 20px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .reviewer-info h3 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .reviewer-info p {
            color: #6c757d;
            font-size: 20px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 20px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .review-title {
            font-size: 22px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .review-content {
            color: #495057;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .review-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 20px;
            color: #6c757d;
        }

        .admin-reply {
            background: #f8f9fa;
            border-left: 4px solid #4facfe;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .admin-reply h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 20px;
        }

        .reply-form {
            display: none;
            margin-bottom: 15px;
        }

        .reply-form textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
            transition: border-color 0.3s ease;
            font-size: 20px;
        }

        .reply-form textarea:focus {
            outline: none;
            border-color: #4facfe;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .btn-reply {
            background: #17a2b8;
            color: white;
        }

        .btn-reply:hover {
            background: #138496;
            transform: translateY(-1px);
        }

        .btn-submit {
            background: #007bff;
            color: white;
        }

        .btn-submit:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #545b62;
            transform: translateY(-1px);
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 22px;
        }

        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 22px;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 18px 30px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            font-size: 20px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: #28a745;
        }

        .notification.error {
            background: #dc3545;
        }

        .product-id {
            font-size: 20px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-block;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 2em;
            }

            .controls {
                padding: 15px 20px;
            }

            .reviews-container {
                padding: 20px;
            }

            .review-card {
                padding: 20px;
            }

            .review-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-buttons {
                justify-content: center;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reviews Management</h1>
            <p>Monitor and manage customer feedback</p>
        </div>

        <div class="controls">
            <div class="filter-buttons">
                <button class="filter-btn active" data-status="all">All Reviews</button>
                <button class="filter-btn" data-status="pending">Pending</button>
                <button class="filter-btn" data-status="approved">Approved</button>
                <button class="filter-btn" data-status="rejected">Rejected</button>
            </div>
        </div>

        <div class="reviews-container" id="reviewsContainer">
            <?php if (empty($reviews)): ?>
                <div class="no-reviews">
                    <h3>No reviews found</h3>
                    <p>There are currently no reviews in the system.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card fade-in" data-status="<?php echo $review['status'] ?? 'pending'; ?>">
                        <div class="review-header">
                            <div class="reviewer-info">
                                <h3><?php echo htmlspecialchars($review['full_name']); ?></h3>
                                <p><?php echo htmlspecialchars($review['email_address']); ?></p>
                                <?php if ($review['product_id']): ?>
                                    <span class="product-id">Product ID: <?php echo $review['product_id']; ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="status-badge status-<?php echo $review['status'] ?? 'pending'; ?>">
                                <?php echo ucfirst($review['status'] ?? 'pending'); ?>
                            </span>
                        </div>

                        <?php if ($review['review_title']): ?>
                            <div class="review-title"><?php echo htmlspecialchars($review['review_title']); ?></div>
                        <?php endif; ?>

                        <div class="review-content">
                            <?php echo nl2br(htmlspecialchars($review['review_content'])); ?>
                        </div>

                        <div class="review-meta">
                            <span>Review ID: <?php echo $review['review_id']; ?></span>
                            <?php if (isset($review['created_at'])): ?>
                                <span><?php echo date('M j, Y g:i A', strtotime($review['created_at'])); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($review['admin_reply'])): ?>
                            <div class="admin-reply">
                                <h4>Admin Reply:</h4>
                                <p><?php echo nl2br(htmlspecialchars($review['admin_reply'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="reply-form" id="replyForm<?php echo $review['review_id']; ?>">
                            <textarea placeholder="Type your reply here..." id="replyText<?php echo $review['review_id']; ?>"></textarea>
                            <div style="margin-top: 10px;">
                                <button class="btn btn-submit" onclick="submitReply(<?php echo $review['review_id']; ?>)">Submit Reply</button>
                                <button class="btn btn-cancel" onclick="cancelReply(<?php echo $review['review_id']; ?>)">Cancel</button>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <?php if (($review['status'] ?? 'pending') !== 'approved'): ?>
                                <button class="btn btn-approve" onclick="approveReview(<?php echo $review['review_id']; ?>)">Approve</button>
                            <?php endif; ?>
                            <button class="btn btn-reply" onclick="showReplyForm(<?php echo $review['review_id']; ?>)">Reply</button>
                            <button class="btn btn-delete" onclick="deleteReview(<?php echo $review['review_id']; ?>)">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let currentFilter = 'all';

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                currentFilter = this.dataset.status;
                filterReviews(currentFilter);
            });
        });

        function filterReviews(status) {
            const reviewCards = document.querySelectorAll('.review-card');
            
            reviewCards.forEach(card => {
                const cardStatus = card.dataset.status;
                if (status === 'all' || cardStatus === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function approveReview(reviewId) {
            if (!confirm('Are you sure you want to approve this review?')) return;
            
            fetch('reviews.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=approve&review_id=${reviewId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Update the card status
                    const card = document.querySelector(`[data-review-id="${reviewId}"]`) || 
                                 document.querySelector(`.review-card:has(.action-buttons button[onclick*="${reviewId}"])`);
                    if (card) {
                        card.dataset.status = 'approved';
                        const statusBadge = card.querySelector('.status-badge');
                        if (statusBadge) {
                            statusBadge.textContent = 'Approved';
                            statusBadge.className = 'status-badge status-approved';
                        }
                        // Remove approve button
                        const approveBtn = card.querySelector('.btn-approve');
                        if (approveBtn) approveBtn.style.display = 'none';
                    }
                    filterReviews(currentFilter);
                } else {
                    showNotification('Error approving review', 'error');
                }
            })
            .catch(error => {
                showNotification('Error approving review', 'error');
                console.error('Error:', error);
            });
        }

        function deleteReview(reviewId) {
            if (!confirm('Are you sure you want to delete this review? This action cannot be undone.')) return;
            
            fetch('reviews.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&review_id=${reviewId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Remove the card from DOM
                    const card = document.querySelector(`[data-review-id="${reviewId}"]`) || 
                                 document.querySelector(`.review-card:has(.action-buttons button[onclick*="${reviewId}"])`);
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.transform = 'translateX(-100%)';
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 300);
                    }
                } else {
                    showNotification('Error deleting review', 'error');
                }
            })
            .catch(error => {
                showNotification('Error deleting review', 'error');
                console.error('Error:', error);
            });
        }

        function showReplyForm(reviewId) {
            const replyForm = document.getElementById(`replyForm${reviewId}`);
            const replyBtn = event.target;
            
            if (replyForm.style.display === 'block') {
                replyForm.style.display = 'none';
                replyBtn.textContent = 'Reply';
            } else {
                replyForm.style.display = 'block';
                replyBtn.textContent = 'Hide Reply';
                document.getElementById(`replyText${reviewId}`).focus();
            }
        }

        function submitReply(reviewId) {
            const replyText = document.getElementById(`replyText${reviewId}`).value.trim();
            
            if (!replyText) {
                showNotification('Please enter a reply', 'error');
                return;
            }
            
            fetch('reviews.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=reply&review_id=${reviewId}&reply_content=${encodeURIComponent(replyText)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Hide reply form and update UI
                    cancelReply(reviewId);
                    
                    // Add or update admin reply display
                    const card = document.querySelector(`[data-review-id="${reviewId}"]`) || 
                                 document.querySelector(`.review-card:has(.action-buttons button[onclick*="${reviewId}"])`);
                    if (card) {
                        let adminReplyDiv = card.querySelector('.admin-reply');
                        if (!adminReplyDiv) {
                            adminReplyDiv = document.createElement('div');
                            adminReplyDiv.className = 'admin-reply';
                            card.querySelector('.review-meta').insertAdjacentElement('afterend', adminReplyDiv);
                        }
                        adminReplyDiv.innerHTML = `
                            <h4>Admin Reply:</h4>
                            <p>${replyText.replace(/\n/g, '<br>')}</p>
                        `;
                    }
                } else {
                    showNotification('Error submitting reply', 'error');
                }
            })
            .catch(error => {
                showNotification('Error submitting reply', 'error');
                console.error('Error:', error);
            });
        }

        function cancelReply(reviewId) {
            const replyForm = document.getElementById(`replyForm${reviewId}`);
            const replyText = document.getElementById(`replyText${reviewId}`);
            
            replyForm.style.display = 'none';
            replyText.value = '';
            
            // Reset reply button text
            const card = document.querySelector(`[data-review-id="${reviewId}"]`) || 
                         document.querySelector(`.review-card:has(.action-buttons button[onclick*="${reviewId}"])`);
            if (card) {
                const replyBtn = card.querySelector('.btn-reply');
                if (replyBtn) replyBtn.textContent = 'Reply';
            }
        }

        function showNotification(message, type) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notif => notif.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Trigger show animation
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add smooth scrolling
        document.addEventListener('DOMContentLoaded', function() {
            const reviewsContainer = document.getElementById('reviewsContainer');
            reviewsContainer.style.scrollBehavior = 'smooth';
        });
    </script>
</body>
</html>
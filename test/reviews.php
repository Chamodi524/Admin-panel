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
    <title>Reviews Management - Allura Estella</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
            font-size: 20px;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Formal Header - Matching Coupons Management */
        .formal-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 20px 0;
            margin: -30px -30px 40px -30px;
            position: relative;
            overflow: hidden;
        }

        .formal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><linearGradient id="a" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="1" stop-color="%23ffffff" stop-opacity="0"/></linearGradient></defs><rect width="11" height="20" fill="url(%23a)" rx="5"/><rect x="22" width="11" height="20" fill="url(%23a)" rx="5"/><rect x="44" width="11" height="20" fill="url(%23a)" rx="5"/><rect x="66" width="11" height="20" fill="url(%23a)" rx="5"/><rect x="88" width="11" height="20" fill="url(%23a)" rx="5"/></svg>') repeat;
            opacity: 0.1;
        }

        .formal-header-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 30px;
            position: relative;
            z-index: 1;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .company-logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            flex-shrink: 0;
        }

        .header-text {
            text-align: left;
        }

        .company-name {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .company-subtitle {
            font-size: 16px;
            font-weight: 300;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .system-title {
            font-size: 22px;
            font-weight: 600;
            color: #45b7d1;
            margin-bottom: 5px;
        }

        .current-date-time {
            font-size: 14px;
            opacity: 0.8;
            font-weight: 300;
        }

        /* Main Content Styling */
        .content-wrapper {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .controls {
            padding: 20px 0;
            background: transparent;
            border-bottom: 2px solid #f1f3f4;
            margin-bottom: 30px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 16px 30px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 20px;
            background: #e9ecef;
            color: #495057;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .reviews-container {
            max-height: 70vh;
            overflow-y: auto;
        }

        .review-card {
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 20px;
            margin-bottom: 25px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .reviewer-info h3 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .reviewer-info p {
            color: #718096;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .status-badge {
            padding: 12px 20px;
            border-radius: 25px;
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        .status-approved {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
        }

        .status-rejected {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #742a2a;
        }

        .review-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .review-content {
            color: #4a5568;
            line-height: 1.7;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .review-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 18px;
            color: #718096;
            padding: 15px 20px;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            border-radius: 12px;
        }

        .admin-reply {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-left: 4px solid #3498db;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .admin-reply h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 22px;
            font-weight: 600;
        }

        .reply-form {
            display: none;
            margin-bottom: 20px;
        }

        .reply-form textarea {
            width: 100%;
            padding: 20px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
            transition: all 0.3s ease;
            font-size: 20px;
            background: white;
        }

        .reply-form textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-approve {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-reply {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-submit {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
            color: white;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .loading {
            text-align: center;
            padding: 60px;
            color: #718096;
            font-size: 24px;
        }

        .no-reviews {
            text-align: center;
            padding: 60px;
            color: #718096;
            font-size: 24px;
        }

        .no-reviews h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 32px;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 35px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            font-size: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        .notification.error {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .product-id {
            font-size: 18px;
            color: #2c3e50;
            background: linear-gradient(135deg, #e8f4fd 0%, #b6e6fc 100%);
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: 20px;
            }
            
            .formal-header {
                margin: -20px -20px 30px -20px;
                padding: 15px 0;
            }

            .formal-header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header-text {
                text-align: center;
            }
            
            .company-name {
                font-size: 28px;
            }
            
            .system-title {
                font-size: 20px;
            }
            
            .content-wrapper {
                padding: 25px;
            }
        }

        @media (max-width: 768px) {
            body {
                font-size: 18px;
            }
            
            .formal-header {
                padding: 10px 0;
            }

            .company-logo {
                width: 80px;
                height: 80px;
            }
            
            .company-name {
                font-size: 24px;
            }
            
            .company-subtitle {
                font-size: 14px;
            }
            
            .system-title {
                font-size: 18px;
            }

            .current-date-time {
                font-size: 12px;
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
    <!-- Formal Header -->
    <div class="formal-header">
        <div class="formal-header-content">
            <img src="allura_estrella.png" alt="Allura Estrella Logo" class="company-logo">
            <div class="header-text">
                <h1 class="company-name">ALLURA ESTELLA</h1>
                <p class="company-subtitle">Premium Women's Clothing & Accessories</p>
                <h2 class="system-title">REVIEWS MANAGEMENT SYSTEM</h2>
                <p class="current-date-time" id="currentDateTime"></p>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="content-wrapper">
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
                                <div style="margin-top: 15px;">
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
    </div>

    <script>
        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZoneName: 'short'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
        }

        // Initialize date time on load
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 1000);
        });

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
            
            // Add fade-in animation to cards
            const cards = document.querySelectorAll('.review-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
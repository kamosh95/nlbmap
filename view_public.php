<?php
require_once 'includes/db_config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM counters WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die("Seller record not found.");
}

// Fetch active prize announcements for this seller's agent
$prize = null;
try {
    $ps = $pdo->prepare("SELECT * FROM prize_announcements WHERE agent_code = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $ps->execute([$record['agent_code']]);
    $prize = $ps->fetch();
} catch (Exception $e) { /* table may not exist */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Details - <?php echo htmlspecialchars($record['seller_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .public-card {
            max-width: 800px;
            margin: 2rem auto;
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .public-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 2.5rem 2rem;
            text-align: center;
            color: #000;
        }
        .seller-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid #fff;
            object-fit: cover;
            margin-bottom: 1rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }
        .info-item {
            background: rgba(255,255,255,0.03);
            padding: 1.25rem;
            border-radius: 16px;
            border: 1px solid var(--glass-border);
        }
        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 0 2rem 2rem;
        }
        .gallery-img {
            width: 100%;
            height: 150px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--glass-border);
            cursor: pointer;
            transition: 0.3s;
        }
        .gallery-img:hover { transform: scale(1.02); }
        .btn-map {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--secondary-color);
            color: #000;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            margin-top: 1rem;
            transition: 0.3s;
        }
        .btn-map:hover { opacity: 0.9; transform: translateY(-2px); }
        .edit-notice {
            text-align: center;
            padding: 1rem;
            background: rgba(0, 212, 255, 0.1);
            color: #00d4ff;
            font-size: 0.85rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container wide">
        <div class="public-card">
            <div class="public-header">
                <?php if ($record['seller_image']): ?>
                    <img src="<?php echo $record['seller_image']; ?>" class="seller-avatar" alt="Seller">
                <?php endif; ?>
                <h1 style="margin: 0; font-size: 1.8rem;"><?php echo htmlspecialchars($record['seller_name']); ?></h1>
                <div style="font-weight: 600; opacity: 0.8; margin-top: 5px;">Reg No: <?php echo htmlspecialchars($record['reg_number'] ?: 'N/A'); ?></div>
                
                <?php if ($record['location_link']): ?>
                    <a href="<?php echo $record['location_link']; ?>" target="_blank" class="btn-map">📍 View on Google Maps</a>
                <?php endif; ?>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Seller Code</div>
                    <div class="info-value"><?php echo htmlspecialchars($record['seller_code']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dealer / Agent</div>
                    <div class="info-value"><?php echo htmlspecialchars($record['dealer_code']); ?> / <?php echo htmlspecialchars($record['agent_code']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Method of Sales</div>
                    <div class="info-value"><?php echo htmlspecialchars($record['sales_method']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">District / DS Division</div>
                    <div class="info-value"><?php echo htmlspecialchars($record['district']); ?> / <?php echo htmlspecialchars($record['ds_division']); ?></div>
                </div>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-label">Full Address</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($record['address'])); ?></div>
                </div>
            </div>

            <div style="padding: 0 2rem; margin-bottom: 1rem;">
                <h3 style="color: var(--secondary-color); font-size: 1rem; text-transform: uppercase;">📸 Location Photos</h3>
            </div>
            <div class="image-gallery">
                <?php if ($record['image_front']): ?>
                    <img src="<?php echo $record['image_front']; ?>" class="gallery-img" onclick="openLightbox(this.src)" title="Front View">
                <?php endif; ?>
                <?php if ($record['image_side']): ?>
                    <img src="<?php echo $record['image_side']; ?>" class="gallery-img" onclick="openLightbox(this.src)" title="Side View">
                <?php endif; ?>
                <?php if ($record['image_inside']): ?>
                    <img src="<?php echo $record['image_inside']; ?>" class="gallery-img" onclick="openLightbox(this.src)" title="Inside View">
                <?php endif; ?>
            </div>

            <?php if ($prize): ?>
            <!-- 🏆 Prize Announcement Section -->
            <div style="margin: 0 2rem 2rem; padding: 1.75rem; background: linear-gradient(135deg, rgba(251,191,36,0.12), rgba(234,179,8,0.06)); border: 1px solid rgba(251,191,36,0.4); border-radius: 20px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1.25rem;">
                    <div style="font-size: 2.5rem; filter: drop-shadow(0 0 10px rgba(251,191,36,0.6));">🏆</div>
                    <div>
                        <div style="font-size: 1.15rem; font-weight: 800; color: #fbbf24;"><?php echo htmlspecialchars($prize['title']); ?></div>
                        <?php if ($prize['description']): ?>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;"><?php echo htmlspecialchars($prize['description']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                    <?php for ($pi = 1; $pi <= 4; $pi++): $pphoto = $prize["photo_$pi"]; ?>
                        <?php if ($pphoto): ?>
                            <img src="<?php echo htmlspecialchars($pphoto); ?>"
                                 onclick="openLightbox(this.src)"
                                 style="width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 12px; border: 2px solid rgba(251,191,36,0.4); cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.3);"
                                 onmouseover="this.style.transform='scale(1.04)'" onmouseout="this.style.transform='scale(1)'">
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="edit-notice">
                🔒 Authorized staff can edit these details by logging into the portal and scanning this QR.
                <br>
                <a href="login.php?redirect=edit_record.php?id=<?php echo $id; ?>" style="color: #fff; text-decoration: underline; margin-top: 10px; display: inline-block;">Login to Edit Details</a>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-content" id="lightbox-img">
    </div>

    <script>
        function openLightbox(src) {
            document.getElementById('lightbox').style.display = 'flex';
            document.getElementById('lightbox-img').src = src;
        }
        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
        }
    </script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>

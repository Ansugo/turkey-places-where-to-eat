<?php
$api_handler = new TPWE_API_Handler();
?>

<div class="tpwe-widget">
    <div class="tpwe-grid-container">
        <?php foreach ($places as $place): ?>
            <div class="tpwe-place-card">
                <div class="tpwe-place-image">
                    <?php if (!empty($place->photo_reference)): ?>
                        <img src="<?php echo esc_url($api_handler->get_place_photo_url($place->photo_reference, 300)); ?>" 
                             alt="<?php echo esc_attr($place->name); ?>"
                             loading="lazy">
                    <?php else: ?>
                        <div class="tpwe-no-image">No image</div>
                    <?php endif; ?>
                </div>
                
                <div class="tpwe-place-info">
                    <h4 class="tpwe-place-title">
                        <a href="<?php echo esc_url($place->place_url); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($place->name); ?>
                        </a>
                    </h4>
                    
                    <div class="tpwe-place-rating">
                        <div class="tpwe-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star <?php echo $i <= floor($place->rating) ? 'filled' : ''; ?>">★</span>
                            <?php endfor; ?>
                            <span class="rating-value"><?php echo number_format($place->rating, 1); ?></span>
                            <span class="total-ratings">(<?php echo number_format($place->total_ratings); ?>)</span>
                        </div>
                    </div>
                    
                    <?php if ($place->price_level): ?>
                        <div class="tpwe-price-level">
                            <?php echo str_repeat('$', $place->price_level); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($place->address)): ?>
                        <div class="tpwe-place-address">
                            📍 <?php echo esc_html($place->address); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tpwe-actions">
                        <a href="<?php echo esc_url($place->place_url); ?>" 
                           target="_blank" 
                           rel="noopener"
                           class="tpwe-button">
                            See on Google
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="tpwe-footer">
        <small>Data taken from Google Maps. Last update: <?php echo date_i18n('d F Y'); ?></small>
    </div>
</div>
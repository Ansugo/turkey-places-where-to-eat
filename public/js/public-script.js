jQuery(document).ready(function($) {
    // Resim lazy loading
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const src = img.getAttribute('data-src');
                    
                    if (src) {
                        img.src = src;
                        img.removeAttribute('data-src');
                    }
                    
                    observer.unobserve(img);
                }
            });
        });
        
        $('.tpwe-place-image img[data-src]').each(function() {
            imageObserver.observe(this);
        });
    } else {
        // Fallback for older browsers
        $('.tpwe-place-image img[data-src]').each(function() {
            $(this).attr('src', $(this).data('src'));
        });
    }
    
    // Rating yıldızlarını renklendirme
    $('.tpwe-stars').each(function() {
        var rating = parseFloat($(this).find('.rating-value').text());
        var stars = $(this).find('.star');
        
        stars.each(function(index) {
            var starIndex = index + 1;
            var star = $(this);
            
            if (starIndex <= Math.floor(rating)) {
                star.addClass('filled');
            } else if (starIndex - 0.5 <= rating) {
                star.addClass('half-filled');
            }
        });
    });
    
    // Price level tooltip
    $('.tpwe-price-level').each(function() {
        var priceLevel = $(this).text().length;
        var tooltipText = '';
        
        switch(priceLevel) {
            case 1:
                tooltipText = 'Ekonomik';
                break;
            case 2:
                tooltipText = 'Orta';
                break;
            case 3:
                tooltipText = 'Pahalı';
                break;
            case 4:
                tooltipText = 'Çok Pahalı';
                break;
            default:
                tooltipText = 'Fiyat bilgisi yok';
        }
        
        $(this).attr('title', tooltipText);
    });
    
    // Modal açma (detaylı görünüm için)
    $('.tpwe-place-card').on('click', function(e) {
        if (!$(e.target).closest('a').length) {
            var placeId = $(this).data('place-id');
            
            if (placeId) {
                openPlaceModal(placeId);
            }
        }
    });
    
    function openPlaceModal(placeId) {
        $.ajax({
            url: tpwe_public.ajax_url,
            type: 'POST',
            data: {
                action: 'tpwe_get_place_details',
                place_id: placeId,
                nonce: tpwe_public.nonce
            },
            beforeSend: function() {
                $('#tpwe_modal_loading').show();
            },
            success: function(response) {
                if (response.success) {
                    showModal(response.data);
                }
            }
        });
    }
    
    function showModal(content) {
        var modal = $('<div class="tpwe-modal-overlay">' +
                      '<div class="tpwe-modal">' +
                      '<button class="tpwe-modal-close">&times;</button>' +
                      '<div class="tpwe-modal-content">' + content + '</div>' +
                      '</div>' +
                      '</div>');
        
        $('body').append(modal);
        
        // Modal kapatma
        modal.find('.tpwe-modal-close').on('click', function() {
            modal.remove();
        });
        
        modal.on('click', function(e) {
            if ($(e.target).hasClass('tpwe-modal-overlay')) {
                modal.remove();
            }
        });
        
        // ESC tuşu ile kapatma
        $(document).on('keyup', function(e) {
            if (e.keyCode === 27) {
                modal.remove();
            }
        });
    }
    
    // Filtreleme özelliği (eğer widget'da filtre varsa)
    $('.tpwe-filter').on('change', function() {
        var filterType = $(this).data('filter');
        var filterValue = $(this).val();
        
        $('.tpwe-place-card').each(function() {
            var card = $(this);
            var showCard = true;
            
            if (filterType === 'rating' && filterValue !== 'all') {
                var rating = parseFloat(card.find('.rating-value').text());
                var minRating = parseFloat(filterValue);
                
                if (rating < minRating) {
                    showCard = false;
                }
            }
            
            if (filterType === 'price' && filterValue !== 'all') {
                var priceLevel = card.find('.tpwe-price-level').text().length;
                var maxPrice = parseInt(filterValue);
                
                if (priceLevel > maxPrice) {
                    showCard = false;
                }
            }
            
            if (showCard) {
                card.show();
            } else {
                card.hide();
            }
        });
    });
    
    // Sıralama özelliği
    $('.tpwe-sort').on('change', function() {
        var sortBy = $(this).val();
        var container = $('.tpwe-grid-container');
        var cards = container.find('.tpwe-place-card').get();
        
        cards.sort(function(a, b) {
            var aRating = parseFloat($(a).find('.rating-value').text());
            var bRating = parseFloat($(b).find('.rating-value').text());
            
            var aReviews = parseInt($(a).find('.total-ratings').text().replace(/[^0-9]/g, ''));
            var bReviews = parseInt($(b).find('.total-ratings').text().replace(/[^0-9]/g, ''));
            
            switch(sortBy) {
                case 'rating_desc':
                    return bRating - aRating;
                case 'rating_asc':
                    return aRating - bRating;
                case 'reviews_desc':
                    return bReviews - aReviews;
                default:
                    return 0;
            }
        });
        
        container.empty().append(cards);
    });
});